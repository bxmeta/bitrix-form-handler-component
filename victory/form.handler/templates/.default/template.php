<?php if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();
/** @var array $arResult */
/** @var array $arParams */
/** @var CBitrixComponentTemplate $this */
$formId = 'form_' . $arResult['FORM_ID'];
?>

<form id="<?= $formId ?>" class="victory-form-handler" enctype="multipart/form-data">
    <!-- Спиннер загрузки -->
    <div id="<?= $formId ?>_spinner" class="victory-form-spinner">
        <div class="victory-spinner"></div>
        <span>Отправка формы...</span>
    </div>
    
    <div id="<?= $formId ?>_errors" class="form-errors" style="color:red;"></div>

    <!-- Пример полей, замените на свои -->
    <input type="text" name="NAME" placeholder="Ваше имя*" required><br>
    <input type="email" name="EMAIL" placeholder="E-mail*" required><br>
    <input type="tel" name="PHONE" placeholder="Телефон"><br>
    <input type="file" name="FILE"><br>

    <!-- Google reCAPTCHA -->
    <?php if($arParams['USE_RECAPTCHA']==='Y'): ?>
        <div id="<?= $formId ?>_recaptcha" class="g-recaptcha" data-sitekey="<?=htmlspecialcharsbx($arParams['RECAPTCHA_SITEKEY'])?>"></div>
    <?php endif; ?>

    <!-- Yandex SmartCaptcha -->
    <?php if($arParams['USE_SMARTCAPTCHA']==='Y' && !empty($arParams['SMARTCAPTCHA_SITEKEY'])): ?>
        <div id="<?= $formId ?>_smartcaptcha" class="smart-captcha" data-sitekey="<?=htmlspecialcharsbx($arParams['SMARTCAPTCHA_SITEKEY'])?>"></div>
        <input type="hidden" name="smart-token" id="<?= $formId ?>_smart_token">
    <?php endif; ?>

    <button type="submit" class="submit-btn">Отправить</button>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof VictoryFormHandler === 'function') {
            new VictoryFormHandler({
                formId: '<?= CUtil::JSEscape($formId) ?>',
                useSmartCaptcha: <?= $arParams['USE_SMARTCAPTCHA'] === 'Y' && !empty($arParams['SMARTCAPTCHA_SITEKEY']) ? 'true' : 'false' ?>,
            });
        }
    });
</script>

<?php
// Подключаем API капчи только один раз (CSS и JS подключаются автоматически)
if ($arParams['USE_RECAPTCHA'] === 'Y') {
    $this->addExternalJs('https://www.google.com/recaptcha/api.js');
}
if ($arParams['USE_SMARTCAPTCHA'] === 'Y') {
    $this->addExternalJs('https://smartcaptcha.yandexcloud.net/captcha.js?render=onload');
}
?> 