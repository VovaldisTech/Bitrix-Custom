<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<tr>
	<td colspan="2">
		<div style="font-family: Arial, sans-serif; margin-bottom: 20px;">
			<b>Итоговый шаблон текста:</b><br>
			<div style="color: gray; font-size: 11px; margin-bottom: 5px;">
				Используйте созданные переменные (например, {Var1}) или Spintax (например, {Привет|Добрый день}).<br>
				Стандартные теги Битрикс24 ({=Document:NAME}) также поддерживаются. Для их вставки нажмите кнопку "...".
			</div>
			<div style="display: flex; gap: 5px; align-items: flex-start;">
				<textarea name="TemplateText" id="id_template_text" rows="5" style="flex-grow: 1; box-sizing: border-box; padding: 5px;"><?=htmlspecialcharsbx($currentValues['TemplateText'])?></textarea>
				<input type="button" value="..." onclick="BPAShowSelector('id_template_text', 'string');" style="padding: 5px 10px; cursor: pointer;">
			</div>
		</div>

		<div style="font-family: Arial, sans-serif; margin-bottom: 20px;">
			<b>Настройка переменных:</b>
			<div id="trx_vars_container"></div>
			<button type="button" style="cursor: pointer; padding: 5px 10px; border: none; border-radius: 3px; color: #fff; background: #00a2e8;" onclick="window.TRXApp.addVar()">+ Добавить переменную</button>
		</div>

		<input type="hidden" id="VarJsonData" name="VarJsonData" value="<?=htmlspecialcharsbx($currentValues['VarJsonData'] ?: '[]')?>">

