<?php
// пространство имен для подключений ланговых файлов
use Bitrix\Main\Localization\Loc;

// пространство имен для управления (регистрации/удалении) модуля в системе/базе
use Bitrix\Main\ModuleManager;

// пространство имен для работы с параметрами модулей хранимых в базе данных
use Bitrix\Main\Config\Option;

// пространство имен с абстрактным классом для любых приложений, любой конкретный класс приложения является наследником этого абстрактного класса
use Bitrix\Main\Application;

// пространство имен для работы c ORM
use \Bitrix\Main\Entity\Base;

// пространство имен для автозагрузки модулей
use \Bitrix\Main\Loader;

// пространство имен для событий
use \Bitrix\Main\EventManager;


use Bitrix\Main\Diag\Debug;

// подключение ланговых файлов
Loc::loadMessages(__FILE__);

class Tools_googlepagespeed extends CModule
{

	// переменные модуля
	public  $MODULE_ID;
	public  $MODULE_VERSION;
	public  $MODULE_VERSION_DATE;
	public  $MODULE_NAME;
	public  $MODULE_DESCRIPTION;
	public  $PARTNER_NAME;
	public  $PARTNER_URI;
	public  $SHOW_SUPER_ADMIN_GROUP_RIGHTS;
	public  $MODULE_GROUP_RIGHTS;
	public  $errors;

	public function __construct()
	{
		$arModuleVersion = [];
		include(__DIR__ . '/version.php');
		$this->MODULE_ID           = 'tools.googlepagespeed';
		$this->MODULE_VERSION      = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		$this->MODULE_NAME         = Loc::getMessage('TOOLS_GOOGLEPAGESPEED_NAME');
		$this->MODULE_DESCRIPTION  = Loc::getMessage('TOOLS_GOOGLEPAGESPEED_DESC');
	}

	public function DoInstall()
	{
		global $APPLICATION;

		ModuleManager::registerModule($this->MODULE_ID);
		$this->InstallFiles();
		$this->InstallDB();
		$this->InstallEvents();

		$options = [
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'YANDEX_METRIKA',
				'NAME_OPTION' => 'Вырезать скрипты Яндекс метрики',
				'OPTION_ACTION' => serialize([
					"<!-- Yandex\.Metrika counter -->.*<!-- \/Yandex\.Metrika counter -->",
					"<script(\s?| type=\W?text\/javascript\W?)>.\s*\(function\s?\(m,\s?e,\s?t,\s?r,\s?i,\s?k,\s?a\).*<\/script>",
					"<noscript.*mc\.yandex\.ru.*\/noscript>"
				]),
				'OPTION_TYPE' => 'regular-expression',
				'LIMITATION' => 'for-gps-robot'
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'GOOGLE_ANALYTICS',
				'NAME_OPTION' => 'Вырезать скрипты Google Analytics',
				'OPTION_ACTION' => serialize([
					"(<!-- Google tag \(gtag\.js\) -->\s?|\s?)<script\s?(async|'')\s?src=.*googletagmanager.*\/script>\s<script>\s?.*function gtag.*\/script>",
				]),
				'OPTION_TYPE' => 'regular-expression',
				'LIMITATION' => 'for-gps-robot'
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'GOOGLE_TAG_MANAGER',
				'NAME_OPTION' => 'Вырезать скрипты Google Tag Manager',
				'OPTION_ACTION' => serialize([
					"<!--\s?Google\s?Tag\s?Manager.*-->.*<!--\s?End\s?Google\s?Tag\s?Manager.*-->",
					"<script\s?(async.*|'')\s?src=.*googletagmanager.*\/script>"
				]),
				'OPTION_TYPE' => 'regular-expression',
				'LIMITATION' => 'for-gps-robot'
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ELIMINATE_RESOURCES_THAT_BLOCK_DISPLAY',
				'NAME_OPTION' => 'Устранить таблицы стилей, блокирующие рендеринг',
				'OPTION_ACTION' => "eliminateResourcesThatBlockDisplay",
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone'
			],
		];

