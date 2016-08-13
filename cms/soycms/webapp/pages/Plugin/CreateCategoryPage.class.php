<?php

class CreateCategoryPage extends CMSWebPageBase{

	function doPost(){
    	if(soy2_check_token()){
			$this->run("Plugin.CreateCategoryAction");
			$this->jump("Plugin");
    	}
	}

    function __construct() {
    	WebPage::__construct();
    	$this->jump("Plugin");
    }
}
?>