<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
// пространство имен для подключений ланговых файлов
use Bitrix\Main\Localization\Loc;
// пространство имен для получения ID модуля
use Bitrix\Main\HttpApplication;
// пространство имен для загрузки необходимых файлов, классов, модулей
use Bitrix\Main\Loader;
// пространство имен для работы с параметрами модулей хранимых в базе данных
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\Json;

// подключение ланговых файлов
Loc::loadMessages(__FILE__);

// получение запроса из контекста для обработки данных
$request = HttpApplication::getInstance()->getContext()->getRequest();

$module_id = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);

// получим права доступа текущего пользователя на модуль
$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);

// если нет прав - отправим к форме авторизации с сообщением об ошибке
if ($POST_RIGHT < "S") {
	$APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}

// подключение модуля
Loader::includeModule($module_id);

// Схема опций (колонка HINT и т.п.) + миграции строк для уже установленных модулей
Tools\GooglePageSpeed\GPSOptionsTable::exitsOrCreateTable();
Tools\GooglePageSpeed\OptionActions::ensureImgAttributeOptions();
Tools\GooglePageSpeed\OptionActions::ensureEliminateScriptsOption();
Tools\GooglePageSpeed\OptionActions::ensureYandexMetrikaCutOption();
Tools\GooglePageSpeed\DeferredPresets::ensureOptions();

// AJAX: скан скриптов публичной страницы
if (
	$request->isPost()
	&& (string)$request->getPost('action') === 'gps_scan_scripts'
	&& check_bitrix_sessid()
) {
	global $APPLICATION;

	$APPLICATION->RestartBuffer();
	header('Content-Type: application/json; charset=UTF-8');

	$existing = $request->getPost('existing');
	if (!is_array($existing)) {
		$existing = [];
	}

	$result = Tools\GooglePageSpeed\PageScriptScanner::scan(
		(string)$request->getPost('page_url'),
		array_map('strval', $existing)
	);

	echo \Bitrix\Main\Web\Json::encode($result);
	die();
}

// AJAX: разброс PSI (variance)
if (
	$request->isPost()
	&& strpos((string)$request->getPost('action'), 'gps_psi_') === 0
	&& check_bitrix_sessid()
) {
	global $APPLICATION;

	$APPLICATION->RestartBuffer();
	header('Content-Type: application/json; charset=UTF-8');

	echo \Bitrix\Main\Web\Json::encode(
		Tools\GooglePageSpeed\Psi\AdminAjax::handle($request)
	);
	die();
}

$aTabs = [
	[
		"DIV"   => "edit1",
		"TAB"   => "Опции",
		"ICON"  => "main_user_edit",
		"TITLE" => "Опции"
	],
	[
		"DIV"   => "edit2",
		"TAB"   => "Тэг link",
		"ICON"  => "main_user_edit",
		"TITLE" => "Тэг link"
	],
	[
		"DIV"   => "edit3",
		"TAB"   => "Тэг script",
		"ICON"  => "main_user_edit",
		"TITLE" => "Тэг script"
	],
	[
		"DIV"   => "edit4",
		"TAB"   => "Разброс PSI",
		"ICON"  => "main_user_edit",
		"TITLE" => "Разброс lab Performance (PSI)"
	]
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);

$arrayLinkCssStyles = Tools\GooglePageSpeed\SettingsProvider::getLinksCssStyles();
$arrayLinkJsScripts = Tools\GooglePageSpeed\SettingsProvider::getLinksJsScripts();
$arrayOptions = Tools\GooglePageSpeed\SettingsProvider::getOptions();
$limitation = ['for-everyone' => 'Для всех', 'for-gps-robot' => 'Для робота Google PS'];
$roleLinkCssStyles = ['preload', 'prefetch', 'preconnect', 'dns-prefetch', 'prerender'];
$typeLinkCssStyles = ['style', 'script', 'font', 'fetch', 'image', 'track'];
$attributeLinkJsScripts = ['async', 'defer'];
$randomId = '';
$active = '';

