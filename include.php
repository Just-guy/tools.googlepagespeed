<?php

Bitrix\Main\Loader::registerAutoloadClasses(
	'tools.googlepagespeed',
	array(
		'Tools\\GooglePageSpeed\\Main'                  => 'lib/Main.php',
		'Tools\\GooglePageSpeed\\GPSOptionsTable'       => 'lib/GPSOptions.php',
		'Tools\\GooglePageSpeed\\ConnectedCssStyleTable' => 'lib/ConnectedCssStyle.php',
		'Tools\\GooglePageSpeed\\ConnectedJsScriptTable' => 'lib/ConnectedJsScript.php',
	)
);
