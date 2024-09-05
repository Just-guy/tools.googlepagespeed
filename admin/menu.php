<?
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Localization\Loc;													// пространство имен для подключений ланговых файлов				

Loc::loadMessages(__FILE__);															// подключение ланговых файлов

$aMenu = array(																			// сформируем верхний пункт меню
	'parent_menu' => 'global_menu_content',										// пункт меню в разделе Контент
	'sort' => 1,																			// сортировка
	'text' => "Инструменты для Google PageSpeed",																			// название пункта меню
	"items_id" => "menu_webforms",													// идентификатор ветви
	"icon" => "form_menu_icon",														// иконка
);			

$aMenu["items"][] =  array(															// дочерния ветка меню
	'text' => 'Настройки',																	// название подпункта меню
	'url' => 'tools.googlepagespeed_options.php?lang=' . LANGUAGE_ID . '&mid=tools.googlepagespeed'																	// ссылка для перехода
);

return $aMenu;																				// возвращаем основной массив $aMenu
