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

// Пресеты отложенной загрузки для уже установленных модулей
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

	Tools\GooglePageSpeed\SettingsProvider::clearCache();
}


$APPLICATION->SetTitle('Настройки');

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
	<?php foreach ($arrayOptions as $keyOption => $valueOption) { ?>
		<tr class="tools-gps-filed">
			<td class="tools-gps-filed__active">
				<input type="checkbox" name="OPTIONS[<?= $keyOption ?>][ACTIVE]" value="Y" size="60" <?php if (!empty($valueOption['ACTIVE']) && $valueOption['ACTIVE'] == 'Y') echo 'checked' ?>>
				<input type="hidden" name="OPTIONS[<?= $keyOption ?>][CODE_OPTION]" value="GOOGLE_PS_OPTION" size="60">
			</td>
			<td class="tools-gps-filed__name">
				<?= $valueOption["NAME_OPTION"] ?>
			</td>
			<td class="tools-gps-filed__value">
				<select name="OPTIONS[<?= $keyOption ?>][LIMITATION]">
					<?php foreach ($limitation as $keyLimitation => $valueLimitation) { ?>
						<option value="<?= $keyLimitation ?>" <?php if ($valueOption["LIMITATION"] == $keyLimitation) echo 'selected' ?>><?= $valueLimitation ?></option>
					<?php } ?>
				</select>
			</td>
		</tr>
	<?php } ?>

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
							<div class="tools-gps-scan__subtitle">HTTP-запрос публичной страницы → список &lt;script src&gt; без ядра Bitrix и без Метрики/GA. Скрипты из пресетов добавляются в правила автоматически.</div>
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

	<?php $tabControl->Buttons(); ?>
	<input class="tools-gps-btn adm-btn-save" type="submit" name="Update" value="Применить" />
	<input type="hidden" name="lang" value="<?= LANG ?>">
</form>

<?php $tabControl->End(); ?>
<?php $tabControl->ShowWarnings("post_form", $message); ?>

