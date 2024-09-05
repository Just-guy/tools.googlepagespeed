<?
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

$arrayLinkCssStyles = Tools\GooglePageSpeed\Main::getLinksCssStyles();
$arrayLinkJsScripts = Tools\GooglePageSpeed\Main::getLinksJsScripts();
$arrayOptions = Tools\GooglePageSpeed\Main::getOptions();
$roleLinkCssStyles = ['preload', 'prefetch', 'preconnect', 'dns-prefetch', 'prerender'];
$typeLinkCssStyles = ['style', 'script', 'font', 'fetch', 'image', 'track'];
$attributeLinkJsScripts = ['async', 'defer'];
$randomId = '';
$active = '';

if ($request["Update"]) {
	// === GPS options
	foreach ($request['OPTIONS'] as $keyOption => $valueOption) {
		if ($arrayOptions[$keyOption]["ACTIVE"] === $valueOption["ACTIVE"]) continue;

		$active = 'N';
		if ($valueOption["ACTIVE"] != null) $active = 'Y';

		Tools\GooglePageSpeed\GPSOptionsTable::update($arrayOptions[$keyOption]['ID'], [
			"ACTIVE" => $active,
		]);

		$arrayOptions[$keyOption]["ACTIVE"] = $valueOption["ACTIVE"];
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

	foreach ($request["STRING_PUBLIC_PART"] as $key => $value) {
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

	foreach ($request["CONNECTED_JS_SCRIPT"] as $key => $value) {
		if ($arrayLinkJsScripts[$key]) continue;

		$active = 'N';
		if ($request["CONNECTED_JS_SCRIPT"][$key]["ACTIVE"] != null) $active = 'Y';

		// Add
		Tools\GooglePageSpeed\ConnectedJsScriptTable::add([
			"ACTIVE" => $active,
			"ROLE" => $request["CONNECTED_JS_SCRIPT"][$key]["ROLE"],
			"ATTRIBUTE" => $request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"],
			"STRING_PUBLIC_PART" => $request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"],
			"STRING_REGULAR_EXPRESSION" => preg_quote($request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"], '/')
		]);

		$arrayLinkJsScripts[$key]["ID"] = $request["CONNECTED_JS_SCRIPT"][$key]["ID"];
		$arrayLinkJsScripts[$key]["ACTIVE"] = $active;
		$arrayLinkJsScripts[$key]["ROLE"] = $request["CONNECTED_JS_SCRIPT"][$key]["ROLE"];
		$arrayLinkJsScripts[$key]["ATTRIBUTE"] = $request["CONNECTED_JS_SCRIPT"][$key]["ATTRIBUTE"];
		$arrayLinkJsScripts[$key]["STRING_PUBLIC_PART"] = $request["CONNECTED_JS_SCRIPT"][$key]["STRING_PUBLIC_PART"];
	}
	// === LinksJsScripts
}


$APPLICATION->SetTitle('Опции');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

if ($_REQUEST["mess"] == "ok" && $ID > 0)
	CAdminMessage::ShowMessage(["MESSAGE" => "Данные сохранены", "TYPE" => "OK"]);

if ($message)
	echo $message->Show();
elseif ($DB->GetErrorMessage() != "")
	CAdminMessage::ShowMessage($DB->GetErrorMessage()); ?>

<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?mid=<? echo ($module_id); ?>&lang=<?= (LANG); ?>" ENCTYPE="multipart/form-data" name="post_form">
	<?= bitrix_sessid_post() ?>
	<? $tabControl->Begin(); ?>


	<? $tabControl->BeginNextTab(); ?>
	<? $randomId = random_int(1, 999); ?>
	<? foreach ($arrayOptions as $keyOption => $valueOption) { ?>
		<tr>
			<td width="50%"><?= $valueOption["NAME_OPTION"] ?></td>
			<td width="50%">
				<input type="checkbox" name="OPTIONS[<?= $keyOption ?>][ACTIVE]" value="Y" size="60" <? if (!empty($valueOption['ACTIVE']) && $valueOption['ACTIVE'] == 'Y') echo 'checked' ?>>
				<input type="hidden" name="OPTIONS[<?= $keyOption ?>][CODE_OPTION]" value="CUT_FOR_GOOGLE_PS" size="60">
			</td>
		</tr>
	<? } ?>

	<!--<tr class="heading">
		<td colspan="2" valign="top" align="center">Файлы стилей, которым необходимо присвоить preload</td>
	</tr>-->

	<? $tabControl->BeginNextTab(); ?>

	<? if (empty($arrayLinkCssStyles)) :
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
				<select id="tags-link" name="STRING_PUBLIC_PART[0][ROLE]">
					<? foreach ($roleLinkCssStyles as $keyRole => $valueRole) { ?>
						<option value="<?= $valueRole ?>"><?= $valueRole ?></option>
					<? } ?>
				</select>
			</td>
			<td class="tools-gps-filed__text">
				as=
			</td>
			<td class="tools-gps-filed__value">
				<select id="tags-link" name="STRING_PUBLIC_PART[0][TYPE]">
					<? foreach ($typeLinkCssStyles as $keyType => $valueType) { ?>
						<option value="<?= $valueType ?>"><?= $valueType ?></option>
					<? } ?>
				</select>
			</td>
		</tr>
	<? else : ?>
		<? foreach ($arrayLinkCssStyles as $keyLinkCss => $valueLinkCss) {
			$randomId = random_int(1, 999); ?>
			<tr class="tools-gps-filed" data-container="link-css" data-id="<?= $valueLinkCss['ID'] ?>" data-key="<?= $keyLinkCss ?>">
				<td class="tools-gps-filed__number"><?= (int)$keyLinkCss + 1 ?>.</td>
				<td class="tools-gps-filed__active">
					<input type="checkbox" name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][ACTIVE]" value="Y" size="60" id="designed_checkbox_<?= $randomId ?>" class="adm-designed-checkbox" <? if (!empty($valueLinkCss['ACTIVE']) && $valueLinkCss['ACTIVE'] == 'Y') echo 'checked' ?>>
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
					<select id="tags-link" name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][ROLE]">
						<? foreach ($roleLinkCssStyles as $keyRole => $valueRole) { ?>
							<option value="<?= $valueRole ?>" <? if ($valueLinkCss["ROLE"] == $valueRole) echo 'selected' ?>><?= $valueRole ?></option>
						<? } ?>
					</select>
				</td>
				<td class="tools-gps-filed__text">
					as=
				</td>
				<td class="tools-gps-filed__value">
					<select id="tags-link" name="STRING_PUBLIC_PART[<?= $keyLinkCss ?>][TYPE]">
						<? foreach ($typeLinkCssStyles as $keyType => $valueType) { ?>
							<option value="<?= $valueType ?>" <? if ($valueLinkCss["TYPE"] == $valueType) echo 'selected' ?>><?= $valueType ?></option>
						<? } ?>
					</select>
				</td>
				<? //if ($keyLinkCss != 0) : 
				?>
				<td class="tools-gps-filed__delete">
					<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
				</td>
				<? //endif; 
				?>
			</tr>
		<? } ?>
	<? endif; ?>

	<tr>
		<td>
			<input type="button" class="tools-gps-filed__add adm-btn-save" data-container-button="link-css" value="Добавить url">
		</td>
	</tr>

	<? $tabControl->BeginNextTab(); ?>
	<? if (empty($arrayLinkJsScripts)) :
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
				<select id="tags-link" name="CONNECTED_JS_SCRIPT[0][ATTRIBUTE]">
					<? foreach ($attributeLinkJsScripts as $keyAttribute => $valueAttribute) { ?>
						<option value="<?= $valueAttribute ?>"><?= $valueAttribute ?></option>
					<? } ?>
				</select>
			</td>
			<td class="tools-gps-filed__text">
				src=
			</td>
			<td class="tools-gps-filed__value">
				<input type="text" name="CONNECTED_JS_SCRIPT[0][STRING_PUBLIC_PART]" value="" size="60">
			</td>
		</tr>
	<? else : ?>
		<? foreach ($arrayLinkJsScripts as $keyLinkJs => $valueLinkJs) {
			$randomId = random_int(1, 999); ?>
			<tr class="tools-gps-filed" data-container="link-js" data-id="<?= $valueLinkJs['ID'] ?>" data-key="<?= $keyLinkJs ?>">
				<td class="tools-gps-filed__number"><?= (int)$keyLinkJs + 1 ?>.</td>
				<td class="tools-gps-filed__active">
					<input type="checkbox" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][ACTIVE]" value="Y" size="60" id="designed_checkbox_<?= $randomId ?>" class="adm-designed-checkbox" <? if (!empty($valueLinkJs['ACTIVE']) && $valueLinkJs['ACTIVE'] == 'Y') echo 'checked' ?>>
					<label class="adm-designed-checkbox-label" for="designed_checkbox_<?= $randomId ?>" title=""></label>
				</td>
				<td class="tools-gps-filed__value">
					<input type="hidden" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][ID]" value="<?= $valueLinkJs['ID'] ?>" size="60">
				</td>
				<td class="tools-gps-filed__value">
					<select id="tags-link" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][ATTRIBUTE]">
						<? foreach ($attributeLinkJsScripts as $keyAttribute => $valueAttribute) { ?>
							<option value="<?= $valueAttribute ?>" <? if ($valueLinkJs["ATTRIBUTE"] == $valueAttribute) echo 'selected' ?>><?= $valueAttribute ?></option>
						<? } ?>
					</select>
				</td>
				<td class="tools-gps-filed__text">
					src=
				</td>
				<td class="tools-gps-filed__value">
					<input type="text" name="CONNECTED_JS_SCRIPT[<?= $keyLinkJs ?>][STRING_PUBLIC_PART]" value="<?= $valueLinkJs['STRING_PUBLIC_PART'] ?>" size="60">
				</td>
				<? //if ($keyLinkCss != 0) : 
				?>
				<td class="tools-gps-filed__delete">
					<input type="button" class="tools-gps-filed__delete-field adm-btn-delete" value="x">
				</td>
				<? //endif; 
				?>
			</tr>
		<? } ?>
	<? endif; ?>

	<tr>
		<td>
			<input type="button" class="tools-gps-filed__add adm-btn-save" data-container-button="link-js" value="Добавить url">
		</td>
	</tr>

	<!--<tr class="heading">
		<td colspan="2" valign="top" align="center">Файлы стилей, которым необходимо присвоить preload</td>
	</tr>-->

	<? $tabControl->BeginNextTab(); ?>

	<? $tabControl->Buttons(); ?>
	<input class="adm-btn-save" type="submit" name="Update" value="Применить" />
	<input type="hidden" name="lang" value="<?= LANG ?>">
