<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

namespace Victory\FormHandler;

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Error;
use Bitrix\Main\SystemException;
use Bitrix\Main\EventManager;
use Bitrix\Main\Web\HttpClient;
use CBitrixComponent;
use CEvent;
use CEventType;
use CEventMessage;
use CIBlockElement;

use Victory\FormHandler\Lib\ValidationException;

Loc::loadMessages(__FILE__);

Loader::registerAutoLoadClasses(null, [
    __NAMESPACE__ . '\Lib\ValidationException' => '/local/components/victory/form.handler/lib/ValidationException.php',
]);

class FormHandlerComponent extends CBitrixComponent implements Controllerable
{
    protected $errors = [];
    protected $result = [];

    public function configureActions()
    {
        return [
            'submit' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
        ];
    }

    public function onPrepareComponentParams($arParams)
    {
        $arParams['STORAGE_TYPE'] = $arParams['STORAGE_TYPE'] === 'highload' ? 'highload' : 'iblock';
        $arParams['USE_RECAPTCHA'] = $arParams['USE_RECAPTCHA'] === 'Y' ? 'Y' : 'N';
        $arParams['USE_SMARTCAPTCHA'] = $arParams['USE_SMARTCAPTCHA'] === 'Y' ? 'Y' : 'N';
        $arParams['B24_MODE'] = $arParams['B24_MODE'] === 'deal' ? 'deal' : 'lead';
        return $arParams;
    }

    public function executeComponent()
    {
        $this->arResult['FORM_ID'] = $this->randString();
        $this->includeComponentTemplate();
    }

    public function submitAction()
    {
        try {
            $request = Context::getCurrent()->getRequest();
            $post = $request->getPostList()->toArray();
            $files = $request->getFileList()->toArray();
            $arParams = $this->arParams;

            // 1. Валидация капчи
            if (!$this->validateCaptcha($post, $arParams)) {
                return $this->jsonResponse(false, Loc::getMessage('VICTORY_FORM_CAPTCHA_ERROR'), $this->errors);
            }

            // 2. Получение структуры полей
            $fields = $this->getFieldsStructure($arParams);
            if (!$fields) {
                return $this->jsonResponse(false, Loc::getMessage('VICTORY_FORM_FIELDS_ERROR'));
            }

            // 3. Валидация и фильтрация
            $data = $this->validateAndFilter($post, $files, $fields);
            if (!$data['success']) {
                return $this->jsonResponse(false, Loc::getMessage('VICTORY_FORM_VALIDATION_ERROR'), $data['errors']);
            }

            // 4. Событие OnBeforeFormSave
            $event = new \Bitrix\Main\Event('victory', 'OnBeforeFormSave', [
                'FIELDS' => &$data['fields'],
                'PARAMS' => $arParams,
            ]);
            $event->send();

            // 5. Сохранение
            $saveResult = $this->saveData($data['fields'], $arParams);
            if (!$saveResult['success']) {
                return $this->jsonResponse(false, Loc::getMessage('VICTORY_FORM_SAVE_ERROR'), $saveResult['errors']);
            }

            // 6. Почтовое событие
            $this->sendMailEvent($data['fields'], $arParams);

            // 7. Интеграция с Bitrix24
            $b24Result = $this->sendToBitrix24($data['fields'], $arParams);
            if ($b24Result === false) {
                return $this->jsonResponse(false, 'Ошибка интеграции с Bitrix24.');
            }

            // 8. Событие OnAfterFormSave
            $event = new \Bitrix\Main\Event('victory', 'OnAfterFormSave', [
                'FIELDS' => $data['fields'],
                'PARAMS' => $arParams,
                'ID' => $saveResult['id'],
            ]);
            $event->send();

            return $this->jsonResponse(true, Loc::getMessage('VICTORY_FORM_SUCCESS'));

        } catch (ValidationException $e) {
            return $this->jsonResponse(false, $e->getMessage(), $e->getErrors());

        } catch (\Throwable $e) {
            // Логируем ошибку
            if (class_exists('Bitrix\\Main\\Diag\\Debug')) {
                \Bitrix\Main\Diag\Debug::writeToFile($e->getMessage().'\n'.$e->getTraceAsString(), 'FormHandler Error', '/bitrix/logs/form_handler.log');
            }
            return $this->jsonResponse(false, 'Внутренняя ошибка сервера. Попробуйте позже.', [$e->getMessage()]);
        }
    }

    protected function jsonResponse($success, $message, $errors = [])
    {
        global $APPLICATION;
        $APPLICATION->RestartBuffer();
        header('Content-Type: application/json');
        echo Json::encode([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
        ]);
        die();
    }

