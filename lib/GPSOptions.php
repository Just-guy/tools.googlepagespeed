<?
// пространство имен модуля
namespace Tools\GooglePageSpeed;

// пространство имен для ORM
use \Bitrix\Main\Entity;
// пространство имен для кеша
use \Bitrix\Main\Application;

// сущность ORM унаследованная от DataManager
class GPSOptionsTable extends Entity\DataManager
{
	// название таблицы в базе данных, если не указывать данную функцию, то таблица в бд сформируется автоматически из неймспейса
	public static function getTableName()
	{
		return "b_gps_options";
	}

	// подключение к БД, если не указывать, то будет использовано значение по умолчанию подключения из файла .settings.php. Если указать, то можно выбрать подключение, которое может быть описано в .setting.php

	// метод возвращающий структуру ORM-сущности
	public static function getMap()
	{
		/*
         * Типы полей: 
         * DatetimeField - дата и время
         * DateField - дата
         * BooleanField - логическое поле да/нет
         * IntegerField - числовой формат
         * FloatField - числовой дробный формат
         * EnumField - список, можно передавать только заданные значения
         * TextField - text
         * StringField - varchar
         */

		return [
			new Entity\IntegerField(
				"ID",
				[
					"primary" => true,
					"autocomplete" => true,
				]
			),
			new Entity\BooleanField(
				'ACTIVE',
				[
					'values' => ['Y', 'N'],
					'default_value' => 'Y'
				]
			),
			new Entity\StringField(
				'CODE_OPTION',
				[
					'size' => 255
				]
			),
			new Entity\StringField(
				'NAME_OPTION',
				[
					'size' => 255
				]
			),
			new Entity\StringField(
				'OPTION_ACTION',
				[
					'size' => 255
				]
			),
			new Entity\StringField(
				'OPTION_TYPE',
				[
					'size' => 50
				]
			),
			new Entity\StringField(
				'LIMITATION',
				[
					'size' => 50
				]
			),
		];
	}

	public static function dropTable()
	{
		$connection = \Bitrix\Main\Application::getConnection();
		$connection->dropTable(self::getTableName());
		return true;
	}

	public static function exitsOrCreateTable()
	{
		if (!self::getEntity()->getConnection()->isTableExists(self::getTableName())) {
			self::getEntity()->createDbTable();
		}
		return true;
	}

	// // события можно задавать прямо в ORM-сущности, для примера запретим изменять поле LINK_PICTURE
	// public static function onBeforeUpdate(Entity\Event $event)
	// {
	// 	$result = new Entity\EventResult;
	// 	$data = $event->getParameter("fields");
	// 	if (isset($data["LINK_PICTURE"])) {
	// 		$result->addError(
	// 			new Entity\FieldError(
	// 				$event->getEntity()->getField("LINK_PICTURE"),
	// 				"Запрещено менять LINK_PICTURE код у баннера"
	// 			)
	// 		);
	// 	}
	// 	return $result;
	// }

	// очистка тегированного кеша при добавлении
	//public static function onAfterAdd(Entity\Event $event)
	//{
	//	RegionListTable::clearCache();
	//}
	//// очистка тегированного кеша при изменении
	//public static function onAfterUpdate(Entity\Event $event)
	//{
	//	RegionListTable::clearCache();
	//}
	//// очистка тегированного кеша при удалении
	//public static function onAfterDelete(Entity\Event $event)
	//{
	//	RegionListTable::clearCache();
	//}
	//// основной метод очистки кеша по тегу
	//public static function clearCache()
	//{
	//	// служба пометки кеша тегами
	//	$taggedCache = Application::getInstance()->getTaggedCache();
	//	$taggedCache->clearByTag('popup');
	//}
}
