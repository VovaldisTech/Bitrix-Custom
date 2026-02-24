<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class CBPTextRandomizerActivity extends CBPActivity
{
	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = [
			'Title' => '',
			'TemplateText' => '',
			'VarJsonData' => '[]',
			'ResultText' => ''
		];
		$this->SetPropertiesTypes([
			'ResultText' => ['Type' => 'string']
		]);
	}

	public function Execute()
	{
		$template = (string)$this->TemplateText;
		$json = (string)$this->VarJsonData;
		
		$variables = json_decode($json, true);
		if (!is_array($variables)) {
			$variables = [];
		}

		$varsMap = [];
		foreach ($variables as $v) {
			$varsMap[$v['name']] = $v['variants'];
		}

		$text = $template;
		$maxLoops = 15;
		$loop = 0;
		$changed = true;

		while ($changed && $loop < $maxLoops) {
			$changed = false;
			$loop++;

			// Обработка {Var1}
			$text = preg_replace_callback('/\{([a-zA-Z0-9_\-]+)\}/', function($matches) use ($varsMap, &$changed) {
				$varName = $matches[1];
				if (isset($varsMap[$varName])) {
					$changed = true;
					return $this->rollDice($varsMap[$varName]);
				}
				return $matches[0];
			}, $text);

			// Обработка {Текст1|Текст2}
			$text = preg_replace_callback('/\{([^={}]*\|[^={}]*)\}/', function($matches) use (&$changed) {
				$changed = true;
				$parts = explode('|', $matches[1]);
				$parts = array_map('trim', $parts);
				return $parts[array_rand($parts)];
			}, $text);
		}

		$this->ResultText = $text;

		return CBPActivityExecutionStatus::Closed;
	}

	private function rollDice($variants)
	{
		if (empty($variants)) return "";

		$rand = mt_rand(1, 10000) / 100;
		$cumulative = 0;

		foreach ($variants as $v) {
			$weight = (float)$v['weight'];
			$cumulative += $weight;
			if ($rand <= $cumulative) {
				return $v['text'];
			}
		}
		
		return end($variants)['text'];
	}

	public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $currentValues = null, $formName = "")
	{
		if (!is_array($currentValues)) {
			$currentValues = [
				'TemplateText' => '',
				'VarJsonData' => '[]'
			];
			$curr = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
			if ($curr && is_array($curr['Properties'])) {
				$currentValues = array_merge($currentValues, $curr['Properties']);
			}
		}

		$runtime = CBPRuntime::GetRuntime();
		return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php", [
			'currentValues' => $currentValues,
			'formName' => $formName
		]);
	}

	public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $currentValues, &$errors)
	{
		$properties = [
			'TemplateText' => isset($currentValues['TemplateText']) ? $currentValues['TemplateText'] : '',
			'VarJsonData' => isset($currentValues['VarJsonData']) ? $currentValues['VarJsonData'] : '[]',
		];

		$curr = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
		$curr['Properties'] = $properties;
		return true;
	}
}
?>