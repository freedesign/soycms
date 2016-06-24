<?php

class ECCUBECSVImportInfo extends SOYShopInfoPageBase{

	function getPage($active = false){

		if($active){
			return '<a href="'.SOY2PageController::createLink("Config.Detail?plugin=eccube_data_import").'">EC CUBEインポートプラグイン</a>';
		}else{
			return "";
		}
	}
}
SOYShopPlugin::extension("soyshop.info","eccube_data_import","ECCUBECSVImportInfo");
?>