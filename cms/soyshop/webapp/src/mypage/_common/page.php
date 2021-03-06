<?php
/*
 * Created on 2010/04/26
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
include_once(dirname(__FILE__) . "/classes.php");
SOY2HTMLConfig::PageDir(dirname(dirname(__FILE__)). "/" . SOYSHOP_CURRENT_MYPAGE_ID . "/pages/");

//マイページのテンプレートの設定
$templateDir = SOYSHOP_SITE_DIRECTORY . ".template/mypage/" . SOYSHOP_CURRENT_MYPAGE_ID . "/";
define("SOYSHOP_DEFAULT_MYPAGE_TEMPLATE_DIR", soy2_realpath(dirname(dirname(__FILE__))."/".SOYSHOP_CURRENT_MYPAGE_ID) . "pages/");
if(file_exists($templateDir)){
    define("SOYSHOP_MAIN_MYPAGE_TEMPLATE_DIR", $templateDir);
}else{
    define("SOYSHOP_MAIN_MYPAGE_TEMPLATE_DIR", SOYSHOP_DEFAULT_MYPAGE_TEMPLATE_DIR);
}

//マイページロジックの設定
MyPageLogic::getMyPage(SOYSHOP_CURRENT_MYPAGE_ID);

//最初はMYPAGE_IDの方を調べて、なければ_commonの方を調べる
$i = 0;
do{
    if(SOY2HTMLFactory::pageExists($htmlObj->createPagePath(true) . "Page")){
        //Hoge.IndexPage
        $path = $htmlObj->createPagePath(true) . "Page";
    }else{
        //HogePage
        $path = $htmlObj->createPagePath() . "Page";

        //MYPAGE_IDの方で無かったので、_commonの方を調べるように設定変更
        if(!SOY2HTMLFactory::pageExists($path)) {
            SOY2HTMLConfig::PageDir(dirname(__FILE__).  "/pages/");
            continue;
        }
    }
}while($i++ < 1);

$path = MyPageLogic::convertPath($path);
define("SOYSHOP_MYPAGE_PATH", $path);

try{
    $page = SOY2HTMLFactory::createInstance($path, array("arguments" => $args));
}catch(Exception $e){
    $page = SOY2HTMLFactory::createInstance("ErrorPage", array("arguments" => $args));
}

$page->buildModules();
$page->display();
