<?php

class RecommendItemAreaPage extends WebPage{
	
	private $configObj;
	
	function __construct(){}
	
	function execute(){
		WebPage::__construct();
		
		$itemIds = SOYShop_DataSets::get("item.recommend_items", array());
		
		$itemDao = SOY2DAOFactory::create("shop.SOYShop_ItemDAO");
		
		$items = array();
		foreach($itemIds as $itemId){
			try{
				$items[] = $itemDao->getById($itemId);
			}catch(Exception $e){
				continue;
			}
		}
		$this->createAdd("recommend_list", "_common.Item.ItemListComponent", array(
			"list" => $items,
			"config" => SOYShop_ShopConfig::load(),
			"detailLink" => SOY2PageController::createLink("Item.Detail."),
			"itemOrderDAO" => SOY2DAOFactory::create("order.SOYShop_ItemOrderDAO")
		));

		DisplayPlugin::toggle("has_recommend", (count($items) > 0));
		DisplayPlugin::toggle("no_recommend", (count($items) === 0));
	}
	
	function setConfigObj($configObj){
		$this->configObj = $configObj;
	}
}
?>