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
			new Entity\TextField(
				'HINT',
				[
					'nullable' => true,
					'default_value' => '',
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
		$connection = self::getEntity()->getConnection();
		$table = self::getTableName();

		if (!$connection->isTableExists($table)) {
			self::getEntity()->createDbTable();
			return true;
		}

		$fields = $connection->getTableFields($table);
		if (!isset($fields['HINT'])) {
			$helper = $connection->getSqlHelper();
			$connection->queryExecute(
				'ALTER TABLE ' . $helper->quote($table) . ' ADD ' . $helper->quote('HINT') . ' text NULL'
			);
		}

		return true;
	}

	public static function onAfterAdd(Entity\Event $event)
	{
		SettingsProvider::clearCache();
	}

	public static function onAfterUpdate(Entity\Event $event)
	{
		SettingsProvider::clearCache();
	}

	public static function onAfterDelete(Entity\Event $event)
	{
		SettingsProvider::clearCache();
	}
}