<!--  JS Scripts  -->
<script>
	let dataContainer = '',
		listFileds = 0,
		lastElementContainer = '',
		idNextElement,
		keyNextElement,
		randomNumber = '',
		templateField = '',
		roleLinkCssStyles = <?= \Bitrix\Main\Web\Json::encode($roleLinkCssStyles); ?>,
		typeLinkCssStyles = <?= \Bitrix\Main\Web\Json::encode($typeLinkCssStyles); ?>,
		attributeLinkJsScripts = <?= \Bitrix\Main\Web\Json::encode($attributeLinkJsScripts); ?>,
		gpsScanSessid = <?= \Bitrix\Main\Web\Json::encode(bitrix_sessid()); ?>;

	(function initScanUrlDefault() {
		let input = document.getElementById('tools-gps-scan-url');
		if (input && !(input.value || '').trim()) {
			input.value = window.location.origin + '/';
		}
	})();

	function gpsSiteOrigin() {
		// Админка часто на том же хосте, что и сайт; origin подходит для пресетов путей.
		return window.location.origin;
	}

	function gpsResolveScanPath(path) {
		path = path || '/';
		if (!path.startsWith('/')) {
			path = '/' + path;
		}
		return gpsSiteOrigin() + path;
	}

	function gpsAppendScanUrl(url) {
		let input = document.getElementById('tools-gps-scan-url');
		if (!input || !url) {
			return;
		}
		let current = (input.value || '').trim();
		if (!current) {
			input.value = url;
			return;
		}
		let parts = current.split(/[\n\r,;]+/).map((p) => p.trim()).filter(Boolean);
		let needle = url.toLowerCase();
		let exists = parts.some((p) => p.toLowerCase() === needle);
		if (exists) {
			return;
		}
		input.value = current + '\n' + url;
	}

	document.getElementById('tools-gps-scan-presets')?.addEventListener('click', (event) => {
		let btn = event.target.closest('.tools-gps-scan__preset-btn');
		if (!btn) {
			return;
		}
		gpsAppendScanUrl(gpsResolveScanPath(btn.getAttribute('data-scan-path') || '/'));
	});

	function gpsCollectExistingJsParts() {
		let parts = [];
		document.querySelectorAll('[data-container="link-js"] input[name*="[STRING_PUBLIC_PART]"]').forEach((el) => {
			let v = (el.value || '').trim();
			if (v) {
				parts.push(v);
			}
		});
		return parts;
	}

	function gpsIsJsPartAlreadyInForm(publicPart) {
		let needle = (publicPart || '').toLowerCase();
		if (!needle) {
			return false;
		}
		let found = false;
		document.querySelectorAll('[data-container="link-js"] input[name*="[STRING_PUBLIC_PART]"]').forEach((el) => {
			let v = (el.value || '').trim().toLowerCase();
			if (v && (v === needle || v.indexOf(needle) !== -1 || needle.indexOf(v) !== -1)) {
				found = true;
			}
		});
		return found;
	}

	function gpsFillOrAddJsRule(publicPart, attribute) {
		publicPart = (publicPart || '').trim();
		attribute = attribute === 'async' ? 'async' : 'defer';
		if (!publicPart || gpsIsJsPartAlreadyInForm(publicPart)) {
			return false;
		}

		let rows = document.querySelectorAll('[data-container="link-js"]');
		let emptyRow = null;
		rows.forEach((row) => {
			let input = row.querySelector('input[name*="[STRING_PUBLIC_PART]"]');
			if (input && !(input.value || '').trim() && !emptyRow) {
				emptyRow = row;
			}
		});

		if (emptyRow) {
			let input = emptyRow.querySelector('input[name*="[STRING_PUBLIC_PART]"]');
			let select = emptyRow.querySelector('select[name*="[ATTRIBUTE]"]');
			let checkbox = emptyRow.querySelector('input[type="checkbox"][name*="[ACTIVE]"]');
			if (input) {
				input.value = publicPart;
			}
			if (select) {
				select.value = attribute;
			}
			if (checkbox) {
				checkbox.checked = true;
			}
			return true;
		}

		let addBtn = document.querySelector('[data-container-button="link-js"]');
		if (addBtn) {
			addBtn.click();
			rows = document.querySelectorAll('[data-container="link-js"]');
			let last = rows[rows.length - 1];
			if (last) {
				let input = last.querySelector('input[name*="[STRING_PUBLIC_PART]"]');
				let select = last.querySelector('select[name*="[ATTRIBUTE]"]');
				let checkbox = last.querySelector('input[type="checkbox"][name*="[ACTIVE]"]');
				if (input) {
					input.value = publicPart;
				}
				if (select) {
					select.value = attribute;
				}
				if (checkbox) {
					checkbox.checked = true;
				}
				return true;
			}
		}
		return false;
	}

	function gpsEscapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function gpsRenderScanResults(data) {
		let statsEl = document.getElementById('tools-gps-scan-stats');
		let listEl = document.getElementById('tools-gps-scan-list');
		let errorEl = document.getElementById('tools-gps-scan-error');
		let warnEl = document.getElementById('tools-gps-scan-warn');
		if (!statsEl || !listEl || !errorEl) {
			return;
		}

		errorEl.hidden = true;
		errorEl.textContent = '';
		if (warnEl) {
			if (data.error) {
				warnEl.hidden = false;
				warnEl.textContent = data.error;
			} else {
				warnEl.hidden = true;
				warnEl.textContent = '';
			}
		}

		let s = data.stats || {};
		let scannedN = (data.scannedUrls && data.scannedUrls.length) ? data.scannedUrls.length : 0;
		statsEl.hidden = false;
		statsEl.innerHTML =
			'Страниц: <strong>' + scannedN + '</strong>' +
			' · найдено: <strong>' + (s.found || 0) + '</strong>' +
			' · в списке: <strong>' + (s.shown || 0) + '</strong>' +
			' · скрыто ядро: <strong>' + (s.hiddenCore || 0) + '</strong>' +
			' · скрыто аналитика: <strong>' + (s.hiddenAnalytics || 0) + '</strong>' +
			' · уже async/defer: <strong>' + (s.hiddenNonBlocking || 0) + '</strong>' +
			' · пресеты: <strong>' + (s.presetMatched || 0) + '</strong>' +
			' · уже в правилах: <strong>' + (s.alreadyInRules || 0) + '</strong>';

		listEl.innerHTML = '';
		let scripts = data.scripts || [];
		if (!scripts.length) {
			listEl.innerHTML = '<div class="tools-gps-scan__empty">После фильтров подходящих скриптов нет.</div>';
			return;
		}

		scripts.forEach((item) => {
			let row = document.createElement('div');
			row.className = 'tools-gps-scan__item';
			if (item.preset) {
				row.classList.add('tools-gps-scan__item--known');
				row.style.setProperty('--gps-preset-color', item.preset.color || '#0ea5e9');
			}
			if (item.alreadyInRules) {
				row.classList.add('tools-gps-scan__item--added');
			}

			let badge = '';
			if (item.preset) {
				badge = '<span class="tools-gps-scan__badge">' + gpsEscapeHtml(item.preset.label) + '</span>';
			}

			let status = item.alreadyInRules
				? '<span class="tools-gps-scan__status">уже в правилах</span>'
				: '';

			let btnLabel = item.preset
				? 'Добавить (' + gpsEscapeHtml(item.attribute || 'defer') + ')'
				: 'Добавить';
			let btnDisabled = item.alreadyInRules ? ' disabled' : '';

			row.innerHTML =
				'<div class="tools-gps-scan__item-main">' +
					badge +
					'<code class="tools-gps-scan__src" title="' + gpsEscapeHtml(item.src || '') + '">' + gpsEscapeHtml(item.publicPart || '') + '</code>' +
					status +
				'</div>' +
				'<button type="button" class="tools-gps-scan__add-btn adm-btn"' + btnDisabled +
					' data-public-part="' + gpsEscapeHtml(item.publicPart || '') + '"' +
					' data-attribute="' + gpsEscapeHtml(item.attribute || 'defer') + '">' +
					btnLabel +
				'</button>';

			listEl.appendChild(row);
		});
	}

	document.getElementById('tools-gps-scan-run')?.addEventListener('click', () => {
		let urlInput = document.getElementById('tools-gps-scan-url');
		let errorEl = document.getElementById('tools-gps-scan-error');
		let warnEl = document.getElementById('tools-gps-scan-warn');
		let btn = document.getElementById('tools-gps-scan-run');
		let pageUrl = (urlInput?.value || '').trim();
		if (!pageUrl) {
			if (errorEl) {
				errorEl.hidden = false;
				errorEl.textContent = 'Укажите URL страницы.';
			}
			if (warnEl) {
				warnEl.hidden = true;
			}
			return;
		}

		btn.disabled = true;
		btn.value = 'Сканирование…';

		let body = new FormData();
		body.append('action', 'gps_scan_scripts');
		body.append('sessid', gpsScanSessid);
		body.append('page_url', pageUrl);
		gpsCollectExistingJsParts().forEach((part) => {
			body.append('existing[]', part);
		});

		fetch(window.location.href, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		})
			.then((r) => r.json())
			.then((data) => {
				if (!data || !data.ok) {
					if (errorEl) {
						errorEl.hidden = false;
						errorEl.textContent = (data && data.error) ? data.error : 'Ошибка сканирования.';
					}
					if (warnEl) {
						warnEl.hidden = true;
					}
					document.getElementById('tools-gps-scan-stats').hidden = true;
					document.getElementById('tools-gps-scan-list').innerHTML = '';
					return;
				}

				gpsRenderScanResults(data);

				(data.scripts || []).forEach((item) => {
					if (item.autoAdd) {
						gpsFillOrAddJsRule(item.publicPart, item.attribute);
					}
				});

				gpsRenderScanResults({
					ok: true,
					error: data.error,
					stats: data.stats,
					scannedUrls: data.scannedUrls,
					scripts: (data.scripts || []).map((item) => {
						let copy = Object.assign({}, item);
						if (gpsIsJsPartAlreadyInForm(copy.publicPart)) {
							copy.alreadyInRules = true;
							copy.autoAdd = false;
						}
						return copy;
					}),
				});
			})
			.catch(() => {
				if (errorEl) {
					errorEl.hidden = false;
					errorEl.textContent = 'Сбой запроса к админке.';
				}
			})
			.finally(() => {
				btn.disabled = false;
				btn.value = 'Сканировать';
			});
	});

	document.getElementById('tools-gps-scan-list')?.addEventListener('click', (event) => {
		let btn = event.target.closest('.tools-gps-scan__add-btn');
		if (!btn || btn.disabled) {
			return;
		}
		let publicPart = btn.getAttribute('data-public-part') || '';
		let attribute = btn.getAttribute('data-attribute') || 'defer';
		if (gpsFillOrAddJsRule(publicPart, attribute)) {
			btn.disabled = true;
			btn.textContent = 'Добавлено';
			let item = btn.closest('.tools-gps-scan__item');
			if (item) {
				item.classList.add('tools-gps-scan__item--added');
				let main = item.querySelector('.tools-gps-scan__item-main');
				if (main && !main.querySelector('.tools-gps-scan__status')) {
					main.insertAdjacentHTML('beforeend', '<span class="tools-gps-scan__status">уже в правилах</span>');
				}
			}
		}
	});

	document.addEventListener('click', (event) => {
		if (event.target.classList.contains('tools-gps-filed__add')) {
			randomNumber = Math.random();
			dataContainer = event.target.dataset.containerButton;
			listFileds = document.querySelectorAll('[data-container=' + dataContainer + ']');
			lastElementContainer = listFileds[listFileds.length - 1];
			idNextElement = Number(lastElementContainer.dataset.id) + 1;
			keyNextElement = Number(lastElementContainer.dataset.key) + 1;
			if (dataContainer == 'link-css') {
				templateField =
					`<tr class="tools-gps-filed" data-container="` + dataContainer + `" data-id="` + idNextElement + `" data-key="` + keyNextElement + `">
						<td class="tools-gps-filed__number">` + (listFileds.length + 1) + `.</td>
						<td class="tools-gps-filed__active">
							<input type="checkbox" name="STRING_PUBLIC_PART[` + keyNextElement + `][ACTIVE]" value="Y" size="60" id="designed_checkbox_` + randomNumber + `" class="adm-designed-checkbox">
							<label class="adm-designed-checkbox-label" for="designed_checkbox_` + randomNumber + `" title=""></label>
						</td>
						<td class="tools-gps-filed__value">
							<input type="hidden" name="STRING_PUBLIC_PART[` + keyNextElement + `][ID]" value="` + idNextElement + `" size="60">
						</td>
						<td class="tools-gps-filed__text">
							href=
						</td>
						<td class="tools-gps-filed__value">
							<input type="text" name="STRING_PUBLIC_PART[` + keyNextElement + `][STRING_PUBLIC_PART]" value="" size="60">
						</td>
						<td class="tools-gps-filed__text">
							rel=
						</td>
						<td class="tools-gps-filed__value">
							<select  name="STRING_PUBLIC_PART[` + keyNextElement + `][ROLE]">`;

				roleLinkCssStyles.forEach((valueRole, keyRole, array) => {
					templateField += `<option value="${valueRole}">${valueRole}</option>`
				});

				templateField +=
					`</select>
						</td>
						<td class="tools-gps-filed__text">
							as=
						</td>
						<td class="tools-gps-filed__value">
							<select  name="STRING_PUBLIC_PART[` + keyNextElement + `][TYPE]">`;

				typeLinkCssStyles.forEach((valueType, keyType, array) => {
					templateField += `<option value="${valueType}">${valueType}</option>`
				});

				templateField +=
					`</select>
						</td>
						<td class="tools-gps-filed__delete">
							<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
						</td>
					</tr>`;
			} else if (dataContainer == 'link-js') {
				templateField =
					`<tr class="tools-gps-filed" data-container="` + dataContainer + `" data-id="` + idNextElement + `" data-key="` + keyNextElement + `">
						<td class="tools-gps-filed__number">` + (listFileds.length + 1) + `.</td>
						<td class="tools-gps-filed__active">
							<input type="checkbox" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ACTIVE]" value="Y" size="60" id="designed_checkbox_` + randomNumber + `" class="adm-designed-checkbox">
							<label class="adm-designed-checkbox-label" for="designed_checkbox_` + randomNumber + `" title=""></label>
						</td>
						<td class="tools-gps-filed__value">
							<input type="hidden" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ID]" value="` + idNextElement + `" size="60">
						</td>
						<td class="tools-gps-filed__value">
							<select  name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ATTRIBUTE]">`;

				attributeLinkJsScripts.forEach((valueAttribute) => {
					templateField += `<option value="${valueAttribute}">${valueAttribute}</option>`
				});

				templateField += `</select>
						</td>
						<td class="tools-gps-filed__text">
							src=
						</td>
						<td class="tools-gps-filed__value tools-gps-filed__value--src">
							<input type="text" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][STRING_PUBLIC_PART]" value="" size="40">
						</td>
						<td class="tools-gps-filed__delete">
							<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
						</td>
					</tr>`;
			}
			lastElementContainer.insertAdjacentHTML('afterend', templateField);
		}

		if (event.target.classList.contains('tools-gps-filed__delete-field')) {
			event.target.closest('.tools-gps-filed').remove();
		}
	})