</form>

<? $tabControl->End(); ?>
<? $tabControl->ShowWarnings("post_form", $message); ?>

<!--  JS Scripts  -->
<script>
	let dataContainer = '',
		listFileds = 0,
		lastElementContainer = '',
		idNextElement,
		keyNextElement,
		randomNumber = '',
		templateField = '';

	document.addEventListener('click', (event) => {
		if (event.target.classList.contains('tools-gps-filed__add')) {
			//debugger
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
							<select id="tags-link" name="STRING_PUBLIC_PART[` + keyNextElement + `][ROLE]">
								<? foreach ($roleLinkCssStyles as $keyRole => $valueRole) { ?>
									<option value="<?= $valueRole ?>"><?= $valueRole ?></option>
								<? } ?>
							</select>
						</td>
						<td class="tools-gps-filed__text">
							as=
						</td>
						<td class="tools-gps-filed__value">
							<select id="tags-link" name="STRING_PUBLIC_PART[` + keyNextElement + `][TYPE]">
								<? foreach ($typeLinkCssStyles as $keyType => $valueType) { ?>
									<option value="<?= $valueType ?>"><?= $valueType ?></option>
								<? } ?>
							</select>
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
							<select id="tags-link" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][ATTRIBUTE]">
								<? foreach ($attributeLinkJsScripts as $keyAttribute => $valueAttribute) { ?>
									<option value="<?= $valueAttribute ?>"><?= $valueAttribute ?></option>
								<? } ?>
							</select>
						</td>
						<td class="tools-gps-filed__text">
							src=
						</td>
						<td class="tools-gps-filed__value">
							<input type="text" name="CONNECTED_JS_SCRIPT[` + keyNextElement + `][STRING_PUBLIC_PART]" value="" size="60">
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
</style>
<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
