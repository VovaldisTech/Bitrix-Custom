<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class CBPCrmLinkedParser extends CBPActivity
{
	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = [
			'Title'             => '',
			'TargetEntityType'  => 'CONTACT',
			'ReturnField'       => '',
			'PrimaryOnly'       => 'Y',
			'FilterLogicGlobal' => 'AND',
			'FilterFields'      => [],
			'FilterLogics'      => [],
			'FilterValues'      => [],
			'ResultValues'      => [],
			'ResultString'      => '',
			'FoundIds'          => [],
			'FoundCount'        => 0
		];
	}

	public function Execute()
	{
		if (!CModule::IncludeModule('crm')) return CBPActivityExecutionStatus::Closed;

		$documentId = $this->GetDocumentId();
		$dealId = 0;
		if (isset($documentId[2])) {
			$dealId = intval(str_replace('DEAL_', '', $documentId[2]));
		}

		if ($dealId <= 0) return CBPActivityExecutionStatus::Closed;

		// 1. Поиск связей
		$targetType = $this->TargetEntityType;
		$linkedIds = [];
		if ($targetType === 'CONTACT') {
			$linkedIds = \Bitrix\Crm\Binding\DealContactTable::getDealContactIDs($dealId);
		} elseif ($targetType === 'COMPANY') {
			$res = CCrmDeal::GetListEx([], ['ID' => $dealId, 'CHECK_PERMISSIONS' => 'N'], false, false, ['COMPANY_ID']);
			if ($row = $res->Fetch()) { if ($row['COMPANY_ID'] > 0) $linkedIds[] = $row['COMPANY_ID']; }
		}

		if (empty($linkedIds)) {
			$this->cleanReturn();
			return CBPActivityExecutionStatus::Closed;
		}

		// 2. Подготовка фильтров
		$returnField = (string)$this->ReturnField;
		$isMultiFieldReturn = in_array($returnField, ['EMAIL', 'PHONE', 'WEB', 'IM']);
		
		$selectFields = ['ID'];
		if ($returnField && !$isMultiFieldReturn) $selectFields[] = $returnField;

		$filters = [];
		$fFields = is_array($this->FilterFields) ? $this->FilterFields : [];
		$fLogics = is_array($this->FilterLogics) ? $this->FilterLogics : [];
		$fValues = is_array($this->FilterValues) ? $this->FilterValues : [];

		// Флаги: нужно ли нам доставать мультиполя для фильтрации
		$needMultiFetch = ['EMAIL' => false, 'PHONE' => false, 'WEB' => false, 'IM' => false];

		foreach ($fFields as $k => $code) {
			if (!$code) continue;
			$selectFields[] = $code;
			$filters[] = ['field' => $code, 'logic' => $fLogics[$k], 'value' => $fValues[$k]];

			// Проверяем, используется ли мультиполе в фильтре
			if (array_key_exists($code, $needMultiFetch)) {
				$needMultiFetch[$code] = true;
			}
		}

		// 3. Запрос основных данных
		$foundData = [];
		$res = ($targetType === 'CONTACT')
			? CCrmContact::GetListEx([], ['@ID' => $linkedIds, 'CHECK_PERMISSIONS' => 'N'], false, false, array_unique($selectFields))
			: CCrmCompany::GetListEx([], ['@ID' => $linkedIds, 'CHECK_PERMISSIONS' => 'N'], false, false, array_unique($selectFields));

		while ($row = $res->Fetch()) {
			
			// --- ВАЖНО: Подгружаем данные мультиполей для текущей строки, если они нужны в фильтре ---
			foreach ($needMultiFetch as $mfCode => $needed) {
				if ($needed) {
					// Если поле не пришло из GetListEx (а оно обычно не приходит), достаем его
					if (!isset($row[$mfCode]) || empty($row[$mfCode])) {
						$fmRes = CCrmFieldMulti::GetList(
							['ID' => 'asc'],
							['ENTITY_ID' => $targetType, 'ELEMENT_ID' => $row['ID'], 'TYPE_ID' => $mfCode]
						);
						$fmValues = [];
						while ($fmRow = $fmRes->Fetch()) {
							$fmValues[] = $fmRow['VALUE'];
						}
						// Склеиваем в строку для простоты проверки (empty, contains)
						$row[$mfCode] = implode(', ', $fmValues);
					}
				}
			}
			// -----------------------------------------------------------------------------------------

			// Применяем фильтры
			$match = ($this->FilterLogicGlobal === 'OR') ? false : true;
			
			if (empty($filters)) {
				$match = true;
			} else {
				foreach ($filters as $f) {
					$val = isset($row[$f['field']]) ? $row[$f['field']] : '';
					$resCheck = $this->checkCond($val, $f['logic'], $f['value']);
					
					if ($this->FilterLogicGlobal === 'AND') {
						if (!$resCheck) { $match = false; break; }
					} else { // OR
						if ($resCheck) { $match = true; break; }
					}
				}
			}

			if ($match) {
				$foundData[] = $row;
			}
		}

		// 4. Сбор результата
		$resValues = []; $resIds = [];
		foreach ($foundData as $item) {
			$resIds[] = $item['ID'];
			if ($isMultiFieldReturn) {
				$mRes = CCrmFieldMulti::GetList(['ID'=>'asc'], ['ENTITY_ID'=>$targetType, 'ELEMENT_ID'=>$item['ID'], 'TYPE_ID'=>$returnField]);
				while ($mRow = $mRes->Fetch()) {
					$resValues[] = $mRow['VALUE'];
					if ($this->PrimaryOnly === 'Y') break;
				}
			} else {
				if (isset($item[$returnField]) && $item[$returnField]) $resValues[] = $item[$returnField];
			}
		}

		$this->ResultValues = $resValues;
		$this->ResultString = implode(', ', $resValues);
		$this->FoundIds = $resIds;
		$this->FoundCount = count($resIds);

		return CBPActivityExecutionStatus::Closed;
	}

	private function checkCond($real, $op, $check) {
		if (is_array($real)) $real = implode(',', $real);
		$realStr = mb_strtolower(trim((string)$real));
		$checkStr = mb_strtolower(trim((string)$check));

		switch ($op) {
			case 'EQ': return $realStr == $checkStr;
			case 'NE': return $realStr != $checkStr;
			case 'CONT': return (mb_strpos($realStr, $checkStr) !== false);
			case 'NOT_CONT': return (mb_strpos($realStr, $checkStr) === false);
			case 'GT': return (float)$real > (float)$check;
			case 'LT': return (float)$real < (float)$check;
			case 'EMPTY': return empty($realStr);
			case 'NOT_EMPTY': return !empty($realStr);
			case 'IN_LIST': 
				$checkArr = array_map('trim', explode(',', $checkStr));
				return in_array($realStr, $checkArr);
			default: return false;
		}
	}

	private function cleanReturn() {
		$this->FoundIds = []; $this->FoundCount = 0; $this->ResultValues = []; $this->ResultString = '';
	}
	
	private static function getFieldsMetadata() {
		if (!CModule::IncludeModule('crm')) return [];
		$result = ['CONTACT' => [], 'COMPANY' => []];
		
		$map = [
			'CONTACT' => ['CLASS' => 'CCrmContact', 'STATUS_ENTITIES' => ['SOURCE' => 'SOURCE_ID', 'CONTACT_TYPE' => 'TYPE_ID']],
			'COMPANY' => ['CLASS' => 'CCrmCompany', 'STATUS_ENTITIES' => ['INDUSTRY' => 'INDUSTRY', 'EMPLOYEES' => 'EMPLOYEES', 'COMPANY_TYPE' => 'COMPANY_TYPE']]
		];

		foreach ($map as $type => $info) {
			$rawFields = $info['CLASS']::GetFields();
			foreach ($rawFields as $k => $v) {
				if (in_array($k, ['ID', 'DATE_CREATE', 'DATE_MODIFY'])) continue;
				
				$fieldData = ['Name' => $v['TITLE'] ?: $k, 'Type' => $v['TYPE'] ?: 'string', 'Options' => null];

				if ($k == 'OPENED' || $k == 'EXPORT' || $k == 'IS_MY_COMPANY') {
					$fieldData['Type'] = 'bool'; $fieldData['Options'] = ['Y' => 'Да', 'N' => 'Нет'];
				}
				$result[$type][$k] = $fieldData;
			}
			foreach ($info['STATUS_ENTITIES'] as $statusId => $fieldCode) {
				if (isset($result[$type][$fieldCode])) {
					$result[$type][$fieldCode]['Type'] = 'list';
					$result[$type][$fieldCode]['Options'] = CCrmStatus::GetStatusList($statusId);
				}
			}
			$result[$type]['EMAIL'] = ['Name' => 'E-mail', 'Type' => 'string'];
			$result[$type]['PHONE'] = ['Name' => 'Телефон', 'Type' => 'string'];
			$result[$type]['WEB'] = ['Name' => 'Сайт', 'Type' => 'string'];
			$result[$type]['IM'] = ['Name' => 'Мессенджер', 'Type' => 'string'];

			$entID = ($type == 'CONTACT') ? 'CRM_CONTACT' : 'CRM_COMPANY';
			$ufs = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields($entID, 0, LANGUAGE_ID);
			foreach ($ufs as $k => $v) {
				$fType = 'string'; $opts = null;
				if ($v['USER_TYPE_ID'] == 'boolean') {
					$fType = 'bool'; $opts = ['1' => 'Да', '0' => 'Нет'];
				} elseif ($v['USER_TYPE_ID'] == 'enumeration') {
					$fType = 'list';
					$enum = new CUserFieldEnum();
					$dbRes = $enum->GetList([], ['USER_FIELD_ID' => $v['ID']]);
					while ($el = $dbRes->GetNext()) { $opts[$el['ID']] = $el['VALUE']; }
				}
				$result[$type][$k] = ['Name' => $v['EDIT_FORM_LABEL'] ?: $k, 'Type' => $fType, 'Options' => $opts];
			}
			uasort($result[$type], function($a, $b) { return strcmp($a['Name'], $b['Name']); });
		}
		return $result;
	}

	public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $currentValues = null, $formName = "")
	{
		if (!is_array($currentValues)) {
			$currentValues = ['TargetEntityType' => 'CONTACT', 'ReturnField' => '', 'PrimaryOnly' => 'Y', 'FilterLogicGlobal' => 'AND'];
			$curr = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
			if ($curr && is_array($curr['Properties'])) $currentValues = array_merge($currentValues, $curr['Properties']);
		}
		$fieldsData = self::getFieldsMetadata();
		$runtime = CBPRuntime::GetRuntime();
		return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php", [
			'currentValues' => $currentValues,
			'fieldsJSON' => \Bitrix\Main\Web\Json::encode($fieldsData)
		]);
	}

	public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $currentValues, &$errors)
	{
		$properties = [
			'TargetEntityType' => $currentValues['TargetEntityType'],
			'ReturnField' => $currentValues['ReturnField'],
			'PrimaryOnly' => ($currentValues['PrimaryOnly'] === 'Y' || $currentValues['PrimaryOnly'] === 'on') ? 'Y' : 'N',
			'FilterLogicGlobal' => $currentValues['FilterLogicGlobal'],
			'FilterFields' => isset($currentValues['FilterFields']) ? $currentValues['FilterFields'] : [],
			'FilterLogics' => isset($currentValues['FilterLogics']) ? $currentValues['FilterLogics'] : [],
			'FilterValues' => isset($currentValues['FilterValues']) ? $currentValues['FilterValues'] : [],
		];
		$curr = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
		$curr['Properties'] = $properties;
		return true;
	}
}