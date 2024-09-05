<?
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
// получаем id модуля
$module_id = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);
// получим права доступа текущего пользователя на модуль
$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
// если нет прав - отправим к форме авторизации с сообщением об ошибке
if ($POST_RIGHT < "S") {
	$APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}
// подключение модуля
Loader::includeModule($module_id);
// настройки модуля для админки в том числе значения по умолчанию
$aTabs = array(
	array(
		// значение будет вставленно во все элементы вкладки для идентификации (используется для javascript)
		"DIV" => "edit1",
		// название вкладки в табах 
		"TAB" => "Название вкладки в табах",
		// заголовок и всплывающее сообщение вкладки
		"TITLE" => "Главное название в админке",
		// массив с опциями секции
		"OPTIONS" => array(
			"Название секции text",
			array(
				// имя элемента формы, для хранения в бд
				"multiregionality_text",
				// поясняющий текст
				"Поясняющий текс элемента text",
				// значение по умолчани, значение text по умолчанию "50"
				"Жми!",
				// тип элемента формы "text", ширина, высота
				array(
					"text",
					10,
					50
				)
			)
		)
	),
	array(
		// значение будет вставленно во все элементы вкладки для идентификации (используется для javascript)
		"DIV"   => "edit2",
		// название вкладки в табах из основного языкового файла битрикс
		"TAB" => Loc::getMessage("MAIN_TAB_RIGHTS"),
		// заголовок и всплывающее сообщение вкладки из основного языкового файла битрикс
		"TITLE" => Loc::getMessage("MAIN_TAB_TITLE_RIGHTS")
	)
);
// проверяем текущий POST запрос и сохраняем выбранные пользователем настройки
if ($request->isPost() && check_bitrix_sessid()) {
	// цикл по вкладкам
	foreach ($aTabs as $aTab) {
		// цикл по заполненым пользователем данным
		foreach ($aTab["OPTIONS"] as $arOption) {
			// если это название секции, переходим к следующий итерации цикла
			if (!is_array($arOption)) {
				continue;
			}
			// проверяем POST запрос, если инициатором выступила кнопка с name="Update" сохраняем введенные настройки в базу данных
			if ($request["Update"]) {
				// получаем в переменную $optionValue введенные пользователем данные
				$optionValue = $request->getPost($arOption[0]);
				// метод getPost() не работает с input типа checkbox, для работы сделал этот костыль
				if ($arOption[0] == "multiregionality_checkbox") {
					if ($optionValue == "") {
						$optionValue = "N";
					}
				}
				// устанавливаем выбранные значения параметров и сохраняем в базу данных, хранить можем только текст, значит если приходит массив, то разбиваем его через запятую, если не массив сохраняем как есть
				Option::set($module_id, $arOption[0], is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
			}
			// проверяем POST запрос, если инициатором выступила кнопка с name="default" сохраняем дефолтные настройки в базу данных 
			if ($request["default"]) {
				// устанавливаем дефолтные значения параметров и сохраняем в базу данных
				Option::set($module_id, $arOption[0], $arOption[2]);
			}
		}
	}
}
// отрисовываем форму, для этого создаем новый экземпляр класса CAdminTabControl, куда и передаём массив с настройками
$tabControl = new CAdminTabControl(
	"tabControl",
	$aTabs
);
// отображаем заголовки закладок
$tabControl->Begin();
?>
<form action="<? echo ($APPLICATION->GetCurPage()); ?>?mid=<? echo ($module_id); ?>&lang=<? echo (LANG); ?>" method="post">
	<? foreach ($aTabs as $aTab) {
		if ($aTab["OPTIONS"]) {
			// завершает предыдущую закладку, если она есть, начинает следующую
			$tabControl->BeginNextTab();
			// отрисовываем форму из массива
			__AdmSettingsDrawList($module_id, $aTab["OPTIONS"]);
		}
	}
	// завершает предыдущую закладку, если она есть, начинает следующую
	$tabControl->BeginNextTab();
	// выводим форму управления правами в настройках текущего модуля
	require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php";
	// подключаем кнопки отправки формы
	$tabControl->Buttons();
	// выводим скрытый input с идентификатором сессии
	echo (bitrix_sessid_post());
	// выводим стандартные кнопки отправки формы
	?>
	<input class="adm-btn-save" type="submit" name="Update" value="Применить" />
	<input type="submit" name="default" value="По умолчанию" />
</form>
<?
// обозначаем конец отрисовки формы
$tabControl->End();
// // пример получения значения из настроек модуля конкретного поля
// $op = \Bitrix\Main\Config\Option::get(
//     // ID модуля, обязательный параметр
//     "multiregionality.d7",
//     // имя параметра, обязательный параметр
//     "multiregionality_multiselectbox",
//     // возвращается значение по умолчанию, если значение не задано
//     "",
//     // ID сайта, если значение параметра различно для разных сайтов
//     false
// );
// // пример получения значения из настроек модуля всех полей
// $op = \Bitrix\Main\Config\Option::getForModule("multiregionality.d7");
// остальные команды https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/index.php