		foreach ($options as $valueOption) {
			Tools\GooglePageSpeed\GPSOptionsTable::add([
				"ACTIVE" => $valueOption['ACTIVE'],
				"CODE_OPTION" => $valueOption['CODE_OPTION'],
				"NAME_OPTION" => $valueOption['NAME_OPTION'],
				"OPTION_ACTION" => $valueOption['OPTION_ACTION'],
				"OPTION_TYPE" => $valueOption['OPTION_TYPE'],
				"LIMITATION" => $valueOption['LIMITATION']
			]);
		}

		$APPLICATION->includeAdminFile(
			Loc::getMessage('TOOLS_GOOGLEPAGESPEED_INSTALL_TITLE') . ' «' . Loc::getMessage('TOOLS_GOOGLEPAGESPEED_NAME') . '»',
			__DIR__ . '/step.php'
		);
	}

	public function InstallFiles()
	{
		//CopyDirFiles(
		//	__DIR__ . "/components",
		//	Application::getDocumentRoot() . "/bitrix/components",
		//	true,
		//	true
		//);
		CopyDirFiles(
			__DIR__ . "/admin",
			Application::getDocumentRoot() . "/bitrix/admin",
			true,
			true
		);

		return true;
	}

	public function InstallDB()
	{
		Loader::includeModule($this->MODULE_ID);

		\Tools\GooglePageSpeed\ConnectedCssStyleTable::exitsOrCreateTable();
		\Tools\GooglePageSpeed\GPSOptionsTable::exitsOrCreateTable();
		\Tools\GooglePageSpeed\ConnectedJsScriptTable::exitsOrCreateTable();
	}

	public function InstallEvents()
	{
		EventManager::getInstance()->registerEventHandler(
			'main',
			'OnPageStart',
			$this->MODULE_ID,
			'Tools\\GooglePageSpeed\\Main',
			'OnPageStart'
		);
		EventManager::getInstance()->registerEventHandler(
			'main',
			'OnEndBufferContent',
			$this->MODULE_ID,
			'Tools\\GooglePageSpeed\\Main',
			'OnEndBufferContent'
		);
	}

	public function DoUninstall()
	{
		global $APPLICATION;

		$this->UnInstallFiles();
		$this->UnInstallDB();
		$this->UnInstallEvents();
		ModuleManager::unRegisterModule($this->MODULE_ID);

		$APPLICATION->includeAdminFile(
			Loc::getMessage('TOOLS_GOOGLEPAGESPEED_UNINSTALL_TITLE') . ' «' . Loc::getMessage('TOOLS_GOOGLEPAGESPEED_NAME') . '»',
			__DIR__ . '/unstep.php'
		);
	}

	public function UnInstallFiles()
	{
		@unlink(Application::getDocumentRoot() . '/bitrix/admin/tools.googlepagespeed_options.php');

		//DeleteDirFilesEx("/bitrix/components/" . $this->MODULE_ID);

		Option::delete($this->MODULE_ID);
	}

	public function UnInstallDB()
	{
		Loader::includeModule($this->MODULE_ID);
		\Tools\GooglePageSpeed\ConnectedCssStyleTable::dropTable();
		\Tools\GooglePageSpeed\GPSOptionsTable::dropTable();
		\Tools\GooglePageSpeed\ConnectedJsScriptTable::dropTable();

		Option::delete($this->MODULE_ID);
	}

	public function UnInstallEvents()
	{
		EventManager::getInstance()->unRegisterEventHandler(
			'main',
			'OnPageStart',
			$this->MODULE_ID,
			'Tools\\GooglePageSpeed\\Main',
			'OnPageStart'
		);
		EventManager::getInstance()->unRegisterEventHandler(
			'main',
			'OnEndBufferContent',
			$this->MODULE_ID,
			'Tools\\GooglePageSpeed\\Main',
			'OnEndBufferContent'
		);
	}
}
