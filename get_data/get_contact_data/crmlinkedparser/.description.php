<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arActivityDescription = array(
	'NAME' => 'Парсер связанных сущностей (Универсальный)',
	'DESCRIPTION' => 'Парсит контакты/компании сделки в массив по фильтру',
	'TYPE' => 'activity',
	'CLASS' => 'CrmLinkedParser',
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => array(
		'ID' => 'document',
		"OWN_ID" => 'crm',
		"OWN_NAME" => 'CRM',
	),
	'RETURN' => array(
		'ResultValues' => array(
			'NAME' => 'Найденные значения (Массив)',
			'TYPE' => 'string',
			'MULTIPLE' => true,
		),
		'ResultString' => array(
			'NAME' => 'Найденные значения (Строка)',
			'TYPE' => 'string',
		),
		'FoundIds' => array(
			'NAME' => 'ID найденных сущностей',
			'TYPE' => 'int',
			'MULTIPLE' => true,
		),
		'FoundCount' => array(
			'NAME' => 'Количество найденных',
			'TYPE' => 'int',
		),
	),
);