<?php

namespace Tools\GooglePageSpeed;

use COption;
use \Bitrix\Main\Context;

class Main
{
	static $module_id = "tools.googlepagespeed";

	// public static function OnBeforeEndBufferContent()
	// {
	//     GLOBAL $APPLICATION;

	//     if(Context::getCurrent()->getRequest()->isAdminSection()) return;

	//     $url        = defined('SEOEVERYWHERE_REQUEST_URI') ? SEOEVERYWHERE_REQUEST_URI : $_SERVER['REQUEST_URI'];
	//     $domain     = $_SERVER['HTTP_HOST'];
	//     $subdomain  = false;

	//     if(mb_substr_count($domain, '.') == 2) {
	//         list($subdomain, $domain) = explode('.', $domain, 2);
	//     }

	//     $meta = MultiregionalityMeta::getMeta($url, $subdomain);

	//     if($meta) {
	//         MultiregionalityMeta::setMeta($meta);
	//     } else {
	//         $filter = MultiregionalityMeta::getFilter(self::$module_id);

	//         if($filter && (count($filter) > 1 || !isset($filter["FACET_OPTIONS"]))) {
	//             $options = [
	//                 'FILTER_HEAD'           => COption::GetOptionString(self::$module_id, "FILTER_HEAD"),
	//                 'FILTER_TITLE'          => COption::GetOptionString(self::$module_id, "FILTER_TITLE"),
	//                 'FILTER_DESCRIPTION'    => COption::GetOptionString(self::$module_id, "FILTER_DESCRIPTION"),
	//             ];
	//             $category = MultiregionalityMeta::getCategory(self::$module_id);

	//             foreach ($options as $key => $option) {
	//                 $params = MultiregionalityMeta::parseParams($option);
	//                 if($category) {
	//                     $params["CATEGORY"]["VALUE"] = $category;
	//                 }

	//                 MultiregionalityMeta::prepareParams($params, $filter);
	//                 $text = MultiregionalityMeta::insertParams($params, $option);

	//                 switch ($key) {
	//                     case 'FILTER_HEAD':
	//                         $APPLICATION->SetTitle($text);
	//                         break;
	//                     case 'FILTER_TITLE':
	//                         $APPLICATION->SetPageProperty('title', $text);
	//                         break;
	//                     case 'FILTER_DESCRIPTION':
	//                         $APPLICATION->SetPageProperty('description', $text);
	//                         break;
	//                 }
	//             }
	//         }
	//     }
	// }

	public static function OnEndBufferContent(&$content)
	{
		$isAdmin = Context::getCurrent()->getRequest()->isAdminSection();

		$templateArrayLinksCss = Main::getLinksCssStyles(["ACTIVE" => "Y"]);
		$templateArrayLinksJS = Main::getLinksJsScripts(["ACTIVE" => "Y"]);
		$arrayOptions = Main::getOptions();

		if (!Main::thisRobot() || $isAdmin) {
			if (!empty($templateArrayLinksCss)) {
				foreach ($templateArrayLinksCss as $value) {
					if (preg_match('(.*' . $value['STRING_REGULAR_EXPRESSION'] . '.*rel=)', $content, $arMatches) && !empty($value['STRING_REGULAR_EXPRESSION'])) {
						$content = preg_replace('(.*' . $value['STRING_REGULAR_EXPRESSION'] . '.*)', $arMatches[0] . '"' . $value['ROLE'] . '" as="' . $value['TYPE'] . '" >', $content);
					}
				}
			}

			foreach ($arrayOptions as $valueOption) {
				if ($valueOption['ACTIVE'] != 'Y' || $valueOption['OPTION_TYPE'] != 'regular-expression') continue;

				$regularExpressionArray = unserialize(htmlspecialcharsback($valueOption['OPTION_ACTION']));

				foreach ($regularExpressionArray as $regularExpression) {
					if (preg_match('/' . $regularExpression . '/msU', $content)) {
						$content = preg_replace('/' . $regularExpression . '/msU', '', $content);
					}
				}
			}
		}

		if (!empty($templateArrayLinksJS)) {
			foreach ($templateArrayLinksJS as $value) {
				if (preg_match('(src.*' . $value['STRING_REGULAR_EXPRESSION'] . '.*)', $content, $arMatches) && !empty($value['STRING_REGULAR_EXPRESSION'])) {
					$content = preg_replace('(src.*' . $value['STRING_REGULAR_EXPRESSION'] . '.*)', $value['ATTRIBUTE'] . ' ' . $arMatches[0], $content);
				}
			}
		}
	}