    protected function validateCaptcha($post, $arParams)
    {
        if ($arParams['USE_RECAPTCHA'] === 'Y' && !empty($arParams['RECAPTCHA_SECRET'])) {
            return $this->checkRecaptcha($post['g-recaptcha-response'], $arParams['RECAPTCHA_SECRET']);
        }
        if ($arParams['USE_SMARTCAPTCHA'] === 'Y' && !empty($arParams['SMARTCAPTCHA_SECRET'])) {
            return $this->checkSmartCaptcha($post['smart-token'], $arParams['SMARTCAPTCHA_SECRET']);
        }
        return true;
    }

    protected function checkRecaptcha($token, $secret)
    {
        if (!$token) return false;
        $client = new HttpClient();
        $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
        ]);
        $result = Json::decode($response);
        return !empty($result['success']);
    }

    protected function checkSmartCaptcha($token, $secret)
    {
        if (!$token) return false;
        $client = new HttpClient();
        $response = $client->post('https://smartcaptcha.yandexcloud.net/validate', [
            'secret' => $secret,
            'token' => $token,
        ]);
        $result = Json::decode($response);
        return !empty($result['status']) && $result['status'] === 'ok';
    }

    protected function getFieldsStructure($arParams)
    {
        if ($arParams['STORAGE_TYPE'] === 'iblock' && Loader::includeModule('iblock')) {
            $iblockId = (int)$arParams['IBLOCK_ID'];
            if ($iblockId > 0) {
                $entity = \Bitrix\Iblock\Elements\ElementTable::compileEntityByIblock($iblockId);
                $fields = $entity->getFields();
                return $fields;
            }
        } elseif ($arParams['STORAGE_TYPE'] === 'highload' && Loader::includeModule('highloadblock')) {
            $hlblockId = (int)$arParams['HLBLOCK_ID'];
            if ($hlblockId > 0) {
                $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($hlblockId)->fetch();
                $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
                $fields = $entity->getFields();
                return $fields;
            }
        }
        return false;
    }

    protected function validateAndFilter($post, $files, $fields)
    {
        $result = ['success' => true, 'fields' => [], 'errors' => []];
        foreach ($fields as $code => $field) {
            $isRequired = $field->isRequired();
            $value = isset($post[$code]) ? $post[$code] : null;
            if ($field instanceof \Bitrix\Main\Entity\FileField) {
                $value = isset($files[$code]) ? $files[$code] : null;
            }
            if ($isRequired && (empty($value) && $value !== '0')) {
                $result['success'] = false;
                $result['errors'][$code] = Loc::getMessage('VICTORY_FORM_REQUIRED', ['#FIELD#' => $code]);
            }
            // Фильтрация и XSS
            if (is_string($value)) {
                $value = htmlspecialcharsbx(trim($value));
            }
            $result['fields'][$code] = $value;
        }
        return $result;
    }

    protected function saveData($fields, $arParams)
    {
        if ($arParams['STORAGE_TYPE'] === 'iblock' && Loader::includeModule('iblock')) {
            $iblockId = (int)$arParams['IBLOCK_ID'];
            if ($iblockId <= 0) {
                return ['success' => false, 'errors' => ['Invalid IBLOCK_ID']];
            }

            $elementEntity = \Bitrix\Iblock\Elements\ElementTable::compileEntityByIblock($iblockId);
            $elementDataClass = $elementEntity->getDataClass();

            $data = [
                'IBLOCK_ID' => $iblockId,
                'NAME' => $fields['NAME'] ?? 'Form ' . date('d.m.Y H:i:s'),
                'ACTIVE' => 'Y',
            ];

            // Распределяем поля по основным и свойствам
            foreach ($fields as $code => $value) {
                if (strpos($code, 'PROPERTY_') === 0) {
                    $propertyCode = str_replace('PROPERTY_', '', $code);
                    $data[$propertyCode] = $value;
                } elseif (array_key_exists($code, $elementDataClass::getMap())) {
                     $data[$code] = $value;
                }
            }
            
            $result = $elementDataClass::add($data);

            if ($result->isSuccess()) {
                return ['success' => true, 'id' => $result->getId()];
            } else {
                return ['success' => false, 'errors' => $result->getErrorMessages()];
            }

        } elseif ($arParams['STORAGE_TYPE'] === 'highload' && Loader::includeModule('highloadblock')) {
            $hlblockId = (int)$arParams['HLBLOCK_ID'];
            if ($hlblockId <= 0) {
                return ['success' => false, 'errors' => ['Invalid HLBLOCK_ID']];
            }
            $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($hlblockId)->fetch();
            $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
            $dataClass = $entity->getDataClass();
            $result = $dataClass::add($fields);
            if ($result->isSuccess()) {
                return ['success' => true, 'id' => $result->getId()];
            } else {
                return ['success' => false, 'errors' => $result->getErrorMessages()];
            }
        }
        return ['success' => false, 'errors' => ['Unknown storage type']];
    }

    protected function sendMailEvent($fields, $arParams)
    {
        $email = $arParams['EMAIL_TO'] ?? '';
        if (!$email) return;

        if ($arParams['STORAGE_TYPE'] === 'iblock') {
            $storageId = (int)$arParams['IBLOCK_ID'];
            $eventName = 'VICTORY_FORM_IBLOCK_' . $storageId;
        } else {
            $storageId = (int)$arParams['HLBLOCK_ID'];
            $eventName = 'VICTORY_FORM_HLBLOCK_' . $storageId;
        }

        if ($storageId <= 0) return;

        // Проверка и создание типа почтового события
        $this->ensureMailEventType($eventName, $fields, $arParams);

        // Отправка письма
        CEvent::Send($eventName, defined('SITE_ID') ? SITE_ID : 's1', array_merge($fields, ['EMAIL_TO' => $email]));
    }

    protected function ensureMailEventType($eventType, $fields, $arParams)
    {
        $eventTypeDb = CEventType::GetList(['TYPE_ID' => $eventType]);
        if (!$eventTypeDb->Fetch()) {
            $description = "Доступные поля:\n";
            foreach ($fields as $code => $value) {
                $description .= "#{$code}# - " . ($fields[$code]->getTitle() ?? $code) . "\n";
            }

            $storageName = '';
            if ($arParams['STORAGE_TYPE'] === 'iblock') {
                $res = \CIBlock::GetByID((int)$arParams['IBLOCK_ID']);
                if($ar_res = $res->GetNext()) $storageName = $ar_res['NAME'];
            } else {
                 $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById((int)$arParams['HLBLOCK_ID'])->fetch();
                 if ($hlblock) $storageName = $hlblock['NAME'];
            }

            $et = new CEventType();
            $et->Add([
                'LID' => 'ru',
                'EVENT_NAME' => $eventType,
                'NAME' => "Форма: {$storageName}",
                'DESCRIPTION' => $description,
            ]);

            // Создание шаблона
            $message = "Новая заявка из формы \"{$storageName}\"\n\n" . $description;
            $arFields = [
                'ACTIVE' => 'Y',
                'EVENT_NAME' => $eventType,
                'LID' => defined('SITE_ID') ? SITE_ID : 's1',
                'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                'EMAIL_TO' => '#EMAIL_TO#',
                'SUBJECT' => "Новая заявка с сайта (#SITE_NAME#)",
                'MESSAGE' => $message,
                'BODY_TYPE' => 'text',
            ];
            $em = new CEventMessage();
            $em->Add($arFields);
        }
    }

    protected function sendToBitrix24($fields, $arParams)
    {
        if (empty($arParams['B24_WEBHOOK'])) return true;

        $mapping = Json::decode($arParams['B24_FIELDS_MAPPING']);
        if (empty($mapping)) return false;

        $url = rtrim($arParams['B24_WEBHOOK'], '/');
        $mode = $arParams['B24_MODE'] === 'deal' ? 'crm.deal.add' : 'crm.lead.add';
        
        $dataFields = [];
        foreach ($mapping as $b24Field => $formField) {
            if (isset($fields[$formField])) {
                $dataFields[$b24Field] = $fields[$formField];
            }
        }

        // Поиск контакт�� по телефону/email
        $contactId = null;
        $companyId = null;
        $phoneValue = $fields[$mapping['PHONE'] ?? 'PHONE'] ?? null;
        $emailValue = $fields[$mapping['EMAIL'] ?? 'EMAIL'] ?? null;

        if (!empty($phoneValue)) {
            $contactData = $this->b24FindByComm($url, 'PHONE', $phoneValue);
            if (!empty($contactData['result']['CONTACT'])) {
                $contactId = $contactData['result']['CONTACT'][0];
            }
        }
        if (empty($contactId) && !empty($emailValue)) {
             $contactData = $this->b24FindByComm($url, 'EMAIL', $emailValue);
            if (!empty($contactData['result']['CONTACT'])) {
                $contactId = $contactData['result']['CONTACT'][0];
            }
        }
        
        if (empty($contactId) && !empty($contactData['result']['COMPANY'])) {
            $companyId = $contactData['result']['COMPANY'][0];
        }

        if ($contactId) {
            $dataFields['CONTACT_ID'] = $contactId;
        }
        if ($companyId) {
            $dataFields['COMPANY_ID'] = $companyId;
        }

        $data = [
            'fields' => $dataFields,
            'params' => ['REGISTER_SONET_EVENT' => 'Y'],
        ];

        try {
            $client = new HttpClient();
            $client->setHeader('Content-Type', 'application/json');
            $response = $client->post($url . '/' . $mode, Json::encode($data));
            $result = Json::decode($response);
            return isset($result['result']);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function b24FindByComm($url, $type, $value)
    {
        $client = new HttpClient();
        $client->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $queryData = http_build_query([
            'entity_type' => 'CONTACT',
            'type' => $type,
            'values' => [$value],
        ]);
        $response = $client->post($url . '/crm.duplicate.findbycomm.json', $queryData);
        return Json::decode($response);
    }

    public function getSignedParameters()
    {
        return $this->arParams;
    }
} 