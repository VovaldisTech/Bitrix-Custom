<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
	'NAME' => Loc::getMessage('CRM_SEMA_NAME_1_MSG_1') . ' (Custom Email)',
	'DESCRIPTION' => Loc::getMessage('CRM_SEMA_DESC_1_MSG_1'),
	'TYPE' => ['activity', 'robot_activity'],
	'CLASS' => 'CrmSendCustomEmailActivity',
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => [
		'ID' => 'document',
		"OWN_ID" => 'crm',
		"OWN_NAME" => 'CRM',
	],
	'FILTER' => [
		'INCLUDE' => [
			['crm', 'CCrmDocumentLead'],
			['crm', 'CCrmDocumentDeal'],
			['crm', 'CCrmDocumentContact'],
			['crm', 'CCrmDocumentCompany'],
			['crm', 'Bitrix\Crm\Integration\BizProc\Document\Order'],
			['crm', 'Bitrix\Crm\Integration\BizProc\Document\Dynamic'],
			['crm', 'Bitrix\Crm\Integration\BizProc\Document\Quote'],
			['crm', 'Bitrix\Crm\Integration\BizProc\Document\SmartInvoice'],
			['crm', \Bitrix\Crm\Integration\BizProc\Document\SmartDocument::class],
		],
	],
	'ROBOT_SETTINGS' => [
		'CATEGORY' => 'client',
		'GROUP' => ['clientCommunication', 'delivery'],
		'SORT' => 1100,
	],
	'RETURN' => [
		'Status' => [
			'NAME' => 'Status (Y/N)',
			'TYPE' => 'string',
		],
		'Error_text' => [
			'NAME' => 'Error Text',
			'TYPE' => 'string',
		],
	],
];