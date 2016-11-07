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
		

		$dao = SOY2DAOFactory::create("plugin.SOYShop_PluginConfigDAO");
    	$logic = SOY2Logic::createInstance("logic.plugin.SOYShopPluginLogic");

		try{
	    	$this->module = $dao->getByPluginId($plugin);

			SOYShopPlugin::load("soyshop.user.export", $this->module);
			$delegate = SOYShopPlugin::invoke("soyshop.user.export", array(
				"mode" => "export"
			));

			$delegate->export(self::getUsers());

		}catch(Exception $e){
			//
		}

		exit;
    }

	private function getUsers(){
		//検索用のロジック作成
		$searchLogic = SOY2Logic::createInstance("logic.user.SearchUserLogic");

		$search = (isset($_POST["search"])) ? $_POST["search"] : array();

		//検索条件の投入と検索実行
		$searchLogic->setSearchCondition($search);
		return $searchLogic->getUsers();
	}
}
?>