if ($request["Update"] && check_bitrix_sessid()) {
	// === GPS options
	foreach (($request['OPTIONS'] ?? []) as $keyOption => $valueOption) {
		if (!isset($arrayOptions[$keyOption])) {
			continue;
		}
		$codeOption = (string)($arrayOptions[$keyOption]['CODE_OPTION'] ?? '');
		if (in_array($codeOption, Tools\GooglePageSpeed\OptionsDefinitions::getHiddenOptionCodes(), true)) {
			continue;
		}
		if (($arrayOptions[$keyOption]['OPTION_TYPE'] ?? '') === 'heading') {
			continue;
		}
		if (empty($valueOption["ACTIVE"])) $valueOption["ACTIVE"] = "N";

		if ($arrayOptions[$keyOption]["ACTIVE"] === $valueOption["ACTIVE"] && $arrayOptions[$keyOption]["LIMITATION"] === $valueOption["LIMITATION"]) continue;

		Tools\GooglePageSpeed\GPSOptionsTable::update($arrayOptions[$keyOption]['ID'], [
			"ACTIVE" => $valueOption["ACTIVE"],
			"LIMITATION" => $valueOption["LIMITATION"]
		]);

		$arrayOptions[$keyOption]["ACTIVE"] = $valueOption["ACTIVE"];
		$arrayOptions[$keyOption]["LIMITATION"] = $valueOption["LIMITATION"];
	}
	// === GPS options

	// === LinksCssStyles
	foreach ($arrayLinkCssStyles as $key => $value) {
		$active = 'N';
		if ($request["STRING_PUBLIC_PART"][$key]["ACTIVE"] != null) $active = 'Y';

		// Not changed
		if (
			$request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"] === $value["STRING_PUBLIC_PART"] &&
			$request["STRING_PUBLIC_PART"][$key]["ROLE"] === $value["ROLE"] &&
			$request["STRING_PUBLIC_PART"][$key]["TYPE"] === $value["TYPE"] &&
			$active === $value["ACTIVE"]
		) continue;

		// Delete
		if ($request["STRING_PUBLIC_PART"][$key] == null) {
			Tools\GooglePageSpeed\ConnectedCssStyleTable::delete($value['ID']);
			unset($arrayLinkCssStyles[$key]);
			continue;
		}

		// Update
		Tools\GooglePageSpeed\ConnectedCssStyleTable::update($value['ID'], [
			"ACTIVE" => $active,
			"ROLE" => $request["STRING_PUBLIC_PART"][$key]["ROLE"],
			"TYPE" => $request["STRING_PUBLIC_PART"][$key]["TYPE"],
			"STRING_PUBLIC_PART" => $request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"],
			"STRING_REGULAR_EXPRESSION" => preg_quote($request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"], '/')
		]);

		$arrayLinkCssStyles[$key]["ACTIVE"] = $active;
		$arrayLinkCssStyles[$key]["ROLE"] = $request["STRING_PUBLIC_PART"][$key]["ROLE"];
		$arrayLinkCssStyles[$key]["TYPE"] = $request["STRING_PUBLIC_PART"][$key]["TYPE"];
		$arrayLinkCssStyles[$key]["STRING_PUBLIC_PART"] = $request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"];
	}

	foreach (($request["STRING_PUBLIC_PART"] ?? []) as $key => $value) {
		if ($arrayLinkCssStyles[$key]) continue;

		$active = 'N';
		if ($request["STRING_PUBLIC_PART"][$key]["ACTIVE"] != null) $active = 'Y';

		// Add
		Tools\GooglePageSpeed\ConnectedCssStyleTable::add([
			"ACTIVE" => $active,
			"ROLE" => $request["STRING_PUBLIC_PART"][$key]["ROLE"],
			"TYPE" => $request["STRING_PUBLIC_PART"][$key]["TYPE"],
			"STRING_PUBLIC_PART" => $request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"],
			"STRING_REGULAR_EXPRESSION" => preg_quote($request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"], '/')
		]);

		$arrayLinkCssStyles[$key]["ID"] = $request["STRING_PUBLIC_PART"][$key]["ID"];
		$arrayLinkCssStyles[$key]["ACTIVE"] = $active;
		$arrayLinkCssStyles[$key]["ROLE"] = $request["STRING_PUBLIC_PART"][$key]["ROLE"];
		$arrayLinkCssStyles[$key]["TYPE"] = $request["STRING_PUBLIC_PART"][$key]["TYPE"];
		$arrayLinkCssStyles[$key]["STRING_PUBLIC_PART"] = $request["STRING_PUBLIC_PART"][$key]["STRING_PUBLIC_PART"];
	}
	// === LinksCssStyles

	// === LinksJsScripts
	foreach ($arrayLinkJsScripts as $key => $value) {
		$active = 'N';
		if ($request["CONNECTED_JS_SCRIPT"][$key]["ACTIVE"] != null) $active = 'Y';

		// Not changed
		if (
			$request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"] === $value["STRING_PUBLIC_PART"] &&
			$request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"] === $value["ATTRIBUTE"] &&
			$active === $value["ACTIVE"]
		) continue;

		// Delete
		if ($request["CONNECTED_JS_SCRIPT"][$key] == null) {
			Tools\GooglePageSpeed\ConnectedJsScriptTable::delete($value['ID']);
			unset($arrayLinkJsScripts[$key]);
			continue;
		}

		// Update
		Tools\GooglePageSpeed\ConnectedJsScriptTable::update($value['ID'], [
			"ACTIVE" => $active,
			"ATTRIBUTE" => $request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"],
			"STRING_PUBLIC_PART" => $request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"],
			"STRING_REGULAR_EXPRESSION" => preg_quote($request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"], '/')
		]);

		$arrayLinkJsScripts[$key]["ACTIVE"] = $active;
		$arrayLinkJsScripts[$key]["ATTRIBUTE"] = $request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"];
		$arrayLinkJsScripts[$key]["STRING_PUBLIC_PART"] = $request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"];
	}

	foreach (($request["CONNECTED_JS_SCRIPT"] ?? []) as $key => $value) {
		if ($arrayLinkJsScripts[$key]) continue;

		$active = 'N';
		if ($request["CONNECTED_JS_SCRIPT"][$key]["ACTIVE"] != null) $active = 'Y';

		// Add
		Tools\GooglePageSpeed\ConnectedJsScriptTable::add([
			"ACTIVE" => $active,
			"ATTRIBUTE" => $request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"],
			"STRING_PUBLIC_PART" => $request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"],
			"STRING_REGULAR_EXPRESSION" => preg_quote($request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"], '/')
		]);

		$arrayLinkJsScripts[$key]["ID"] = $request["CONNECTED_JS_SCRIPT"][$key]["ID"];
		$arrayLinkJsScripts[$key]["ACTIVE"] = $active;
		$arrayLinkJsScripts[$key]["ATTRIBUTE"] = $request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"];
		$arrayLinkJsScripts[$key]["STRING_PUBLIC_PART"] = $request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"];
	}
	// === LinksJsScripts

	if ($request->offsetExists('PSI_API_KEY')) {
		$psiKeyPosted = trim((string)$request->getPost('PSI_API_KEY'));
		if ($psiKeyPosted !== '') {
			Tools\GooglePageSpeed\Psi\Settings::setApiKey($psiKeyPosted);
		}
	}

	Tools\GooglePageSpeed\SettingsProvider::clearCache();
}

$psiProbe = Tools\GooglePageSpeed\Psi\VarianceStorage::buildProbeUrlFromSite();
$psiHasApiKey = Tools\GooglePageSpeed\Psi\Settings::hasApiKey();

$APPLICATION->SetTitle('Настройки');

// Статика: /bitrix/css|js/ (не /local/modules — urlrewrite на проде).
// В админке CAdminPage::ShowCSS читает только GetCSSArray() → нужен SetAdditionalCSS
// (внутри → Asset). Голый Asset::addCss в админский <head> не попадает.
$gpsCssPath = '/bitrix/css/tools.googlepagespeed/style.css';
$gpsJsPath = '/bitrix/js/tools.googlepagespeed/script.js';
$gpsCssFs = $_SERVER['DOCUMENT_ROOT'] . $gpsCssPath;
$gpsJsFs = $_SERVER['DOCUMENT_ROOT'] . $gpsJsPath;
$gpsModuleCss = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/tools.googlepagespeed/css/style.css';
$gpsModuleJs = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/tools.googlepagespeed/js/script.js';
if (is_file($gpsModuleCss) && (!is_file($gpsCssFs) || filemtime($gpsModuleCss) > (int)@filemtime($gpsCssFs))) {
	CheckDirPath(dirname($gpsCssFs) . '/');
	@copy($gpsModuleCss, $gpsCssFs);
}
if (is_file($gpsModuleJs) && (!is_file($gpsJsFs) || filemtime($gpsModuleJs) > (int)@filemtime($gpsJsFs))) {
	CheckDirPath(dirname($gpsJsFs) . '/');
	@copy($gpsModuleJs, $gpsJsFs);
}

$APPLICATION->SetAdditionalCSS($gpsCssPath);
$APPLICATION->AddHeadString(
	'<script>window.ToolsGpsAdminConfig=' . Json::encode([
		'roleLinkCssStyles' => $roleLinkCssStyles,
		'typeLinkCssStyles' => $typeLinkCssStyles,
		'attributeLinkJsScripts' => $attributeLinkJsScripts,
		'sessid' => bitrix_sessid(),
		'publicOrigin' => \Tools\GooglePageSpeed\PageScriptScanner::getPublicOrigin(),
	]) . ';</script>',
	true
);
$APPLICATION->AddHeadScript($gpsJsPath);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

if ($_REQUEST["mess"] == "ok" && $ID > 0)
	CAdminMessage::ShowMessage(["MESSAGE" => "Данные сохранены", "TYPE" => "OK"]);

if ($message)
	echo $message->Show();
elseif ($DB->GetErrorMessage() != "")
	CAdminMessage::ShowMessage($DB->GetErrorMessage()); ?>

<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?php echo ($module_id); ?>&lang=<?= (LANG); ?>" ENCTYPE="multipart/form-data" name="post_form">
	<?= bitrix_sessid_post() ?>
	<?php $tabControl->Begin(); ?>

	<?php $tabControl->BeginNextTab(); ?>
	<?php $randomId = random_int(1, 999); ?>
	<?php
	$optionsByCode = [];
	foreach ($arrayOptions as $keyOption => $valueOption) {
		$code = (string)($valueOption['CODE_OPTION'] ?? '');
		if ($code !== '') {
			$optionsByCode[$code] = ['key' => $keyOption, 'row' => $valueOption];
		}
	}
	$hiddenOptionCodes = Tools\GooglePageSpeed\OptionsDefinitions::getHiddenOptionCodes();

	$renderGpsOptionRow = static function (int|string $keyOption, array $valueOption, array $limitation, bool $nested = false, string $hint = ''): void {
		$code = (string)($valueOption['CODE_OPTION'] ?? '');
		$rowClass = 'tools-gps-filed' . ($nested ? ' tools-gps-filed--nested' : '');
		$hint = trim($hint);
		?>
		<tr class="<?= $rowClass ?>">
			<td class="tools-gps-filed__active">
				<input type="checkbox" name="OPTIONS[<?= $keyOption ?>][ACTIVE]" value="Y" size="60" <?php if (!empty($valueOption['ACTIVE']) && $valueOption['ACTIVE'] == 'Y') echo 'checked' ?>>
				<input type="hidden" name="OPTIONS[<?= $keyOption ?>][CODE_OPTION]" value="<?= htmlspecialcharsbx($code !== '' ? $code : 'GOOGLE_PS_OPTION') ?>" size="60">
			</td>
			<td class="tools-gps-filed__name">
				<?= htmlspecialcharsbx($valueOption['NAME_OPTION']) ?>
				<?php if ($hint !== '') { ?>
					<div class="tools-gps-filed__hint tools-gps-filed__hint--warn"><?= htmlspecialcharsbx($hint) ?></div>
				<?php } ?>
			</td>
			<td class="tools-gps-filed__value">
				<select name="OPTIONS[<?= $keyOption ?>][LIMITATION]">
					<?php foreach ($limitation as $keyLimitation => $valueLimitation) { ?>
						<option value="<?= $keyLimitation ?>" <?php if ($valueOption['LIMITATION'] == $keyLimitation) echo 'selected' ?>><?= $valueLimitation ?></option>
					<?php } ?>
				</select>
			</td>
		</tr>
		<?php
	};

	$renderPanelFromDefinitions = static function (string $panel) use (
		$optionsByCode,
		$hiddenOptionCodes,
		$limitation,
		$renderGpsOptionRow
	): void {
		foreach (Tools\GooglePageSpeed\OptionsDefinitions::forPanel($panel) as $def) {
			$code = (string)($def['CODE_OPTION'] ?? '');
			if ($code === '' || in_array($code, $hiddenOptionCodes, true)) {
				continue;
			}
			if (!empty($def['PARENT'])) {
				continue;
			}
			if (!isset($optionsByCode[$code])) {
				continue;
			}

			$keyOption = $optionsByCode[$code]['key'];
			$valueOption = $optionsByCode[$code]['row'];
			$hint = (string)($def['HINT'] ?? '');

			if (($def['OPTION_TYPE'] ?? '') === 'heading') {
				?>
				<tr class="tools-gps-filed tools-gps-filed--heading">
					<td class="tools-gps-filed__active"></td>
					<td class="tools-gps-filed__name" colspan="2">
						<strong><?= htmlspecialcharsbx($valueOption['NAME_OPTION']) ?></strong>
					</td>
				</tr>
				<?php
				foreach (Tools\GooglePageSpeed\OptionsDefinitions::getChildCodes($code) as $childCode) {
					if (!isset($optionsByCode[$childCode])) {
						continue;
					}
					$childDef = Tools\GooglePageSpeed\OptionsDefinitions::getByCode($childCode);
					$renderGpsOptionRow(
						$optionsByCode[$childCode]['key'],
						$optionsByCode[$childCode]['row'],
						$limitation,
						true,
						(string)($childDef['HINT'] ?? '')
					);
				}
				continue;
			}

			$renderGpsOptionRow($keyOption, $valueOption, $limitation, false, $hint);
		}
	};
	?>
	<tr class="tools-gps-options-layout-row">
		<td colspan="10">
			<div class="tools-gps-options-layout">
				<div class="tools-gps-options-layout__col">
					<div class="tools-gps-options-panel">
						<div class="tools-gps-options-panel__title">Опции</div>
						<table class="tools-gps-options-panel__table">
							<?php $renderPanelFromDefinitions('main'); ?>
						</table>
					</div>
				</div>
				<div class="tools-gps-options-layout__col">
					<div class="tools-gps-options-panel">
						<div class="tools-gps-options-panel__title">Отложить скрипты</div>
						<div class="tools-gps-options-panel__subtitle">Idle или первое взаимодействие — что раньше. Не путать с «Вырезать…».</div>
						<table class="tools-gps-options-panel__table">
							<?php $renderPanelFromDefinitions('defer'); ?>
						</table>
					</div>
				</div>
			</div>
		</td>
	</tr>

	<?php $tabControl->BeginNextTab(); ?>

	<?php if (empty($arrayLinkCssStyles)) :
		$randomId = random_int(1, 999); ?>
		<tr class="tools-gps-filed" data-container="link-css" data-id="1" data-key="0">
			<td class="tools-gps-filed__number">1.</td>
			<td class="tools-gps-filed__active">
				<input type="checkbox" name="STRING_PUBLIC_PART[0][ACTIVE]" value="Y" size="60" id="designed_checkbox_<?= $randomId ?>" class="adm-designed-checkbox">
				<label class="adm-designed-checkbox-label" for="designed_checkbox_<?= $randomId ?>" title=""></label>
			</td>
			<td class="tools-gps-filed__value">
				<input type="hidden" name="STRING_PUBLIC_PART[0][ID]" value="1" size="60">
			</td>
			<td class="tools-gps-filed__text">
				href=
			</td>
			<td class="tools-gps-filed__value">
				<input type="text" name="STRING_PUBLIC_PART[0][STRING_PUBLIC_PART]" value="" size="60">
			</td>
			<td class="tools-gps-filed__text">
				rel=
			</td>
			<td class="tools-gps-filed__value">
				<select name="STRING_PUBLIC_PART[0][ROLE]">
					<?php foreach ($roleLinkCssStyles as $keyRole => $valueRole) { ?>
						<option value="<?= $valueRole ?>"><?= $valueRole ?></option>
					<?php } ?>
				</select>
			</td>
			<td class="tools-gps-filed__text">
				as=
			</td>
			<td class="tools-gps-filed__value">
				<select name="STRING_PUBLIC_PART[0][TYPE]">
					<?php foreach ($typeLinkCssStyles as $keyType => $valueType) { ?>
						<option value="<?= $valueType ?>"><?= $valueType ?></option>
					<?php } ?>
				</select>
			</td>
		</tr>
	<?php else : ?>
		<?php foreach ($arrayLinkCssStyles as $keyLinkCss => $valueLinkCss) {
			$randomId = random_int(1, 999); ?>
			<tr class="tools-gps-filed" data-container="link-css" data-id="<?= $valueLinkCss['ID'] ?>" data-key="<?= $keyLinkCss ?>">
				<td class="tools-gps-filed__number"><?= (int)$keyLinkCss + 1 ?>.</td>
				<td class="tools-gps-filed__active">
					<input type="checkbox" name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][ACTIVE]" value="Y" size="60" id="designed_checkbox_<?= $randomId ?>" class="adm-designed-checkbox" <?php if (!empty($valueLinkCss['ACTIVE']) && $valueLinkCss['ACTIVE'] == 'Y') echo 'checked' ?>>
					<label class="adm-designed-checkbox-label" for="designed_checkbox_<?= $randomId ?>" title=""></label>
				</td>
				<td class="tools-gps-filed__value">
					<input type="hidden" name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][ID]" value="<?= $valueLinkCss['ID'] ?>" size="60">
				</td>
				<td class="tools-gps-filed__text">
					href=
				</td>
				<td class="tools-gps-filed__value">
					<input type="text" name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][STRING_PUBLIC_PART]" value="<?= $valueLinkCss['STRING_PUBLIC_PART'] ?>" size="60">
				</td>
				<td class="tools-gps-filed__text">
					rel=
				</td>
				<td class="tools-gps-filed__value">
					<select name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][ROLE]">
						<?php foreach ($roleLinkCssStyles as $keyRole => $valueRole) { ?>
							<option value="<?= $valueRole ?>" <?php if ($valueLinkCss["ROLE"] == $valueRole) echo 'selected' ?>><?= $valueRole ?></option>
						<?php } ?>
					</select>
				</td>
				<td class="tools-gps-filed__text">
					as=
				</td>
				<td class="tools-gps-filed__value">
					<select name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][TYPE]">
						<?php foreach ($typeLinkCssStyles as $keyType => $valueType) { ?>
							<option value="<?= $valueType ?>" <?php if ($valueLinkCss["TYPE"] == $valueType) echo 'selected' ?>><?= $valueType ?></option>
						<?php } ?>
					</select>
				</td>
				<td class="tools-gps-filed__delete">
					<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
				</td>
			</tr>
		<?php } ?>
	<?php endif; ?>

	<tr>
		<td colspan="10">
			<input type="button" class="tools-gps-filed__add tools-gps-btn adm-btn-save" data-container-button="link-css" value="Добавить url">
		</td>
	</tr>

	<tr class="tools-gps-ref-row">
		<td colspan="10">
			<div class="tools-gps-ref">
				<div class="tools-gps-ref__header">
					<span class="tools-gps-ref__header-icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="9" cy="9" r="9" fill="#3b82f6"/>
							<path d="M9 5v5M9 12.5v.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
						</svg>
					</span>
					<div>
						<div class="tools-gps-ref__title">Справочник: значения атрибута rel</div>
						<div class="tools-gps-ref__subtitle">Выберите подходящее значение rel в зависимости от цели оптимизации загрузки ресурса.</div>
					</div>
				</div>

				<table class="tools-gps-ref__table">
					<thead>
						<tr>
							<th>Значение</th>
							<th>Описание</th>
							<th>Когда использовать</th>
							<th>Подробнее</th>
							<th>Пример</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<span class="tools-gps-ref__badge tools-gps-ref__badge--preload">preload</span>
								<span class="tools-gps-ref__badge-icon tools-gps-ref__badge-icon--preload" aria-hidden="true">
					
								</span>
							</td>
							<td>Задает приоритетную загрузку ресурса. Браузер загружает его как можно скорее и сохраняет в кеше для последующего использования.</td>
							<td>Для критически важных ресурсов, необходимых для отображения контента: шрифты, CSS, hero-изображения, важные JS.</td>
							<td>
								<ul>
									<li>Загружается прямо сейчас.</li>
									<li>Не блокирует парсинг HTML.</li>
									<li>Требует указания атрибута as.</li>
									<li>Используйте только для действительно важных ресурсов.</li>
								</ul>
							</td>
							<td>
								<div class="tools-gps-ref__examples">
									<code class="tools-gps-ref__code">&lt;link rel="preload" href="/local/templates/style.css" as="style"&gt;</code>
									<code class="tools-gps-ref__code">&lt;link rel="preload" href="/local/templates/font.woff2" as="font" type="font/woff2" crossorigin&gt;</code>
									<code class="tools-gps-ref__code">&lt;link rel="preload" href="/img/hero.jpg" as="image"&gt;</code>
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<span class="tools-gps-ref__badge tools-gps-ref__badge--prefetch">prefetch</span>
								<span class="tools-gps-ref__badge-icon tools-gps-ref__badge-icon--prefetch" aria-hidden="true">
								</span>
							</td>
							<td>Указывает браузеру загрузить ресурс в фоновом режиме для возможного будущего перехода.</td>
							<td>Для ресурсов других страниц, на которые пользователь может перейти (например, следующая статья, страница категории и т.д.).</td>
							<td>
								<ul>
									<li>Загружается с низким приоритетом.</li>
									<li>Не используется в текущей навигации.</li>
									<li>Подходит для ссылок, которые, вероятно, понадобятся позже.</li>
								</ul>
							</td>
							<td>
								<div class="tools-gps-ref__examples">
									<code class="tools-gps-ref__code">&lt;link rel="prefetch" href="/js/next-page.js" as="script"&gt;</code>
									<code class="tools-gps-ref__code">&lt;link rel="prefetch" href="/catalog/page-2/" as="document"&gt;</code>
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<span class="tools-gps-ref__badge tools-gps-ref__badge--preconnect">preconnect</span>
								<span class="tools-gps-ref__badge-icon tools-gps-ref__badge-icon--preconnect" aria-hidden="true">
								</span>
							</td>
							<td>Устанавливает раннее соединение с указанным доменом (DNS, TCP, TLS handshake).</td>
							<td>Для внешних доменов (шрифты, API, CDN, аналитика), к которым подключение занимает время.</td>
							<td>
								<ul>
									<li>Ускоряет установление соединения.</li>
									<li>Полезен для сервисов, которые будут запрошены позже.</li>
									<li>Рекомендуется для сторонних сервисов.</li>
								</ul>
							</td>
							<td>
								<div class="tools-gps-ref__examples">
									<code class="tools-gps-ref__code">&lt;link rel="preconnect" href="https://fonts.googleapis.com"&gt;</code>
									<code class="tools-gps-ref__code">&lt;link rel="preconnect" href="https://api.example.com" crossorigin&gt;</code>
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<span class="tools-gps-ref__badge tools-gps-ref__badge--dns-prefetch">dns-prefetch</span>
								<span class="tools-gps-ref__badge-icon tools-gps-ref__badge-icon--dns-prefetch" aria-hidden="true">
								</span>
							</td>
							<td>Выполняет предварительный DNS-запрос к указанному домену.</td>
							<td>Когда важно ускорить только DNS-разрешение, но соединение пока не требуется.</td>
							<td>
								<ul>
									<li>Только DNS-запрос.</li>
									<li>Не устанавливает соединение.</li>
									<li>Легковесный способ ускорить обращение к домену.</li>
								</ul>
							</td>
							<td>
								<div class="tools-gps-ref__examples">
									<code class="tools-gps-ref__code">&lt;link rel="dns-prefetch" href="//example.com"&gt;</code>
									<code class="tools-gps-ref__code">&lt;link rel="dns-prefetch" href="//cdn.example.com"&gt;</code>
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<span class="tools-gps-ref__badge tools-gps-ref__badge--prerender">prerender</span>
								<span class="tools-gps-ref__badge-icon tools-gps-ref__badge-icon--prerender" aria-hidden="true">
								</span>
							</td>
							<td>Загружает и рендерит указанную страницу в фоновом режиме.</td>
							<td>Для страниц, на которые пользователь, скорее всего, перейдет (например, следующая статья, переход по кнопке «Далее»).</td>
							<td>
								<ul>
									<li>Полная загрузка и отрисовка страницы в фоне.</li>
									<li>Может значительно ускорить навигацию.</li>
									<li>Используйте с осторожностью — не для всех страниц.</li>
								</ul>
							</td>
							<td><code class="tools-gps-ref__code">&lt;link rel="prerender" href="/next-page.html"&gt;</code></td>
						</tr>
					</tbody>
				</table>

				<div class="tools-gps-ref__note">
					<div class="tools-gps-ref__note-title">
						<span class="tools-gps-ref__note-icon" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="#3b82f6"/><path d="M8 4.5v4.5M8 11v.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/></svg>
						</span>
						Примечание
					</div>
					<ul>
						<li>Не все браузеры поддерживают все значения rel. Используйте их с умом и тестируйте влияние на производительность.</li>
						<li>Избыточное использование preload может ухудшить производительность (загружает лишнее).</li>
						<li>Для изображений обязательно указывайте as="image", для шрифтов — as="font" и type + crossorigin.</li>
					</ul>
				</div>
			</div>
		</td>
	</tr>

	<?php $tabControl->BeginNextTab(); ?>

	<tr class="tools-gps-script-layout-row">
		<td colspan="10">
			<div class="tools-gps-script-layout">
				<div class="tools-gps-script-layout__col tools-gps-script-layout__col--scan">
					<div class="tools-gps-scan" id="tools-gps-script-scan">
						<div class="tools-gps-scan__head">
							<div class="tools-gps-scan__title">Сканирование страницы</div>
						</div>
						<div class="tools-gps-scan__presets" id="tools-gps-scan-presets">
							<?php foreach (Tools\GooglePageSpeed\ScriptScanCatalog::getScanUrlPresets() as $scanPreset) { ?>
								<button
									type="button"
									class="tools-gps-scan__preset-btn"
									data-scan-path="<?= htmlspecialcharsbx($scanPreset['path']) ?>"
								><?= $scanPreset['label'] ?></button>
							<?php } ?>
						</div>
						<div class="tools-gps-scan__hint">
							Несколько URL — через запятую, точку с запятой или с новой строки. Максимум 5. Пример:<br>
							<code>https://example.com/, https://example.com/catalog/</code>
						</div>
						<div class="tools-gps-scan__form">
							<textarea
								class="tools-gps-scan__url"
								id="tools-gps-scan-url"
								rows="2"
								placeholder="https://example.com/"
							></textarea>
							<input type="button" class="tools-gps-btn adm-btn-save" id="tools-gps-scan-run" value="Сканировать">
						</div>
						<div class="tools-gps-scan__stats" id="tools-gps-scan-stats" hidden></div>
						<div class="tools-gps-scan__error" id="tools-gps-scan-error" hidden></div>
						<div class="tools-gps-scan__warn" id="tools-gps-scan-warn" hidden></div>
						<div class="tools-gps-scan__list" id="tools-gps-scan-list"></div>
					</div>
				</div>

				<div class="tools-gps-script-layout__col tools-gps-script-layout__col--rules">
					<div class="tools-gps-js-rules">
						<div class="tools-gps-js-rules__head">
							<div class="tools-gps-js-rules__title">Правила async / defer</div>
							<div class="tools-gps-js-rules__subtitle">Список скриптов, которым модуль назначит атрибут на публичных страницах.</div>
						</div>
						<table class="tools-gps-js-rules__table">
							<tbody id="tools-gps-js-rules-body">
							<?php if (empty($arrayLinkJsScripts)) :
								$randomId = random_int(1, 999); ?>
								<tr class="tools-gps-filed" data-container="link-js" data-id="1" data-key="0">
									<td class="tools-gps-filed__number">1.</td>
									<td class="tools-gps-filed__active">
										<input type="checkbox" name="CONNECTED_JS_SCRIPT[0][ACTIVE]" value="Y" size="60" id="designed_checkbox_<?= $randomId ?>" class="adm-designed-checkbox">
										<label class="adm-designed-checkbox-label" for="designed_checkbox_<?= $randomId ?>" title=""></label>
									</td>
									<td class="tools-gps-filed__value">
										<input type="hidden" name="CONNECTED_JS_SCRIPT[0][ID]" value="1" size="60">
									</td>
									<td class="tools-gps-filed__value">
										<select name="CONNECTED_JS_SCRIPT[0][ATTRIBUTE]">
											<?php foreach ($attributeLinkJsScripts as $keyAttribute => $valueAttribute) { ?>
												<option value="<?= $valueAttribute ?>"><?= $valueAttribute ?></option>
											<?php } ?>
										</select>
									</td>
									<td class="tools-gps-filed__text">
										src=
									</td>
									<td class="tools-gps-filed__value tools-gps-filed__value--src">
										<input type="text" name="CONNECTED_JS_SCRIPT[0][STRING_PUBLIC_PART]" value="" size="40">
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ($arrayLinkJsScripts as $keyLinkJs => $valueLinkJs) {
									$randomId = random_int(1, 999); ?>
									<tr class="tools-gps-filed" data-container="link-js" data-id="<?= $valueLinkJs['ID'] ?>" data-key="<?= $keyLinkJs ?>">
										<td class="tools-gps-filed__number"><?= (int)$keyLinkJs + 1 ?>.</td>
										<td class="tools-gps-filed__active">
											<input type="checkbox" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][ACTIVE]" value="Y" size="60" id="designed_checkbox_<?= $randomId ?>" class="adm-designed-checkbox" <?php if (!empty($valueLinkJs['ACTIVE']) && $valueLinkJs['ACTIVE'] == 'Y') echo 'checked' ?>>
											<label class="adm-designed-checkbox-label" for="designed_checkbox_<?= $randomId ?>" title=""></label>
										</td>
										<td class="tools-gps-filed__value">
											<input type="hidden" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][ID]" value="<?= $valueLinkJs['ID'] ?>" size="60">
										</td>
										<td class="tools-gps-filed__value">
											<select name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][ATTRIBUTE]">
												<?php foreach ($attributeLinkJsScripts as $keyAttribute => $valueAttribute) { ?>
													<option value="<?= $valueAttribute ?>" <?php if ($valueLinkJs["ATTRIBUTE"] == $valueAttribute) echo 'selected' ?>><?= $valueAttribute ?></option>
												<?php } ?>
											</select>
										</td>
										<td class="tools-gps-filed__text">
											src=
										</td>
										<td class="tools-gps-filed__value tools-gps-filed__value--src">
											<input type="text" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][STRING_PUBLIC_PART]" value="<?= $valueLinkJs['STRING_PUBLIC_PART'] ?>" size="40">
										</td>
										<td class="tools-gps-filed__delete">
											<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
										</td>
									</tr>
								<?php } ?>
							<?php endif; ?>
							</tbody>
						</table>
						<div class="tools-gps-js-rules__actions">
							<input type="button" class="tools-gps-filed__add tools-gps-btn adm-btn-save" data-container-button="link-js" value="Добавить url">
						</div>
					</div>
				</div>
			</div>
		</td>
	</tr>

	<tr class="tools-gps-ref-row">
		<td colspan="10">
			<div class="tools-gps-ref tools-gps-script-ref">
				<div class="tools-gps-ref__header">
					<span class="tools-gps-ref__header-icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="9" cy="9" r="9" fill="#94a3b8"/>
							<path d="M9 5.5a1.2 1.2 0 100 2.4A1.2 1.2 0 009 5.5z" fill="#fff"/>
							<path d="M9 9.5v3.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
						</svg>
					</span>
					<div>
						<div class="tools-gps-ref__title">Описание атрибутов defer и async</div>
						<div class="tools-gps-ref__subtitle">Эти атрибуты определяют, как и когда браузер загружает и выполняет внешний JavaScript.</div>
					</div>
				</div>

				<div class="tools-gps-script-ref__grid">
					<div class="tools-gps-script-ref__card tools-gps-script-ref__card--defer">
						<div class="tools-gps-script-ref__card-head">
							<span class="tools-gps-script-ref__attr tools-gps-script-ref__attr--defer">defer</span>
							<span class="tools-gps-script-ref__card-icon tools-gps-script-ref__card-icon--defer" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm-.5 3v4.5l3.5 2-.8 1.2L6 9.2V4h1.5z"/></svg>
							</span>
							<span class="tools-gps-script-ref__card-label">Отложенное выполнение</span>
						</div>
						<p class="tools-gps-script-ref__text">Скрипт загружается параллельно с HTML, но выполняется только после <strong>полного разбора документа</strong>, перед событием DOMContentLoaded.</p>
						<div class="tools-gps-script-ref__section-title">Когда использовать</div>
						<ul class="tools-gps-script-ref__list">
							<li>Когда порядок выполнения скриптов важен.</li>
							<li>Когда скрипт зависит от DOM-элементов на странице.</li>
							<li>Для большинства скриптов на странице.</li>
						</ul>
						<div class="tools-gps-script-ref__hint tools-gps-script-ref__hint--defer">
							<span class="tools-gps-script-ref__hint-icon" aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="7" fill="#3b82f6"/><path d="M7 4v3.5M7 9v.5" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/></svg>
							</span>
							Скрипты с defer сохраняют порядок выполнения.
						</div>
					</div>

					<div class="tools-gps-script-ref__card tools-gps-script-ref__card--async">
						<div class="tools-gps-script-ref__card-head">
							<span class="tools-gps-script-ref__attr tools-gps-script-ref__attr--async">async</span>
							<span class="tools-gps-script-ref__card-icon tools-gps-script-ref__card-icon--async" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M9.5 1.5L4 9h3.5L6.5 14.5 12 7H8.5L9.5 1.5z"/></svg>
							</span>
							<span class="tools-gps-script-ref__card-label">Немедленное выполнение</span>
						</div>
						<p class="tools-gps-script-ref__text">Скрипт загружается параллельно с HTML и выполняется сразу после загрузки, не дожидаясь разбора документа.</p>
						<div class="tools-gps-script-ref__section-title">Когда использовать</div>
						<ul class="tools-gps-script-ref__list">
							<li>Для независимых скриптов (например, счётчики, виджеты).</li>
							<li>Когда порядок выполнения не важен.</li>
							<li>Когда скрипт не зависит от DOM-элементов.</li>
						</ul>
						<div class="tools-gps-script-ref__hint tools-gps-script-ref__hint--async">
							<span class="tools-gps-script-ref__hint-icon" aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="7" fill="#7c3aed"/><path d="M7 4v3.5M7 9v.5" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/></svg>
							</span>
							Порядок выполнения скриптов с async не гарантируется.
						</div>
					</div>
				</div>

				<div class="tools-gps-script-ref__warning">
					<span class="tools-gps-script-ref__warning-icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 1.5a5.5 5.5 0 00-2.2 10.6V14h4.4v-1.9A5.5 5.5 0 009 1.5z" fill="#f59e0b"/><path d="M7 15.5h4M8 17h2" stroke="#d97706" stroke-width="1.2" stroke-linecap="round"/></svg>
					</span>
					<strong>Важно:</strong> Не используйте async и defer вместе — выберите только один вариант для каждого скрипта.
				</div>
			</div>
		</td>
	</tr>

	<?php $tabControl->BeginNextTab(); ?>

	<tr>
		<td colspan="10">
			<div class="tools-gps-psi" id="tools-gps-psi">
				<div class="tools-gps-psi__panel">
					<div class="tools-gps-psi__title">Замер разброса lab Performance</div>
					<div class="tools-gps-psi__subtitle">Серия прогонов PageSpeed Insights API. Сравнивайте «до/после» по медиане.</div>

					<div class="tools-gps-psi__row">
						<label class="tools-gps-psi__label" for="tools-gps-psi-url">URL прогона</label>
						<div class="tools-gps-psi__url-row">
							<input
								type="url"
								class="tools-gps-psi__input"
								id="tools-gps-psi-url"
								value="<?= !empty($psiProbe['ok']) && !empty($psiProbe['url']) ? htmlspecialcharsbx($psiProbe['url']) : '' ?>"
								placeholder="https://ideal-mf.ru/"
								autocomplete="off"
							>
							<input type="button" class="adm-btn" id="tools-gps-psi-url-from-site" value="Подставить домен сайта" title="Взять URL из настроек текущего сайта Bitrix">
						</div>
						<?php if (empty($psiProbe['ok'])) { ?>
							<div class="tools-gps-psi__hint tools-gps-psi__hint--warn"><?= htmlspecialcharsbx((string)($psiProbe['error'] ?? 'Домен сайта не определён — введите URL вручную.')) ?></div>
						<?php } ?>
					</div>

					<div class="tools-gps-psi__row">
						<label class="tools-gps-psi__label" for="tools-gps-psi-api-key">Ключ API PSI</label>
						<div class="tools-gps-psi__key-row">
							<input
								type="password"
								class="tools-gps-psi__input"
								id="tools-gps-psi-api-key"
								name="PSI_API_KEY"
								value=""
								autocomplete="off"
								placeholder="<?= $psiHasApiKey ? '•••••••• (ключ сохранён — введите новый, чтобы заменить)' : 'Вставьте ключ Google PageSpeed Insights API' ?>"
							>
							<input type="button" class="tools-gps-btn adm-btn-save" id="tools-gps-psi-save-key" value="Сохранить ключ">
							<span class="tools-gps-psi__key-status" id="tools-gps-psi-key-status" <?= $psiHasApiKey ? '' : 'hidden' ?>>ключ есть</span>
						</div>
					</div>

					<div class="tools-gps-psi__row tools-gps-psi__row--inline">
						<div class="tools-gps-psi__field">
							<label class="tools-gps-psi__label" for="tools-gps-psi-n">Число прогонов (N)</label>
							<input type="number" class="tools-gps-psi__input tools-gps-psi__input--n" id="tools-gps-psi-n" min="1" max="20" value="5">
							<div class="tools-gps-psi__hint">Эмпирически 5 прогонов достаточно для более-менее реалистичной картины разброса.</div>
						</div>
						<div class="tools-gps-psi__field">
							<span class="tools-gps-psi__label">Устройства</span>
							<label class="tools-gps-psi__check"><input type="checkbox" id="tools-gps-psi-mobile" checked> Мобильные</label>
							<label class="tools-gps-psi__check"><input type="checkbox" id="tools-gps-psi-desktop" checked> Компьютер</label>
						</div>
					</div>

					<div class="tools-gps-psi__actions">
						<input type="button" class="tools-gps-btn adm-btn-save" id="tools-gps-psi-start" value="Старт">
						<input type="button" class="adm-btn" id="tools-gps-psi-stop" value="Стоп" disabled>
					</div>

					<div class="tools-gps-psi__progress" id="tools-gps-psi-progress" hidden>
						<div class="tools-gps-psi__progress-bar"><div class="tools-gps-psi__progress-fill" id="tools-gps-psi-progress-fill"></div></div>
						<div class="tools-gps-psi__progress-text" id="tools-gps-psi-progress-text">0 / 0</div>
					</div>
					<div class="tools-gps-psi__log" id="tools-gps-psi-log" hidden></div>
					<div class="tools-gps-psi__error" id="tools-gps-psi-error" hidden></div>
				</div>

				<div class="tools-gps-psi__panel tools-gps-psi__panel--history">
					<div class="tools-gps-psi__title">Результаты серий</div>
					<div class="tools-gps-psi__history-controls">
						<select class="tools-gps-psi__select" id="tools-gps-psi-runs" aria-label="Серия прогонов">
							<option value="">— нет сохранённых серий —</option>
						</select>
						<input type="button" class="adm-btn" id="tools-gps-psi-refresh" value="Обновить список">
						<input type="button" class="adm-btn adm-btn-delete" id="tools-gps-psi-delete" value="Удалить серию" disabled>
					</div>
					<div class="tools-gps-psi__report" id="tools-gps-psi-report">
						<p class="tools-gps-psi-report__empty">Выберите серию в списке или запустите новый замер.</p>
					</div>
				</div>
			</div>
						</td>
	</tr>

	<?php $tabControl->Buttons(); ?>
	<input class="tools-gps-btn adm-btn-save" type="submit" name="Update" value="Применить" />
	<input type="hidden" name="lang" value="<?= LANG ?>">
</form>

<?php $tabControl->End(); ?>
<?php $tabControl->ShowWarnings("post_form", $message); ?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
