<?php
class RandomBlockConfigPage extends WebPage{
	
	private $pluginObj;
	
	function __construct(){}
	
	function doPost(){}
	
	function execute(){
		WebPage::__construct();		
	}
	
	function getPluginObj() {
		return $this->pluginObj;
	}
	function setPluginObj($pluginObj) {
		$this->pluginObj = $pluginObj;
	}
}
