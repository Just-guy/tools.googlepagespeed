<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Entity;

class GPSOptionsTable extends Entity\DataManager
{
	public static function getTableName()
	{
		return "b_gps_options";
	}

	public static function getMap()
	{
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
			new Entity\TextField(
				'OPTION_ACTION',
				[]
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

	public static function onAfterAdd(Entity\Event $event)
	{
		Main::clearRulesCache();
	}

	public static function onAfterUpdate(Entity\Event $event)
	{
		Main::clearRulesCache();
	}

	public static function onAfterDelete(Entity\Event $event)
	{
		Main::clearRulesCache();
	}
}
