<?php

class FileUploadPage extends CMSWebPageBase {

	function doPost(){
		
		$action = SOY2ActionFactory::createInstance("SiteConfig.DetailAction");
		$result = $action->run();
		$entity = $result->getAttribute("entity");
				
		$res = $this->run("Entry.UploadFileAction", array("maxWidth" => $entity->getDefaultUploadResizeWidth()));
		echo json_encode($res->getAttribute("result"));
		exit;
	}
    function __construct($arg) {
    	WebPage::__construct();
		
		$this->createAdd("jqueryjs","HTMLModel",array(
			"type" => "text/JavaScript",
			"src" => SOY2PageController::createRelativeLink("./js/jquery.js")
		));
		$this->createAdd("jqueryuijs","HTMLModel",array(
			"type" => "text/JavaScript",
			"src" => SOY2PageController::createRelativeLink("./js/jquery-ui.min.js")
		));
		$this->createAdd("commonjs","HTMLModel",array(
			"type" => "text/JavaScript",
			"src" => SOY2PageController::createRelativeLink("./js/common.js")
		));
		
		$this->createAdd("applyForm","HTMLForm",array(
			"action"=>SOY2PageController::createLink("Entry.Editor.UploadApply")
		));
		
		$this->createAdd("cancelForm","HTMLForm",array(
			"action"=>SOY2PageController::createLink("Entry.Editor.UploadCancel")
		));
		
		$this->createAdd("uploadForm","HTMLForm");
		
		$this->createAdd("parameters","HTMLScript",array(
			"lang" => "text/JavaScript",
			"script" => 'var remotoURI = "'.UserInfoUtil::getSiteURL().substr($this->getDefaultUpload(),1).'";'
		));
		
		$this->createAdd("file_manager_iframe","HTMLModel",array(
			"target_src"=>SOY2PageController::createLink("FileManager.File")
		));
    }
    
    function getDefaultUpload(){
    	
    	$dao = SOY2DAOFactory::create("cms.SiteConfigDAO");
    	$config = $dao->get();
    	$dir = $config->getUploadDirectory();
    	
    	return $dir;
    }
}
?>