<?php

class ExportPage extends WebPage{

    function __construct() {
    	
    	//ログインチェック	ログインしていなければ強制的に止める
		if(!soyshop_admin_login()){
			echo "invalid plugin id";
			exit;
		}

		$plugin = (isset($_POST["plugin"])) ? $_POST["plugin"] : null;
		if(is_null($plugin)){
			echo "invalid plugin id";
			exit;
		}
		
		$search = array();
		if(isset($_POST["search"])){
			parse_str($_POST["search"], $search);
		}			
		$_POST["search"] = $search;

		//統計、集計プラグインの場合は、注文一覧の検索を無視する
		if(strpos($plugin, "aggregate") !== false || strpos($plugin, "analytics") !== false ){
			$orders = array();
		}else{
			$orders = self::getOrders();
		}
		

		$dao = SOY2DAOFactory::create("plugin.SOYShop_PluginConfigDAO");
    	$logic = SOY2Logic::createInstance("logic.plugin.SOYShopPluginLogic");

		try{
	    	$this->module = $dao->getByPluginId($plugin);

			SOYShopPlugin::load("soyshop.order.export", $this->module);
			$delegate = SOYShopPlugin::invoke("soyshop.order.export", array(
				"mode" => "export"
			));

			$delegate->export($orders);

		}catch(Exception $e){
			//
		}

		exit;
    }

    private function getOrders(){
		//検索用のロジック作成
		$searchLogic = SOY2Logic::createInstance("logic.order.SearchOrderLogic");

		$search = (isset($_POST["search"])) ? $_POST["search"] : array();

		//検索条件の投入と検索実行
		$searchLogic->setSearchCondition($search);
		return $searchLogic->getOrders();
    }
}
?>