	public static function getLinksCssStyles($filter = [])
	{
		// запрос к базе
		$result = ConnectedCssStyleTable::getList(
			[
				'select' => ['*'],
				'filter' => $filter
			]
		);
		// преобразование запроса от базы
		while ($row = $result->fetch()) {
			$resultArray[] = $row;
		}

		// возвращаем ответ от баззы
		return $resultArray;
	}

	public static function getLinksJsScripts($filter = [])
	{
		// запрос к базе
		$result = ConnectedJsScriptTable::getList(
			[
				'select' => ['*'],
				'filter' => $filter
			]
		);
		// преобразование запроса от базы
		while ($row = $result->fetch()) {
			$resultArray[] = $row;
		}

		// возвращаем ответ от баззы
		return $resultArray;
	}

	public static function getOptions()
	{
		// запрос к базе
		$result = GPSOptionsTable::getList(
			[
				'select' => ['*'],
			]
		);
		// преобразование запроса от базы
		while ($row = $result->fetch()) {
			$resultArray[] = $row;
		}

		// возвращаем ответ от баззы
		return $resultArray;
	}

	public static function thisRobot()
	{
		$userAgent = \Bitrix\Main\Application::getInstance()->getContext()->getServer()->getUserAgent();
		if (strpos($userAgent, "Lighthouse")) {
			return true;
		} else {
			return false;
		}
	}

	public static function OnPageStart()
	{
		global $APPLICATION;

		define('TOOLS_GOOGLEPAGESPEED', 'YES');

		//  $context        = \Bitrix\Main\Application::getInstance()->getContext();
		//  $request        = $context->getRequest();
		//  $server         = $context->getServer();
		//  $request_uri    = $request->getRequestUri();

		//  $data = \Multiregionality\MultiregionalityLink::getLink($request_uri);
		//  if($data) {
		//      if($data['REDIRECT']) {
		//          $request_uri = $data['NEW'];
		//          if($data['QUERY']) {
		//              $request_uri .= "?" . $data['QUERY'];
		//          }
		//          LocalRedirect($request_uri, false, '301 Moved Permanently');
		//      } else {
		//          $request_uri = $data['OLD'];
		//          if($data['QUERY']) {
		//              $request_uri .= "?" . $data['QUERY'];
		//          }
		//          $request_new_uri = $data['NEW'];
		//          if($data['QUERY']) {
		//              $request_new_uri .= "?" . $data['QUERY'];
		//          }

		//          $serverArray                = $server->toArray();
		//          $_SERVER['REQUEST_URI']     = $request_uri;
		//          $serverArray['REQUEST_URI'] = $request_uri;
		//          $server->set($serverArray);

		//          $context->initialize(new \Bitrix\Main\HttpRequest($server, $_GET, [], [], $_COOKIE), $context->getResponse(), $server);
		//          $APPLICATION->reinitPath();

		//          $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$data[NEW]";
		//          define('SEOEVERYWHERE_LINK_NEW', $actual_link);
		//          define('SEOEVERYWHERE_REQUEST_URI', $request_new_uri);
		//      }
		//  } else {
		//	define('MULTIREGIONALITY', 'YES');
		//  }
	}

	// public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
	// {
	//     $aModuleMenu[] = Array(
	//         "parent_menu"   => "global_menu_content",
	//         "section"       => "content",
	//         "icon"          => "util_menu_icon",
	//         "page_icon"     => "util_menu_icon",
	//         "sort"          => "0",
	//         "text"          => "СЕО везде",
	//         "title"         => "СЕО везде",
	//         "url"           => "/bitrix/admin/seoeverywhere_meta.php",
	//         "more_url"      => Array("seoeverywhere_meta.php"),
	//         "items_id"      => "menu_content",
	//         "items"         => Array(
	//             Array(
	//                 "text" => "Список мета тегов",
	//                 "url" => "/bitrix/admin/seoeverywhere_meta.php",
	//                 "more_url" => Array("seoeverywhere_meta")
	//             ),
	//             Array(
	//                 "text" => "Список ссылок",
	//                 "url" => "/bitrix/admin/seoeverywhere_link.php",
	//                 "more_url" => Array("seoeverywhere_link")
	//             ),
	//             Array(
	//                 "text" => "Список тегов",
	//                 "url" => "/bitrix/admin/seoeverywhere_tag.php",
	//                 "more_url" => Array("seoeverywhere_tag")
	//             ),
	//             Array(
	//                 "text" => "Настройки",
	//                 "url" => "/bitrix/admin/settings.php?mid=seoeverywhere",
	//                 "more_url" => Array()
	//             )
	//         )
	//     );
	// }

}
