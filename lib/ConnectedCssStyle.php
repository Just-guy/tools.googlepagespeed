<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Entity;

class ConnectedCssStyleTable extends Entity\DataManager
{
	public static function getTableName()
	{
		return "b_connected_css_styles";
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
				'STRING_PUBLIC_PART',
				[
					'size' => 255
				]
			),
			new Entity\StringField(
				'ROLE',
				[
					'size' => 50
				]
			),
			new Entity\StringField(
				'TYPE',
				[
					'size' => 50
				]
			),
			new Entity\StringField(
				'STRING_REGULAR_EXPRESSION',
				[
					'size' => 255
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
