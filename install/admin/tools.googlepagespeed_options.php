<? if (is_dir($_SERVER["DOCUMENT_ROOT"] . "/local/modules/tools.googlepagespeed/")) {
	require_once($_SERVER["DOCUMENT_ROOT"] . "/local/modules/tools.googlepagespeed/admin/tools.googlepagespeed_options.php");
} else if (is_dir($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/tools.googlepagespeed/")) {
	require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/tools.googlepagespeed/admin/tools.googlepagespeed_options.php");
}

