<?php

class ReserveCalendarOption extends SOYShopItemOptionBase{

    private function getCartAttributeId($optionId, $itemIndex, $itemId){
        return "reserve_calendar_{$optionId}_{$itemIndex}_{$itemId}";
    }

    function clear($index, CartLogic $cart){
        $items = $cart->getItems();
        if(isset($items[$index])){
            $itemId = $items[$index]->getItemId();

            $obj = self::getCartAttributeId("schedule_id", $index, $itemId);
            $cart->clearAttribute($obj);
        }
    }

    function compare($postedOption, CartLogic $cart){
        $checkOptionId = null;

        $items = $cart->getItems();

        //比較用の配列を作成する
        $attributes = array();
        foreach($items as $index => $item){
            $obj = self::getCartAttributeId("schedule_id", $index, $item->getItemId());
            $attributes[$index]["schedule_id"] = $cart->getAttribute($obj);

            $currentOptions = array_diff($attributes[$index], array(null));

            if($postedOption == $currentOptions){
                $checkOptionId = $index;
                break;
            }
        }

        return $checkOptionId;
    }

    function doPost($index, CartLogic $cart){

        if(isset($_REQUEST["item_option"]["schedule_id"])){
            $items = $cart->getItems();
            if(isset($items[$index])){
                $itemId = $items[$index]->getItemId();

                $obj = self::getCartAttributeId("schedule_id", $index, $itemId);
                $cart->setAttribute($obj, $_REQUEST["item_option"]["schedule_id"]);
            }
        }
    }

    /**
     * 商品情報の下に表示される情報
     * @param htmlObj, integer index
     * @return string html
     */
    function onOutput($htmlObj, $index){
        $cart = CartLogic::getCart();

        $items = $cart->getItems();
        if(!isset($items[$index])){
            return "";
        }

        $itemId = $items[$index]->getItemId();

        $obj = self::getCartAttributeId("schedule_id", $index, $itemId);
        $schId = $cart->getAttribute($obj);

        $list = SOY2Logic::createInstance("module.plugins.reserve_calendar.logic.Calendar.LabelLogic")->getLabelList($itemId);

        $sch = self::getScheduleById($schId);
        if(isset($list[$sch->getLabelId()])){
            return $sch->getYear() . "-" . $sch->getMonth() . "-"  . $sch->getDay() . " " . $list[$sch->getLabelId()];
        }

        return "";
    }

    function order($index){
        $cart = CartLogic::getCart();

        $items = $cart->getItems();
        if(!isset($items[$index])){
            return null;
        }

        $itemId = $items[$index]->getItemId();

        $obj = self::getCartAttributeId("schedule_id", $index, $itemId);
        $schId = $cart->getAttribute($obj);

        //予約として登録
        $resDao = self::resDao();
        $res = new SOYShopReserveCalendar_Reserve();
        $res->setScheduleId($schId);
        $res->setOrderId($cart->getAttribute("order_id"));
        //$res->setToken();
        $res->setTemp(SOYShopReserveCalendar_Reserve::NO_TEMP);
        //$res->setTempDate();
        $res->setReserveDate(time());

        /**
         * @ToDo 仮登録の仕組み
         */
        try{
            $resId = $resDao->insert($res);
        }catch(Exception $e){
            var_dump($e);
        }

        //注文属性にも入れておく
        return soy2_serialize(array("reserve_id" => $resId));
    }

    function display($item){
        $attributes = $item->getAttributeList();
        if(isset($attributes["reserve_id"]) && is_numeric($attributes["reserve_id"])){
            $sch = self::schDao()->getScheduleByReserveId((int)$attributes["reserve_id"]);

            $list = SOY2Logic::createInstance("module.plugins.reserve_calendar.logic.Calendar.LabelLogic")->getLabelList($item->getItemId());

            if(isset($list[$sch->getLabelId()])){
                return $sch->getYear() . "-" . $sch->getMonth() . "-"  . $sch->getDay() . " " . $list[$sch->getLabelId()];
            }
        }
    }

    function edit($key){}

    private function getScheduleById($schId){
        try{
            return self::schDao()->getById($schId);
        }catch(Exception $e){
            return new SOYShopReserveCalendar_Schedule();
        }
    }

    private function schDao(){
        static $dao;
        if(is_null($dao)){
            SOY2::import("module.plugins.reserve_calendar.domain.SOYShopReserveCalendar_Schedule");
            SOY2::import("module.plugins.reserve_calendar.domain.SOYShopReserveCalendar_ScheduleDAO");
            $dao = SOY2DAOFactory::create("SOYShopReserveCalendar_ScheduleDAO");
        }
        return $dao;
    }

    private function resDao(){
        static $dao;
        if(is_null($dao)){
            SOY2::import("module.plugins.reserve_calendar.domain.SOYShopReserveCalendar_Reserve");
            SOY2::import("module.plugins.reserve_calendar.domain.SOYShopReserveCalendar_ReserveDAO");
            $dao = SOY2DAOFactory::create("SOYShopReserveCalendar_ReserveDAO");
        }
        return $dao;
    }
}

SOYShopPlugin::extension("soyshop.item.option", "reserve_calendar", "ReserveCalendarOption");
?>
