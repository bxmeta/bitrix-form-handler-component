<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Localization\Loc;

$arComponentParameters = [
    'PARAMETERS' => [
        'STORAGE_TYPE' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STORAGE_TYPE'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'iblock' => Loc::getMessage('IBLOCK_ID'),
                'highload' => Loc::getMessage('HLBLOCK_ID'),
            ],
            'DEFAULT' => 'iblock',
            'REFRESH' => 'Y',
            'DESCRIPTION' => Loc::getMessage('STORAGE_TYPE_TIP'),
        ],
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('IBLOCK_ID'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('IBLOCK_ID_TIP'),
        ],
        'HLBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('HLBLOCK_ID'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('HLBLOCK_ID_TIP'),
        ],
        'USE_RECAPTCHA' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('USE_RECAPTCHA'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
            'REFRESH' => 'Y',
            'DESCRIPTION' => Loc::getMessage('USE_RECAPTCHA_TIP'),
        ],
        'RECAPTCHA_SECRET' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('RECAPTCHA_SECRET'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('RECAPTCHA_SECRET_TIP'),
        ],
        'RECAPTCHA_SITEKEY' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('RECAPTCHA_SITEKEY'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('RECAPTCHA_SITEKEY_TIP'),
        ],
        'USE_SMARTCAPTCHA' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('USE_SMARTCAPTCHA'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
            'REFRESH' => 'Y',
            'DESCRIPTION' => Loc::getMessage('USE_SMARTCAPTCHA_TIP'),
        ],
        'SMARTCAPTCHA_SECRET' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('SMARTCAPTCHA_SECRET'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('SMARTCAPTCHA_SECRET_TIP'),
        ],
        'SMARTCAPTCHA_SITEKEY' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('SMARTCAPTCHA_SITEKEY'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('SMARTCAPTCHA_SITEKEY_TIP'),
        ],
        'EMAIL_TO' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('EMAIL_TO'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('EMAIL_TO_TIP'),
        ],
        'B24_WEBHOOK' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('B24_WEBHOOK'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'DESCRIPTION' => Loc::getMessage('B24_WEBHOOK_TIP'),
        ],
        'B24_MODE' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('B24_MODE'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'lead' => Loc::getMessage('B24_MODE') . ' (Лид)',
                'deal' => Loc::getMessage('B24_MODE') . ' (Сделка)',
            ],
            'DEFAULT' => 'lead',
            'DESCRIPTION' => Loc::getMessage('B24_MODE_TIP'),
        ],
        'B24_FIELDS_MAPPING' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('B24_FIELDS_MAPPING'),
            'TYPE' => 'STRING',
            'COLS' => 45,
            'ROWS' => 10,
            'DEFAULT' => '{"TITLE":"NAME", "NAME":"NAME", "PHONE":"PHONE", "EMAIL":"EMAIL"}',
            'DESCRIPTION' => Loc::getMessage('B24_FIELDS_MAPPING_TIP'),
        ],
    ],
]; 