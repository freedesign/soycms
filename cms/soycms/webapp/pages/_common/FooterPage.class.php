<?php

class FooterPage extends CMSHTMLPageBase{
	
	var $copyRight = ""; 
	
    function __construct() {
    	HTMLPage::__construct();
    	
    	$year = date("Y", SOYCMS_BUILD_TIME);
    	if($year>2007) $year = "2007-".$year;
    	$this->copyRight = $this->getMessage("COMMON_FOOTER_COPYRIGHT", array("YEAR" => $year));
    	
    }
    
    function execute(){
    	
    	$this->createAdd("copyright","HTMLLabel",array(
    		"html" => $this->copyRight    	
    	));
    	
    }
}
?>