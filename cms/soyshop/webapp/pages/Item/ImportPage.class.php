<?php

class ImportPage extends WebPage{
    private $dao;
    private $pageDao;

    private $categories;
    private $detailPage;

    function __construct() {
        WebPage::__construct();

        DisplayPlugin::toggle("fail", isset($_GET["fail"]));
        DisplayPlugin::toggle("invalid", isset($_GET["invalid"]));

        self::buildForm();

        //多言語化
        $this->createAdd("multi_language_item_name_list", "_common.Item.MultiLanguageItemNameListComponent", array(
            "list" => self::getLanguageList()
        ));

        //特別価格プラグイン周りの項目を表示する
        $this->createAdd("special_price_list","_common.Item.SpecialPriceExportListComponent", array(
            "list" => self::getSpecialPriceList()
        ));

        $this->createAdd("customfield_list", "_common.Item.CustomFieldImExportListComponent", array(
            "list" => self::getCustomFieldList()
        ));

        //商品オプションリストを表示する
        if(!defined("ITEM_CSV_IMEXPORT_MODE")) define("ITEM_CSV_IMEXPORT_MODE", "import");
        $this->createAdd("custom_search_field_list", "_common.Item.CustomSearchFieldImExportListComponent", array(
            "list" => self::getCustomSearchFieldList(),
            "languages" => self::getLanguageList()
        ));

        //商品オプションリストを表示する
        $this->createAdd("item_option_list", "_common.Item.ItemOptionImExportListComponent", array(
            "list" => self::getItemOptionList()
        ));

        SOYShopPlugin::load("soyshop.item.csv");
        $delegate = SOYShopPlugin::invoke("soyshop.item.csv");

        $this->createAdd("plugin_list", "_common.Item.PluginCSVListComponent", array(
            "list" => $delegate->getModules()
        ));
    }

    private function buildForm(){
        $this->addForm("import_form", array(
             "ENCTYPE" => "multipart/form-data"
        ));
    }

