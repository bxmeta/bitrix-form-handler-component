<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = [
    'NAME' => 'Универсальный обработчик форм (D7)',
    'DESCRIPTION' => 'Гибкий AJAX-компонент для обработки любых форм, с поддержкой инфоблоков, HL-блоков, капчи, почты и Bitrix24.',
    'ICON' => '/local/components/victory/form.handler/icon.png',
    'PATH' => [
        'ID' => 'victory',
        'NAME' => 'Victory',
        'CHILD' => [
            'ID' => 'form',
            'NAME' => 'Формы',
        ],
    ],
]; 