<?php

Bitrix\Main\Loader::registerAutoloadClasses(
	'tools.googlepagespeed',
	array(
		'Tools\\GooglePageSpeed\\Main'                  => 'lib/Main.php',
		'Tools\\GooglePageSpeed\\BufferGuard'           => 'lib/BufferGuard.php',
		'Tools\\GooglePageSpeed\\HtmlBuffer'            => 'lib/HtmlBuffer.php',
		'Tools\\GooglePageSpeed\\RobotDetector'         => 'lib/RobotDetector.php',
		'Tools\\GooglePageSpeed\\SettingsProvider'       => 'lib/SettingsProvider.php',
		'Tools\\GooglePageSpeed\\OptionActions'         => 'lib/OptionActions.php',
		'Tools\\GooglePageSpeed\\ScriptDeferral'        => 'lib/ScriptDeferral.php',
		'Tools\\GooglePageSpeed\\DeferredPresets'       => 'lib/DeferredPresets.php',
		'Tools\\GooglePageSpeed\\ScriptScanCatalog'     => 'lib/ScriptScanCatalog.php',
		'Tools\\GooglePageSpeed\\PageScriptScanner'     => 'lib/PageScriptScanner.php',
		'Tools\\GooglePageSpeed\\GPSOptionsTable'       => 'lib/GPSOptions.php',
		'Tools\\GooglePageSpeed\\ConnectedCssStyleTable' => 'lib/ConnectedCssStyle.php',
		'Tools\\GooglePageSpeed\\ConnectedJsScriptTable' => 'lib/ConnectedJsScript.php',
	)
);