    function doPost(){

        //check token
        if(!soy2_check_token()){
            SOY2PageController::jump("Item.Import?fail");
            exit;
        }

        set_time_limit(0);

        $file  = $_FILES["import_file"];

        $logic = SOY2Logic::createInstance("logic.shop.item.ExImportLogic");
        $format = $_POST["format"];
        $item = $_POST["item"];

        $logic->setSeparator(@$format["separator"]);
        $logic->setQuote(@$format["quote"]);
        $logic->setCharset(@$format["charset"]);
        $logic->setItems($item);
        $logic->setCustomFields($this->getCustomFieldList(true));
        $logic->setLanguageItems(self::getLanguageList());
        $logic->setSpecialPrices($this->getSpecialPriceList());
        $logic->setCustomSearchFields($this->getCustomSearchFieldList());
        $logic->setItemOptions($this->getItemOptionList());

        if(!$logic->checkUploadedFile($file)){
            SOY2PageController::jump("Item.Import?fail");
            exit;
        }
        if(!$logic->checkFileContent($file)){
            SOY2PageController::jump("Item.Import?invalid");
            exit;
        }

        //ファイル読み込み・削除
        $fileContent = file_get_contents($file["tmp_name"]);
        unlink($file["tmp_name"]);

        //データを行単位にばらす
        $lines = $logic->GET_CSV_LINES($fileContent);    //fix multiple lines

        //先頭行削除
        if(isset($format["label"])) array_shift($lines);

        //DAO
        $this->dao = SOY2DAOFactory::create("shop.SOYShop_ItemDAO");
        $this->pageDao = SOY2DAOFactory::create("site.SOYShop_PageDAO");
        $attributeDAO = SOY2DAOFactory::create("shop.SOYShop_ItemAttributeDAO");
        $config = SOYShop_ItemAttributeConfig::load(true);
        $categoryDAO = SOY2DAOFactory::create("shop.SOYShop_CategoryDAO");

        //カスタムサーチフィールド
        $customSearchFieldDBLogic = SOY2Logic::createInstance("module.plugins.custom_search_field.logic.DataBaseLogic");

        //商品詳細ページの挿入の有無
        $this->setDetailPage();

        //カテゴリのデータを取得
        $categoryLogic = SOY2Logic::createInstance("logic.shop.CategoryLogic");
        $this->categories = $categoryLogic->getCategoryMap();

        //plugin
        SOYShopPlugin::load("soyshop.item.csv");
        SOYShopPlugin::load("soyshop.item.csv.expand");
        $delegate = SOYShopPlugin::invoke("soyshop.item.csv", array("mode" => "import"));
        $logic->setModules($delegate->getModules());

        $this->dao->begin();

        //データ更新
        foreach($lines as $line){
            if(empty($line)) continue;

            list($obj,$attributes,$plugins,$customSearchFields) = $logic->import($line);

            $deleted = ($obj["id"] == "delete");

            $item = $this->import($obj);

            if(strlen($item->getCode()) > 0){

                if($item->getCategory()){
                    $categoryId = $categoryLogic->import(array("name" => $item->getCategory()));
                    $item->setCategory($categoryId);
                }

                if($deleted){
                    $this->deleteItem($item);
                }else{
                    $pageId = $this->getDetailPage($item->getDetailPageId());
                    $item->setDetailPageId($pageId);

                    $id = $this->insertOrUpdate($item);

                    foreach($attributes as $key => $value){
                        $attributeDAO->delete($id, $key);
                        $attr = new SOYShop_ItemAttribute();
                        $attr->setItemId($id);
                        $attr->setFieldId($key);

                        //商品オプションの場合、カンマ区切りにしているものを元に戻す
                        if(preg_match('/^item_option_/', $key, $tmp)){
                            $value = str_replace(",", "\r\n", $value);
                        }

                        $attr->setValue($value);

                        $attributeDAO->insert($attr);

                        if(isset($config[$key]) && method_exists($config[$key], "isIndex") && $config[$key]->isIndex()){
                            $this->customSortImport($id, $key, $value);
                        }
                    }

                    foreach($plugins as $pluginId => $value){
                        $delegate->import($pluginId, $id, $value);
                    }

                    //カスタムサーチフィールド 日本語の値で確認しておく
                    if(count($customSearchFields[UtilMultiLanguageUtil::LANGUAGE_JP])){
                        foreach($customSearchFields as $lang => $csfValues){
                            $customSearchFieldDBLogic->save($item->getId(), $csfValues, $lang);
                        }
                    }

                    //拡張の処理
                    SOYShopPlugin::invoke("soyshop.item.csv.expand", array("mode" => "expand", "itemId" => $id));
                }
            }
        }

        $this->dao->commit();

        SOY2PageController::jump("Item.Import?updated");
    }

    /**
     * CSV, TSVの一行からSOYShop_Itemを作り、返す
     *
     * 商品コードでチェックを行う
     *
     * @param String $line
     * @param Array $properties
     * @return SOYShop_Item
     */
    function import($obj){

        if(isset($obj["id"]))unset($obj["id"]);
        $item = SOY2::cast("SOYShop_Item", (object)$obj);

        try{
            $item = $this->dao->getByCode($item->getCode());
            SOY2::cast($item, (object)$obj);
        }catch(Exception $e){
            //
        }

        if(isset($obj["orderPeriodStart"])) $item->setOrderPeriodStart(soyshop_convert_timestamp($obj["orderPeriodStart"], "start"));
        if(isset($obj["orderPeriodEnd"])) $item->setOrderPeriodEnd(soyshop_convert_timestamp($obj["orderPeriodEnd"], "end"));
        if(isset($obj["openPeriodStart"])) $item->setOpenPeriodStart(soyshop_convert_timestamp($obj["openPeriodStart"], "start"));
        if(isset($obj["openPeriodEnd"])) $item->setOpenPeriodEnd(soyshop_convert_timestamp($obj["openPeriodEnd"], "end"));

        return $item;
    }


    function customSortImport($id, $key, $value){
        $dao = new SOY2DAO();

        try{
            $dao->executeQuery("update soyshop_item set custom_" . $key . " = :custom where soyshop_item.id = :id",
                array(
                    ":id" => $id,
                    ":custom" => $value
                    ));
        }catch(Exception $e){
            return false;
        }

        return true;
    }

