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

		foreach (Tools\GooglePageSpeed\OptionsDefinitions::getInstallRows() as $valueOption) {
			Tools\GooglePageSpeed\GPSOptionsTable::add($valueOption);
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
		CopyDirFiles(
			__DIR__ . '/../css',
			Application::getDocumentRoot() . '/bitrix/css/tools.googlepagespeed',
			true,
			true
		);
		CopyDirFiles(
			__DIR__ . '/../js',
			Application::getDocumentRoot() . '/bitrix/js/tools.googlepagespeed',
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
		DeleteDirFilesEx('/bitrix/css/tools.googlepagespeed');
		DeleteDirFilesEx('/bitrix/js/tools.googlepagespeed');

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
			'OnEndBufferContent',
			$this->MODULE_ID,
			'Tools\\GooglePageSpeed\\Main',
			'OnEndBufferContent'
		);
	}
}
