<?php

class CreatePage extends CMSWebPageBase{
	
	private $moduleId;
	private $moduleName;
	
	function doPost(){
		
		if(isset($_POST["Module"])){
			
			$moduleId = (isset($_POST["Module"]["id"])) ? str_replace("/", ".", $_POST["Module"]["id"]) : null;
			$this->moduleId = htmlspecialchars($moduleId);
			$this->moduleName = $_POST["Module"]["name"];
			
			if(strlen($this->moduleName) < 1) $this->moduleName = $this->moduleId;
			
			$moduleDir = self::getModuleDirectory();
		
			$modulePath = $moduleDir . str_replace(".","/",$this->moduleId) . ".php";
			$iniPath =$moduleDir . str_replace(".","/",$this->moduleId) . ".ini";
			
			if(soy2_check_token()){
				if(preg_match('/^[a-zA-Z0-9\._]+$/', $this->moduleId) &&
				   strpos($this->moduleId, ".") &&
				   !preg_match("/^common./", $this->moduleId) &&
				   !preg_match("/^html./", $this->moduleId) &&
				   !file_exists($modulePath)
				){
					@mkdir(dirname($modulePath), 0766, true);
					file_put_contents($modulePath, "<?php ?>");
					file_put_contents($iniPath,"name=" . $this->moduleName);
				
					$this->jump("Module.Editor?updated&moduleId=" . $this->moduleId);
				}
			}
		}
	}
	
	function __construct(){
		//PHPモジュールの使用が許可されていない場合はモジュール一覧に遷移
		if(!defined("SOYCMS_ALLOW_PHP_MODULE") || !SOYCMS_ALLOW_PHP_MODULE) SOY2PageController::jump("Module");
		
		WebPage::__construct();
		
		DisplayPlugin::visible("updated");
		if($this->moduleId) DisplayPlugin::visible("failed");
		
		$this->addForm("form");
		
		$this->addInput("module_id", array(
			"name" => "Module[id]",
			"value" => $this->moduleId,
			"style" => "padding: 3px; width:300px;"
		));
		
		$this->addInput("module_name", array(
			"name" => "Module[name]",
			"value" => $this->moduleName,
			"style" => "padding: 3px; width:300px;"
		));
	}
	
	private function getModuleDirectory(){
		return UserInfoUtil::getSiteDirectory() . ".module/";
	}
}
?>