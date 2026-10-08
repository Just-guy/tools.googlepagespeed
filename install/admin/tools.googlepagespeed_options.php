<?php
$gpsAdminOptions = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/tools.googlepagespeed/admin/tools.googlepagespeed_options.php';
if (!is_file($gpsAdminOptions)) {
	$gpsAdminOptions = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/tools.googlepagespeed/admin/tools.googlepagespeed_options.php';
}
require $gpsAdminOptions;
