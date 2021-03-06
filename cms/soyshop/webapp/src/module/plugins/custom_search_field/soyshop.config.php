<?php
class CustomSearchFieldConfig extends SOYShopConfigPageBase{

    /**
     * @return string
     */
    function getConfigPage(){
        if(isset($_GET["eximport"])){
          include_once(dirname(__FILE__) . "/config/CustomSearchExImportPage.class.php");
          $form = SOY2HTMLFactory::createInstance("CustomSearchExImportPage");
        //一括設定画面
        }else if(isset($_GET["collective"])){
          include_once(dirname(__FILE__) . "/config/collective/SettingPage.class.php");
          $form = SOY2HTMLFactory::createInstance("SettingPage");
        //検索の設定画面
        }else if(isset($_GET["config"])){
          include_once(dirname(__FILE__) . "/config/search/CustomSearchConfigPage.class.php");
          $form = SOY2HTMLFactory::createInstance("CustomSearchConfigPage");
        //カテゴリカスタムフィールド
        }else if(isset($_GET["category"])){
          include_once(dirname(__FILE__) . "/config/category/CustomSearchFieldConfigFormPage.class.php");
          $form = SOY2HTMLFactory::createInstance("CustomSearchFieldConfigFormPage");
        //通常の設定画面
        }else{
          include_once(dirname(__FILE__) . "/config/CustomSearchFieldConfigFormPage.class.php");
          $form = SOY2HTMLFactory::createInstance("CustomSearchFieldConfigFormPage");
        }

        $form->setConfigObj($this);
        $form->execute();
        return $form->getObject();
    }

    /**
     * @return string
     */
    function getConfigPageTitle(){
        return "カスタムサーチフィールド";
    }
}
SOYShopPlugin::extension("soyshop.config", "custom_search_field", "CustomSearchFieldConfig");
?>
