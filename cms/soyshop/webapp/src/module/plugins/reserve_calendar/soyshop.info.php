<?php
/*
 */
class ReserveCalendarInfo extends SOYShopInfoPageBase{

	function getPage($active = false){
		if($active){
			return '<a href="' . SOY2PageController::createLink("Config.Detail?plugin=reserve_calendar") . '">簡易予約カレンダーの設定方法</a>';
		}else{
			return "";
		}
	}
}
SOYShopPlugin::extension("soyshop.info", "reserve_calendar", "ReserveCalendarInfo");
?>