</script>
<style>
	.tools-gps-script-layout-row > td {
		padding: 0 !important;
		border: none !important;
	}

	.tools-gps-script-layout {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
		margin: 8px 0 12px;
		align-items: stretch;
	}

	.tools-gps-script-layout__col {
		min-width: 0;
	}

	.tools-gps-scan {
		height: 100%;
		margin: 0;
		padding: 16px 18px 18px;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		box-sizing: border-box;
	}

	.tools-gps-js-rules {
		height: 100%;
		margin: 0;
		padding: 16px 18px 18px;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		box-sizing: border-box;
	}

	.tools-gps-js-rules__title {
		font-size: 15px;
		font-weight: 600;
		color: #1e293b;
		margin-bottom: 4px;
	}

	.tools-gps-js-rules__subtitle {
		font-size: 12px;
		color: #64748b;
		margin-bottom: 12px;
		line-height: 1.45;
	}

	.tools-gps-js-rules__table {
		width: 100%;
		border-collapse: collapse;
	}

	.tools-gps-js-rules__table .tools-gps-filed {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		width: 100%;
		margin-bottom: 8px;
		padding: 6px 0;
		border-bottom: 1px solid #f1f5f9;
	}

	.tools-gps-js-rules__table .tools-gps-filed > td {
		margin-right: 8px;
		padding: 0;
		border: none;
		background: transparent;
	}

	.tools-gps-js-rules__table .tools-gps-filed__value--src {
		flex: 1 1 140px;
		min-width: 0;
	}

	.tools-gps-js-rules__table .tools-gps-filed__value--src input[type="text"] {
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
	}

	.tools-gps-js-rules__actions {
		margin-top: 10px;
	}

	.tools-gps-scan__title {
		font-size: 15px;
		font-weight: 600;
		color: #1e293b;
		margin-bottom: 4px;
	}

	.tools-gps-scan__subtitle {
		font-size: 12px;
		color: #64748b;
		margin-bottom: 10px;
		line-height: 1.45;
	}

	.tools-gps-scan__presets {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		margin-bottom: 8px;
	}

	.tools-gps-scan__preset-btn {
		appearance: none;
		border: 1px solid #cbd5e1;
		background: #f8fafc;
		color: #334155;
		border-radius: 999px;
		padding: 4px 10px;
		font-size: 12px;
		line-height: 1.2;
		cursor: pointer;
	}

	.tools-gps-scan__preset-btn:hover {
		border-color: #94a3b8;
		background: #f1f5f9;
	}

	.tools-gps-scan__hint {
		font-size: 11px;
		color: #64748b;
		line-height: 1.45;
		margin-bottom: 10px;
	}

	.tools-gps-scan__hint code {
		font-size: 11px;
		color: #0f172a;
		background: #f1f5f9;
		padding: 1px 4px;
		border-radius: 3px;
	}

	.tools-gps-scan__form {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		align-items: flex-start;
		margin-bottom: 10px;
	}

	.tools-gps-scan__url {
		flex: 1 1 160px;
		min-width: 120px;
		max-width: 100%;
		min-height: 54px;
		padding: 8px 10px;
		border: 1px solid #c6cdd3;
		border-radius: 4px;
		box-sizing: border-box;
		resize: vertical;
		font-family: inherit;
		font-size: 13px;
		line-height: 1.4;
	}

	.tools-gps-scan__stats {
		font-size: 12px;
		color: #475569;
		margin-bottom: 10px;
		line-height: 1.5;
	}

	.tools-gps-scan__error {
		font-size: 12px;
		color: #b91c1c;
		background: #fef2f2;
		border: 1px solid #fecaca;
		border-radius: 4px;
		padding: 8px 10px;
		margin-bottom: 10px;
	}

	.tools-gps-scan__warn {
		font-size: 12px;
		color: #92400e;
		background: #fffbeb;
		border: 1px solid #fde68a;
		border-radius: 4px;
		padding: 8px 10px;
		margin-bottom: 10px;
	}

	.tools-gps-scan__list {
		display: flex;
		flex-direction: column;
		gap: 6px;
		max-height: 360px;
		overflow: auto;
	}

	.tools-gps-scan__empty {
		font-size: 12px;
		color: #64748b;
		padding: 6px 0;
	}

	.tools-gps-scan__item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		padding: 8px 10px;
		background: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 6px;
		border-left: 3px solid #cbd5e1;
	}

	.tools-gps-scan__item--known {
		border-left-color: var(--gps-preset-color, #0ea5e9);
		background: color-mix(in srgb, var(--gps-preset-color, #0ea5e9) 8%, #fff);
	}

	.tools-gps-scan__item--added {
		opacity: 0.72;
	}

	.tools-gps-scan__item-main {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
		min-width: 0;
	}

	.tools-gps-scan__badge {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 999px;
		font-size: 11px;
		font-weight: 600;
		color: #fff;
		background: var(--gps-preset-color, #0ea5e9);
		white-space: nowrap;
	}

	.tools-gps-scan__src {
		font-size: 12px;
		color: #0f172a;
		word-break: break-all;
		background: transparent;
	}

	.tools-gps-scan__status {
		font-size: 11px;
		color: #64748b;
	}

	.tools-gps-scan__add-btn {
		flex-shrink: 0;
	}

	@media (max-width: 1100px) {
		.tools-gps-script-layout {
			grid-template-columns: 1fr;
		}
	}

	.tools-gps-filed {
		display: flex;
		align-items: center;
	}

	.tools-gps-filed>.tools-gps-filed__text {
		padding: 5px 10px;
		border-radius: 5px;
		background-color: grey;
		color: white;
		margin-right: 5px;
	}

	.tools-gps-filed>td {
		margin-right: 15px;
	}

	.tools-gps-filed__number,
	.tools-gps-filed__active {
		padding: 0;
	}

	.tools-gps-filed__name {
		flex-basis: 325px;
	}

	.tools-gps-btn.adm-btn-save {
		border: 1px solid #6d8f00 !important;
		border-radius: 4px !important;
		color: #fff !important;
		text-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
		background-color: #86a820 !important;
		background-image:
			repeating-linear-gradient(
				-45deg,
				transparent,
				transparent 2px,
				rgba(255, 255, 255, 0.04) 2px,
				rgba(255, 255, 255, 0.04) 4px
			),
			linear-gradient(to bottom, #acce11 0%, #8abb0d 45%, #729e00 100%) !important;
		box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28), 0 1px 2px rgba(0, 0, 0, 0.12) !important;
	}

	.tools-gps-btn.adm-btn-save:hover {
		background-image:
			repeating-linear-gradient(
				-45deg,
				transparent,
				transparent 2px,
				rgba(255, 255, 255, 0.05) 2px,
				rgba(255, 255, 255, 0.05) 4px
			),
			linear-gradient(to bottom, #b8d916 0%, #97ba00 100%) !important;
	}

	.tools-gps-btn.adm-btn-save:active {
		background-color: #698f00 !important;
		background-image: linear-gradient(to bottom, #729e00 0%, #5f8500 100%) !important;
		box-shadow: inset 0 2px 1px rgba(66, 84, 17, 0.55) !important;
	}

	.tools-gps-ref-row > td {
		padding: 0 !important;
		border: none !important;
	}

	.tools-gps-ref {
		margin: 24px 0 8px;
		padding: 20px 24px 22px;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		font-size: 13px;
		line-height: 1.5;
		color: #334155;
	}

	.tools-gps-ref__header {
		display: flex;
		align-items: flex-start;
		gap: 12px;
		margin-bottom: 18px;
	}

	.tools-gps-ref__header-icon {
		flex-shrink: 0;
		margin-top: 2px;
	}

	.tools-gps-ref__title {
		font-size: 16px;
		font-weight: 600;
		color: #1e293b;
		margin-bottom: 4px;
	}

	.tools-gps-ref__subtitle {
		font-size: 13px;
		color: #64748b;
	}

	.tools-gps-ref__table {
		width: 100%;
		border-collapse: collapse;
		table-layout: fixed;
	}

	.tools-gps-ref__table th {
		text-align: left;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: #64748b;
		padding: 10px 12px;
		border-bottom: 1px solid #e2e8f0;
		vertical-align: bottom;
	}

	.tools-gps-ref__th-icon {
		display: inline-block;
		margin-right: 4px;
		color: #94a3b8;
		font-size: 10px;
	}

	.tools-gps-ref__table td {
		padding: 16px 12px;
		border-bottom: 1px solid #f1f5f9;
		vertical-align: top;
	}

	.tools-gps-ref__table tbody tr:last-child td {
		border-bottom: none;
	}

	.tools-gps-ref__table td:first-child {
		width: 11%;
		white-space: nowrap;
	}

	.tools-gps-ref__table td:nth-child(2) {
		width: 18%;
	}

	.tools-gps-ref__table td:nth-child(3) {
		width: 18%;
	}

	.tools-gps-ref__table td:nth-child(4) {
		width: 24%;
	}

	.tools-gps-ref__table td:last-child {
		width: 29%;
	}

	.tools-gps-ref__badge {
		display: inline-block;
		padding: 3px 10px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 600;
		color: #fff;
		vertical-align: middle;
	}

	.tools-gps-ref__badge-icon {
		display: inline-flex;
		vertical-align: middle;
		margin-left: 6px;
	}

	.tools-gps-ref__badge--preload { background: #7c3aed; }
	.tools-gps-ref__badge-icon--preload { color: #7c3aed; }

	.tools-gps-ref__badge--prefetch { background: #16a34a; }
	.tools-gps-ref__badge-icon--prefetch { color: #16a34a; }

	.tools-gps-ref__badge--preconnect { background: #ea580c; }
	.tools-gps-ref__badge-icon--preconnect { color: #ea580c; }

	.tools-gps-ref__badge--dns-prefetch { background: #4f46e5; }
	.tools-gps-ref__badge-icon--dns-prefetch { color: #4f46e5; }

	.tools-gps-ref__badge--prerender { background: #0891b2; }
	.tools-gps-ref__badge-icon--prerender { color: #0891b2; }

	.tools-gps-ref__table ul {
		margin: 0;
		padding-left: 16px;
	}

	.tools-gps-ref__table li {
		margin-bottom: 4px;
	}

	.tools-gps-ref__table li:last-child {
		margin-bottom: 0;
	}

	.tools-gps-ref__examples {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	.tools-gps-ref__code {
		display: inline-block;
		font-family: Consolas, Monaco, "Courier New", monospace;
		font-size: 11px;
		line-height: 1.4;
		color: #be185d;
		background: #fdf2f8;
		padding: 6px 10px;
		border-radius: 4px;
		word-break: break-all;
	}

	.tools-gps-ref__note {
		margin-top: 20px;
		padding: 14px 16px;
		background: #f0f9ff;
		border-radius: 6px;
		border-left: 3px solid #3b82f6;
	}

	.tools-gps-ref__note-title {
		display: flex;
		align-items: center;
		gap: 8px;
		font-weight: 600;
		color: #1e293b;
		margin-bottom: 8px;
	}

	.tools-gps-ref__note ul {
		margin: 0;
		padding-left: 18px;
		color: #475569;
	}

	.tools-gps-ref__note li {
		margin-bottom: 4px;
	}

	.tools-gps-ref__note code {
		font-family: Consolas, Monaco, "Courier New", monospace;
		font-size: 12px;
		color: #be185d;
		background: rgba(255, 255, 255, 0.7);
		padding: 1px 4px;
		border-radius: 3px;
	}

	.tools-gps-script-ref__grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
		margin-bottom: 16px;
	}

	.tools-gps-script-ref__card {
		padding: 16px 18px;
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
	}

	.tools-gps-script-ref__card-head {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 12px;
		flex-wrap: wrap;
	}

	.tools-gps-script-ref__attr {
		font-size: 14px;
		font-weight: 700;
	}

	.tools-gps-script-ref__attr--defer { color: #2563eb; }
	.tools-gps-script-ref__attr--async { color: #7c3aed; }

	.tools-gps-script-ref__card-icon {
		display: inline-flex;
	}

	.tools-gps-script-ref__card-icon--defer { color: #2563eb; }
	.tools-gps-script-ref__card-icon--async { color: #7c3aed; }

	.tools-gps-script-ref__card-label {
		font-size: 13px;
		font-weight: 600;
		color: #334155;
	}

	.tools-gps-script-ref__text {
		margin: 0 0 14px;
		font-size: 13px;
		line-height: 1.5;
		color: #475569;
	}

	.tools-gps-script-ref__section-title {
		font-size: 12px;
		font-weight: 600;
		color: #64748b;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		margin-bottom: 8px;
	}

	.tools-gps-script-ref__list {
		margin: 0 0 14px;
		padding: 0;
		list-style: none;
	}

	.tools-gps-script-ref__list li {
		position: relative;
		padding-left: 22px;
		margin-bottom: 6px;
		font-size: 13px;
		color: #475569;
		line-height: 1.45;
	}

	.tools-gps-script-ref__list li::before {
		content: "✓";
		position: absolute;
		left: 0;
		top: 0;
		color: #16a34a;
		font-weight: 700;
		font-size: 13px;
	}

	.tools-gps-script-ref__hint {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		padding: 10px 12px;
		border-radius: 6px;
		font-size: 12px;
		line-height: 1.45;
	}

	.tools-gps-script-ref__hint--defer {
		background: #eff6ff;
		color: #1e40af;
	}

	.tools-gps-script-ref__hint--async {
		background: #f5f3ff;
		color: #5b21b6;
	}

	.tools-gps-script-ref__hint-icon {
		flex-shrink: 0;
		margin-top: 1px;
	}

	.tools-gps-script-ref__warning {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		padding: 12px 14px;
		background: #fffbeb;
		border: 1px solid #fde68a;
		border-radius: 6px;
		font-size: 13px;
		line-height: 1.5;
		color: #92400e;
	}

	.tools-gps-script-ref__warning-icon {
		flex-shrink: 0;
		margin-top: 1px;
	}
</style>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