    /**
     * 商品データの更新または挿入を実行する
     * 同じメールアドレスのユーザがすでに登録されている場合に更新を行う
     * @param SOYShop_Item
     * @return id
     */
    function insertOrUpdate(SOYShop_Item $item){
        if(strlen($item->getId())){
            $this->update($item);
            return $item->getId();
        }else{
            return $this->insert($item);
        }
    }

    /**
     * 商品データの挿入を実行する
     * @param SOYShop_Item
     */
    function insert(SOYShop_Item $item){
        try{
            $id = $this->dao->insert($item);
        }catch(Exception $e){
            var_dump($e);
        }
        return $id;
    }

    /**
     * 商品データの更新を実行する
     * @param SOYShop_Item
     */
    function update(SOYShop_Item $item){

        try{
            $this->dao->update($item);
        }catch(Exception $e){

        }
    }

    function deleteItem(SOYShop_Item $item){
        try{
            $this->dao->deleteByCode($item->getCode());
        }catch(Exception $e){

        }
    }

     private function getLanguageList(){
        static $list;

        if(is_null($list)){
            SOY2::import("module.plugins.util_multi_language.util.UtilMultiLanguageUtil");
            if(SOYShopPluginUtil::checkIsActive("util_multi_language")){
                $list = UtilMultiLanguageUtil::allowLanguages();
            }else{
                $list[UtilMultiLanguageUtil::LANGUAGE_JP] = "日本語";
            }
        }

        return $list;
    }

    private function getSpecialPriceList(){
        SOY2::import("util.SOYShopPluginUtil");
        if(!SOYShopPluginUtil::checkIsActive("member_special_price")) return array();

        SOY2::import("module.plugins.member_special_price.util.MemberSpecialPriceUtil");
        return MemberSpecialPriceUtil::getConfig();
    }

    private function getCustomFieldList($flag = false){
        $dao = SOY2DAOFactory::create("shop.SOYShop_ItemAttributeDAO");
        $config = SOYShop_ItemAttributeConfig::load($flag);
        return $config;
    }

    private function getCustomSearchFieldList(){
        SOY2::import("module.plugins.custom_search_field.util.CustomSearchFieldUtil");
        return CustomSearchFieldUtil::getConfig();
    }

    private function getItemOptionList(){
        return SOY2Logic::createInstance("module.plugins.common_item_option.logic.ItemOptionLogic")->getOptions();
    }

        /**
     * 商品詳細ページがひとつしかなかった場合に挿入する
     */
    function getDetailPage($id){
        if(empty($this->detailPage)){
            //指定されているページIDが詳細ページとして存在しているか？
            try{
                $res = $this->pageDao->executeQuery("SELECT id FROM soyshop_page WHERE id = :id AND type = 'detail' LIMIT 1;", array(":id" => $id));
                if(isset($res[0]["id"])) return $res[0]["id"];
            }catch(Exception $e){
                $res = array();
            }

            //IDの一番小さいページを取得する
            return $this->getDefaultDetailPage();
        }else{
            return $this->detailPage;
        }
    }

    /**
     * 商品詳細ページの設定
     */
    function setDetailPage(){
        $detail = $this->pageDao->getByType("detail");

        if(count($detail) > 1){
            $this->detailPage = "";
        }else{
            $key = array_keys($detail);
            $this->detailPage = (int)$detail[$key[0]]->getId();
        }
    }

    /**
     * IDが一番小さいdetailページを取得する
     */
    function getDefaultDetailPage(){
        static $pageId;

        if(is_null($pageId)){
            try{
                $res = $this->pageDao->executeQuery("SELECT id FROM soyshop_page WHERE type = 'detail' ORDER BY id ASC LIMIT 1;");
                $pageId = (isset($res[0]["id"])) ? (int)$res[0]["id"] : 0;
            }catch(Exception $e){
                $pageId = 0;
            }
        }

        return ($pageId > 0) ? $pageId : null;
    }
}
