<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<tr>
	<td align="right" width="40%"><b>Кого ищем:</b></td>
	<td width="60%">
		<select name="TargetEntityType" id="id_target_type" onchange="UpdateFields()">
			<option value="CONTACT" <?=($currentValues['TargetEntityType']=='CONTACT'?'selected':'')?>>Контакты</option>
			<option value="COMPANY" <?=($currentValues['TargetEntityType']=='COMPANY'?'selected':'')?>>Компании</option>
		</select>
	</td>
</tr>

<tr>
	<td align="right"><b>Поле для возврата:</b></td>
	<td>
		<select name="ReturnField" id="id_return_field"></select><br>
		<input type="checkbox" name="PrimaryOnly" value="Y" <?=($currentValues['PrimaryOnly']=='Y'?'checked':'')?>> Только основной (для Email/Тел)
	</td>
</tr>

<tr><td colspan="2"><hr><b>Фильтры</b></td></tr>
<tr>
	<td align="right">Логика:</td>
	<td>
		<!-- ИСПРАВЛЕНО: $currentValues вместо $arCurrentValues -->
		<select name="FilterLogicGlobal">
			<option value="AND" <?=($currentValues['FilterLogicGlobal']=='AND'?'selected':'')?>>И (Все условия)</option>
			<option value="OR" <?=($currentValues['FilterLogicGlobal']=='OR'?'selected':'')?>>ИЛИ (Любое)</option>
		</select>
	</td>
</tr>

<tr>
	<td colspan="2">
		<table width="100%" class="adm-list-table" id="filter_table">
			<thead>
				<tr class="adm-list-table-header">
					<td class="adm-list-table-cell"><div class="adm-list-table-cell-inner">Поле</div></td>
					<td class="adm-list-table-cell"><div class="adm-list-table-cell-inner">Условие</div></td>
					<td class="adm-list-table-cell"><div class="adm-list-table-cell-inner">Значение</div></td>
					<td class="adm-list-table-cell"></td>
				</tr>
			</thead>
			<tbody id="filter_body"></tbody>
		</table>
		<br>
		<input type="button" value="+ Добавить условие" onclick="AddRow()">
	</td>
</tr>

<script>
var allFields = <?=$fieldsJSON?>; 
var savedValues = {
	return: '<?=$currentValues['ReturnField']?>',
	f: <?=json_encode($currentValues['FilterFields'] ?: [])?>,
	l: <?=json_encode($currentValues['FilterLogics'] ?: [])?>,
	v: <?=json_encode($currentValues['FilterValues'] ?: [])?>
};

function UpdateFields() {
	var type = document.getElementById('id_target_type').value;
	var fields = allFields[type];
	
	var sel = document.getElementById('id_return_field');
	sel.innerHTML = '';
	for (var k in fields) {
		var opt = new Option(fields[k].Name, k);
		if (k == savedValues.return) opt.selected = true;
		sel.options.add(opt);
	}
}

function AddRow(f, l, v) {
	var tbody = document.getElementById('filter_body');
	var tr = document.createElement('tr');
	tr.className = 'adm-list-table-row';
	
	// 1. Поле
	var tdF = document.createElement('td'); tdF.className = 'adm-list-table-cell';
	var selF = document.createElement('select'); 
	selF.name = 'FilterFields[]'; selF.style.width='200px';
	
	// Обработчик смены поля
	selF.onchange = function() { UpdateRowInput(tr, this.value); };
	
	var type = document.getElementById('id_target_type').value;
	var fields = allFields[type];
	selF.add(new Option('--- Выберите поле ---', ''));
	for (var k in fields) {
		var opt = new Option(fields[k].Name, k);
		if (f && k == f) opt.selected = true;
		selF.add(opt);
	}
	tdF.appendChild(selF);
	
	// 2. Логика
	var tdL = document.createElement('td'); tdL.className = 'adm-list-table-cell';
	var selL = document.createElement('select'); 
	selL.name = 'FilterLogics[]';
	var logics = {EQ:'Равно', NE:'Не равно', CONT:'Содержит', NOT_CONT:'Не содержит', GT:'Больше', LT:'Меньше', IN_LIST:'В списке (a,b,c)', EMPTY:'Пусто', NOT_EMPTY:'Не пусто'};
	for (var k in logics) {
		var opt = new Option(logics[k], k);
		if (l && k == l) opt.selected = true;
		selL.add(opt);
	}
	
	// Обработчик смены логики (скрывать инпут)
	selL.onchange = function() { ToggleValueVisibility(tr, this.value); };
	
	tdL.appendChild(selL);

	// 3. Значение
	var tdV = document.createElement('td'); tdV.className = 'adm-list-table-cell';
	var divV = document.createElement('div'); 
	divV.className = 'value-container';
	tdV.appendChild(divV);

	// 4. Удалить
	var tdD = document.createElement('td'); tdD.className = 'adm-list-table-cell';
	var btn = document.createElement('a');
	btn.href = 'javascript:void(0)';
	btn.innerHTML = '×';
	btn.style.color='red'; btn.style.fontWeight='bold'; btn.style.textDecoration='none';
	btn.onclick = function() { tr.remove(); };
	tdD.appendChild(btn);

	tr.appendChild(tdF); tr.appendChild(tdL); tr.appendChild(tdV); tr.appendChild(tdD);
	tbody.appendChild(tr);

	UpdateRowInput(tr, (f || ''), v);
	// Инициализируем видимость при загрузке
	ToggleValueVisibility(tr, (l || 'EQ'));
}

function ToggleValueVisibility(tr, logic) {
	var container = tr.querySelector('.value-container');
	if (logic === 'EMPTY' || logic === 'NOT_EMPTY') {
		container.style.display = 'none';
	} else {
		container.style.display = 'block';
	}
}

function UpdateRowInput(tr, fieldCode, value) {
	var type = document.getElementById('id_target_type').value;
	var fieldMeta = allFields[type][fieldCode];
	var container = tr.querySelector('.value-container');
	container.innerHTML = ''; 

	var input;
	if (fieldMeta && fieldMeta.Options) {
		input = document.createElement('select');
		input.name = 'FilterValues[]';
		input.style.width = '200px';
		var defOpt = new Option('(Любое)', '');
		input.add(defOpt);
		for (var valKey in fieldMeta.Options) {
			var opt = new Option(fieldMeta.Options[valKey], valKey);
			if (value && valKey == value) opt.selected = true;
			input.add(opt);
		}
	} else {
		input = document.createElement('input');
		input.type = 'text';
		input.name = 'FilterValues[]';
		input.style.width = '200px';
		if (value) input.value = value;
		if (fieldMeta && fieldMeta.Type === 'bool' && !fieldMeta.Options) input.placeholder = '1 или 0';
	}
	container.appendChild(input);
}

UpdateFields();
if (savedValues.f.length > 0) {
	for(var i=0; i<savedValues.f.length; i++) AddRow(savedValues.f[i], savedValues.l[i], savedValues.v[i]);
} else {
	AddRow();
}
</script>