<script>
window.TRXApp = {
	data: [],
	varCounter: 1,

	init: function() {
		this.data = [];
		this.varCounter = 1;
		var initialJson = document.getElementById('VarJsonData').value;
		if(initialJson && initialJson !== '[]') {
			try { this.data = JSON.parse(initialJson); } catch(e) { this.data = []; }
			this.data.forEach(function(v) {
				var match = v.name.match(/^Var(\d+)$/);
				if (match && parseInt(match[1]) >= window.TRXApp.varCounter) {
					window.TRXApp.varCounter = parseInt(match[1]) + 1;
				}
			});
		}
		this.render();
	},

	addVar: function() {
		this.data.push({
			name: 'Var' + this.varCounter++,
			variants: [ {text: '', weight: '', manual: false} ],
			status: '',
			isError: false
		});
		this.validateAndCalculate();
	},

	removeVar: function(index) {
		this.data.splice(index, 1);
		this.validateAndCalculate();
	},

	addVariant: function(varIndex) {
		this.data[varIndex].variants.push({text: '', weight: '', manual: false});
		this.validateAndCalculate();
	},

	removeVariant: function(varIndex, varntIndex) {
		this.data[varIndex].variants.splice(varntIndex, 1);
		this.validateAndCalculate();
	},

	updateName: function(varIndex, val) {
		this.data[varIndex].name = val.replace(/[^a-zA-Z0-9_\-]/g, '');
		this.saveState();
	},

	updateVariantText: function(varIndex, varntIndex, val) {
		this.data[varIndex].variants[varntIndex].text = val;
		this.saveState();
	},

	updateVariantWeight: function(varIndex, varntIndex, val) {
		var v = this.data[varIndex].variants[varntIndex];
		if (val === '') {
			v.weight = '';
			v.manual = false;
		} else {
			v.weight = parseFloat(val);
			v.manual = true;
		}
		this.validateAndCalculate();
	},

	validateAndCalculate: function() {
		this.data.forEach(function(vari) {
			var sumManual = 0;
			var emptyCount = 0;

			vari.variants.forEach(function(v) {
				if (v.manual && typeof v.weight === 'number' && !isNaN(v.weight)) {
					sumManual += v.weight;
				} else {
					v.manual = false;
					emptyCount++;
				}
			});

			sumManual = parseFloat(sumManual.toFixed(2));

			if (sumManual > 100) {
				vari.isError = true;
				vari.status = 'Ошибка! Сумма весов превышает 100% (' + sumManual + '%)';
			} else if (sumManual < 100 && emptyCount === 0) {
				vari.isError = true;
				vari.status = 'Ошибка! Сумма весов ' + sumManual + '%, но нет пустых полей.';
			} else {
				vari.isError = false;
				vari.status = 'ОК (100%)';
				
				if (emptyCount > 0) {
					var remainder = 100 - sumManual;
					var share = Math.floor((remainder / emptyCount) * 100) / 100;
					var diff = parseFloat((remainder - (share * emptyCount)).toFixed(2));

					var appliedEmpty = 0;
					vari.variants.forEach(function(v) {
						if (!v.manual) {
							appliedEmpty++;
							if (appliedEmpty === emptyCount) {
								v.weight = parseFloat((share + diff).toFixed(2));
							} else {
								v.weight = share;
							}
						}
					});
				}
			}
		});

		this.saveState();
		this.render();
	},

	saveState: function() {
		document.getElementById('VarJsonData').value = JSON.stringify(this.data);
	},

	render: function() {
		var container = document.getElementById('trx_vars_container');
		container.innerHTML = '';

		var btnStyle = 'cursor: pointer; padding: 5px 10px; border: none; border-radius: 3px; color: #fff; background: #00a2e8; margin-right: 5px;';
		var btnRedStyle = 'cursor: pointer; padding: 5px 10px; border: none; border-radius: 3px; color: #fff; background: #e80000;';
		var inputStyle = 'padding: 5px; border: 1px solid #ccc; border-radius: 3px;';

		this.data.forEach(function(vari, vIndex) {
			var card = document.createElement('div');
			card.style.cssText = 'border: 1px solid #c0c8cd; border-radius: 4px; padding: 15px; margin-bottom: 15px; background: #f8fafb;';

			var header = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
				'<input type="text" style="' + inputStyle + ' font-weight: bold; width: 200px;" value="' + vari.name + '" placeholder="Имя переменной" onchange="window.TRXApp.updateName(' + vIndex + ', this.value)" title="Только латинские буквы и цифры">' +
				'<button type="button" style="' + btnRedStyle + '" onclick="window.TRXApp.removeVar(' + vIndex + ')">Удалить переменную</button>' +
			'</div>';
			card.innerHTML += header;

			vari.variants.forEach(function(varnt, vnIndex) {
				var displayWeight = varnt.weight !== '' ? varnt.weight : '';
				var placeholder = varnt.manual ? '' : '(авто)';
				var safeText = varnt.text.replace(/"/g, '&quot;');

				var vRow = document.createElement('div');
				vRow.style.cssText = 'display: flex; align-items: center; margin-bottom: 5px; gap: 10px;';
				vRow.innerHTML = '<input type="text" placeholder="Текст варианта" style="' + inputStyle + ' flex-grow: 1;" value="' + safeText + '" onchange="window.TRXApp.updateVariantText(' + vIndex + ', ' + vnIndex + ', this.value)">' +
					'<input type="number" step="0.01" min="0" max="100" placeholder="' + placeholder + '" style="' + inputStyle + ' width: 70px;" value="' + displayWeight + '" onkeyup="window.TRXApp.updateVariantWeight(' + vIndex + ', ' + vnIndex + ', this.value)" onchange="window.TRXApp.updateVariantWeight(' + vIndex + ', ' + vnIndex + ', this.value)">' +
					'<button type="button" style="' + btnRedStyle + '" onclick="window.TRXApp.removeVariant(' + vIndex + ', ' + vnIndex + ')">X</button>';
				card.appendChild(vRow);
			});

			var statusColor = vari.isError ? 'red' : 'green';
			card.innerHTML += '<div style="margin-top: 10px;">' +
				'<button type="button" style="' + btnStyle + '" onclick="window.TRXApp.addVariant(' + vIndex + ')">+ Добавить вариант</button>' +
				'<div style="font-size: 12px; margin-top: 10px; font-weight: bold; color: ' + statusColor + ';">' + vari.status + '</div>' +
			'</div>';

			container.appendChild(card);
		});
	}
};

window.TRXApp.init();
</script>
	</td>
</tr>