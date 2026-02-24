<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arActivityDescription = array(
	'NAME' => 'Рандомайзер текста (Spintax)',
	'DESCRIPTION' => 'Генерация текста на основе переменных с весами и конструкции {Текст1|Текст2}',
	'TYPE' => 'activity',
	'CLASS' => 'TextRandomizerActivity',
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => array(
		'ID' => 'document',
		"OWN_ID" => 'crm',
		"OWN_NAME" => 'CRM',
	),
	'RETURN' => array(
		'ResultText' => array(
			'NAME' => 'Сгенерированный текст',
			'TYPE' => 'string',
		),
	),
);
?>