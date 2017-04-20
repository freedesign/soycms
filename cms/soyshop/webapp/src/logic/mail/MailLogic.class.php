<?php
SOY2::import("domain.config.SOYShop_ServerConfig");

class MailLogic extends SOY2LogicBase{

	private $serverConfig;
	private $shopConfig;
	private $send;
	private $receive;
	private $moduleList = array();
	private $attributeList = array();

	function getServerConfig() {
		return $this->serverConfig;
	}
	function setServerConfig($serverConfig) {
		$this->serverConfig = $serverConfig;
	}
	function getSend() {
		return $this->send;
	}
	function setSend($send) {
		$this->send = $send;
	}
	function getReceive() {
		return $this->receive;
	}
	function setReceive($receive) {
		$this->receive = $receive;
	}

	/**
	 * pop
	 */
	function prepare(){
		$serverConfig = $this->serverConfig;

		if($serverConfig->getIsUsePopBeforeSMTP()){
			if($serverConfig->getReceiveServerType() != SOYShop_ServerConfig::RECEIVE_SERVER_TYPE_POP
			&& $serverConfig->getReceiveServerType() != SOYShop_ServerConfig::RECEIVE_SERVER_TYPE_IMAP
			){
				throw new Exception("invalid receive server type");
			}

			//before smtp
			$this->receive = $serverConfig->createReceiveServerObject();
			$this->receive->open();
			$this->receive->close();
		}
	}

	/**
	 * 準備
	 */
	function prepareSend(){

		$serverConfig = SOYShop_ServerConfig::load();
		$this->serverConfig = $serverConfig;

		$this->prepare();

		//SOY2Mail
		$this->send = $serverConfig->createSendServerObject();

	}

    /**
	 * 一通送信する
	 */
	function sendMail($sendTo, $title, $body, $sendToName = "", $order = null, $orderFlag = false){
		
		//ダミーのメールアドレスには送信しない
		if(defined("DUMMY_MAIL_ADDRESS_DOMAIN") && strpos($sendTo, "@" . DUMMY_MAIL_ADDRESS_DOMAIN) !== false) return;
		
		if(is_null($this->send)){
			$this->prepareSend();
		}

		//リセット
		$this->reset();

		//文字コード
		$encoding = $this->serverConfig->getEncodingByEmailAddress($sendTo);
		$this->send->setEncoding($encoding);
		$this->send->setSubjectEncoding($encoding);

		//送信元アドレス設定
		$this->send->setFrom($this->serverConfig->getAdministratorMailAddress(), $this->serverConfig->getAdministratorName());
		if(strlen($this->serverConfig->getReturnMailAddress()) > 0){
			$replyTo = new SOY2Mail_MailAddress($this->serverConfig->getReturnMailAddress(), $this->serverConfig->getReturnName(), $encoding);
			$this->send->setHeader("Reply-To", $replyTo->getString());
		}


		$this->send->setSubject($title);
		$this->send->setText($body);

		$this->send->addRecipient($sendTo, $sendToName);

		//管理者にコピーを送る設定の時
		if($this->serverConfig->isSendWithAdministrator() && $sendTo != $this->serverConfig->getAdministratorMailAddress()){
			$this->send->addBccRecipient($this->serverConfig->getAdministratorMailAddress(), $this->serverConfig->getAdministratorName());
		}
		
		//管理側に送るメールのフラグ
		$isAdmin = false;

		//追加の送信先
		if($sendTo != $this->serverConfig->getAdministratorMailAddress()){
			//ユーザー向けメールの時
			$additionalEmails = $this->serverConfig->getAdditionalMailAddressForUserMailArray();
		}else{
			//管理者向けメールの時
			$additionalEmails = $this->serverConfig->getAdditionalMailAddressForAdminMailArray();
			$isAdmin = true;

			//別アプリとの連携→メールの送り先を増やす
			SOYShopPlugin::load("soyshop.add.mailaddress");
			$delegate = SOYShopPlugin::invoke("soyshop.add.mailaddress", array(
				"order" => $order,
				"orderFlag" => $orderFlag
			));
			$addEmails = $delegate->getMailAddress();
			if(is_array($addEmails) && count($addEmails) > 0){
				$additionalEmails = array_merge($additionalEmails, $addEmails);
			}
		}
		if(is_array($additionalEmails) && count($additionalEmails)){
			foreach($additionalEmails as $email){
				if(strlen(trim($email)) && strpos($email, "@") !== false){
					$this->send->addBccRecipient(trim($email));
				}
			}
		}
		
		//メールログ用に送信先のすべてのメールアドレスを一つの配列にまとめておく
		array_unshift($additionalEmails, $sendTo);
		
		try{
			$this->send->send();
			$isSuccess = true;
		}catch(Exception $e){
			$isSuccess = false;
		}
		
		//メールの送信状況を記録する
		$this->saveLog($isSuccess, $isAdmin, $additionalEmails, $title, $body, $order);
	}
	
	//メールの送信状況を記録する
	function saveLog($isSuccess, $isAdmin, $mails, $title, $body, $order = null){
		
		$logDao = SOY2DAOFactory::create("logging.SOYShop_MailLogDAO");
		
		$log = new SOYShop_MailLog();
		$orderId = (!is_null($order)) ? $order->getId() : null;
		$userId = (!is_null($order) && $isAdmin === false) ? $order->getUserId() : null;
		
		//マイページの方で、メールアドレスからユーザIDの取得を一度だけ試してみる
		if(is_null($orderId) && is_null($userId)){
			$userDao = SOY2DAOFactory::create("user.SOYShop_UserDAO");
			try{
				$user = $userDao->getByMailAddress($mails[0]);
			}catch(Exception $e){
				$user = new SOYShop_User();
			}
			$userId = $user->getId();
		}
		
		if(count($mails) > 0){
			$log->setRecipient(implode(",", $mails));
			$log->setOrderId($orderId);
			$log->setUserId($userId);
			$log->setTitle($title);
			$log->setContent($body);
			$log->setIsSuccess($isSuccess);
			$log->setSendDate(time());
			
			try{
				$logDao->insert($log);
			}catch(Exception $e){
				//
			}
		}
	}

	/**
	 * 件名、本文、受信者、BCC受信者、ヘッダー、添付ファイルをリセット
	 * SOY2MailはsetTextだけではencodedTextが上書きされない
	 */
	function reset(){
		$this->send->clearSubject();
		$this->send->clearText();
		$this->send->clearRecipients();
		$this->send->clearBccRecipients();
		$this->send->clearHeaders();
		//1.6.1現時点で添付ファイルの仕様はないのでコメントアウトをしておく
//		$this->send->clearAttachments();
	}


	/**
	 * @return boolean
	 */
	public function isValidEmail($email){
		$ascii  = '[a-zA-Z0-9!#$%&\'*+\-\/=?^_`{|}~.]';//'[\x01-\x7F]';
		$domain = '(?:[-a-z0-9]+\.)+[a-z]{2,10}';//'([-a-z0-9]+\.)*[a-z]+';
		$d3     = '\d{1,3}';
		$ip     = $d3.'\.'.$d3.'\.'.$d3.'\.'.$d3;
		$validEmail = "^$ascii+\@(?:$domain|\\[$ip\\])$";

		if(! preg_match('/'.$validEmail.'/i', $email) ) {
			return false;
		}

    	return true;
	}

	/**
	 * ユーザに送信するメール設定の取得
	 */
	function getUserMailConfig($type = null){
		if(is_null($type) || strlen($type) < 1) $type = "order";
		
		SOYShopPlugin::load("soyshop.mail.config");
		$delegate = SOYShopPlugin::invoke("soyshop.mail.config",array(
			"mode" => "send",
			"target" => "user",
			"type" => $type
		));
		$config = $delegate->getConfig();
		if(!is_null($config) && is_array($config) && (isset($config["title"]) && strlen($config["title"]))){
			return $config;
		}else{
			return array(
				"active" => SOYShop_DataSets::get("mail.user.$type.active", 1),
				"title"  => SOYShop_DataSets::get("mail.user.$type.title", "soyshop"),
		    	"header" => SOYShop_DataSets::get("mail.user.$type.header", ""),
		    	"footer" => SOYShop_DataSets::get("mail.user.$type.footer", ""),
		    	"output" => SOYShop_DataSets::get("mail.user.$type.output", 1)
		    );
		}
	}

	/**
	 * 管理者に送信するメール設定の取得
	 * 互換性維持のため、order以外のメールはなければorderを取るようにしておく
	 */
	function getAdminMailConfig($type = null){
		if(is_null($type) || strlen($type) < 1) $type = "order";
		
		SOYShopPlugin::load("soyshop.mail.config");
		$delegate = SOYShopPlugin::invoke("soyshop.mail.config",array(
			"mode" => "send",
			"target" => "admin",
			"type" => $type
		));
		$config = $delegate->getConfig();
		if(!is_null($config) && is_array($config) && (isset($config["title"]) && strlen($config["title"]))){
			return $config;
		}else{
			if("order" == $type){
				return array(
					"active" => SOYShop_DataSets::get("mail.admin.active", 1),
					"title"  => SOYShop_DataSets::get("mail.admin.title", "[SOY Shop]"),
					"header" => SOYShop_DataSets::get("mail.admin.header", ""),
					"footer" => SOYShop_DataSets::get("mail.admin.footer", ""),
					"output" => SOYShop_DataSets::get("mail.admin.output", 1)
				);
			}else{
				return array(
					"active" => SOYShop_DataSets::get("mail.admin.$type.active",SOYShop_DataSets::get("mail.admin.active",1)),
					"title"  => SOYShop_DataSets::get("mail.admin.$type.title", SOYShop_DataSets::get("mail.admin.title","[SOY Shop]")),
					"header" => SOYShop_DataSets::get("mail.admin.$type.header",SOYShop_DataSets::get("mail.admin.header","")),
					"footer" => SOYShop_DataSets::get("mail.admin.$type.footer",SOYShop_DataSets::get("mail.admin.footer","")),
					"output" => SOYShop_DataSets::get("mail.admin.$type.output",SOYShop_DataSets::get("mail.admin.output",""))
				);
			}
		}
	}

	/**
	 * マイページで送信するメール設定の取得
	 */
	function getMyPageMailConfig($type = null){
		if(is_null($type) || strlen($type) < 1) $type = "remind";

		SOYShopPlugin::load("soyshop.mail.config");
		$delegate = SOYShopPlugin::invoke("soyshop.mail.config",array(
			"mode" => "send",
			"target" => "mypage",
			"type" => $type
		));
		$config = $delegate->getConfig();
		if(!is_null($config) && is_array($config) && (isset($config["title"]) && strlen($config["title"]))){
			return $config;
		}else{
			return array(
				"active" => SOYShop_DataSets::get("mail.mypage.$type.active",1),
				"title"  => SOYShop_DataSets::get("mail.mypage.$type.title", "[SOY Shop]"),
				"header" => SOYShop_DataSets::get("mail.mypage.$type.header",""),
				"footer" => SOYShop_DataSets::get("mail.mypage.$type.footer",""),
				"output" => SOYShop_DataSets::get("mail.mypage.$type.output",1)
			);
		}
	}


	/**
	 * ユーザに送信するメール設定の保存
	 */
	function setUserMailConfig($mail, $type = null){
		if(is_null($type) || strlen($type) < 1) $type = "order";
		if(isset($mail["active"]))SOYShop_DataSets::put("mail.user.$type.active",$mail["active"]);
		if(isset($mail["title"])) SOYShop_DataSets::put("mail.user.$type.title", $mail["title"]);
		if(isset($mail["header"]))SOYShop_DataSets::put("mail.user.$type.header",$mail["header"]);
	    if(isset($mail["footer"]))SOYShop_DataSets::put("mail.user.$type.footer",$mail["footer"]);
	    if(isset($mail["output"]))SOYShop_DataSets::put("mail.user.$type.output",$mail["output"]);
	}
	/**
	 * 管理者に送信するメール設定の保存
	 */
	function setAdminMailConfig($mail, $type = null){
		if(is_null($type) || strlen($type) < 1) $type = "order";
		if("order" == $type){
			if(isset($mail["active"]))SOYShop_DataSets::put("mail.admin.active",$mail["active"]);
			if(isset($mail["title"])) SOYShop_DataSets::put("mail.admin.title", $mail["title"]);
			if(isset($mail["header"]))SOYShop_DataSets::put("mail.admin.header",$mail["header"]);
			if(isset($mail["footer"]))SOYShop_DataSets::put("mail.admin.footer",$mail["footer"]);
			if(isset($mail["output"]))SOYShop_DataSets::put("mail.admin.output",$mail["output"]);
		}else{
			if(isset($mail["active"]))SOYShop_DataSets::put("mail.admin.$type.active",$mail["active"]);
			if(isset($mail["title"])) SOYShop_DataSets::put("mail.admin.$type.title", $mail["title"]);
			if(isset($mail["header"]))SOYShop_DataSets::put("mail.admin.$type.header",$mail["header"]);
			if(isset($mail["footer"]))SOYShop_DataSets::put("mail.admin.$type.footer",$mail["footer"]);
			if(isset($mail["output"]))SOYShop_DataSets::put("mail.admin.$type.footer",$mail["output"]);
		}
	}

	/**
	 * マイページのメール設定の保存
	 */
	function setMyPageMailConfig($mail, $type = null){
		if(is_null($type) || strlen($type) < 1) $type = "remind";
		if(isset($mail["active"]))SOYShop_DataSets::put("mail.mypage.$type.active",$mail["active"]);
		if(isset($mail["title"])) SOYShop_DataSets::put("mail.mypage.$type.title", $mail["title"]);
		if(isset($mail["header"]))SOYShop_DataSets::put("mail.mypage.$type.header",$mail["header"]);
    	if(isset($mail["footer"]))SOYShop_DataSets::put("mail.mypage.$type.footer",$mail["footer"]);
    	if(isset($mail["output"]))SOYShop_DataSets::put("mail.mypage.$type.output",$mail["output"]);
	}
	
	/**
	 * メール本文を置換
	 */
	function convertMailContent($content, SOYShop_User $user, SOYShop_Order $order){
		
		$this->moduleList = $order->getModuleList();
		$this->attributeList = $order->getAttributeList();
		
		//ユーザー情報
		$content = str_replace("#NAME#", $user->getName(), $content);
		$content = str_replace("#READING#", $user->getReading(), $content);
		$content = str_replace("#MAILADDRESS#", $user->getMailAddress(), $content);
		$content = str_replace("#BIRTH_YEAR#", $user->getBirthdayYear(), $content);
		$content = str_replace("#BIRTH_MONTH#", $user->getBirthdayMonth(), $content);
		$content = str_replace("#BIRTH_DAY#", $user->getBirthdayDay(), $content);

		//注文情報
		$content = str_replace("#ORDER_RAWID#", $order->getId(), $content);
		$content = str_replace("#ORDER_ID#", $order->getTrackingNumber(), $content);
		$config = $this->getShopConfig();
		if(!$config){
			SOY2::import("domain.config.SOYShop_ShopConfig");
			$config = SOYShop_ShopConfig::load();
			$this->setShopConfig($config);
		}
		
		$content = str_replace("#ORDER_DATE#", date("Y-m-d H:i:s", $order->getOrderDate()), $content);
		$content = str_replace("#ORDER_TOTAL#", number_format($order->getPrice()), $content);
		
		//配送周り
		$content = str_replace("#DELIVERY_METHOD#", self::getDeliveryMethod(), $content);
		$content = str_replace("#DELIVERY_DATE#", self::getDeliveryValue("date"), $content);
		$content = str_replace("#DELIVERY_TIME#", self::getDeliveryValue("time"), $content);

		//送料
		$content = str_replace("#POSTAGE#", self::getPostage(), $content);
		
		//代引手数料
		$content = str_replace("#DAIBIKI_FEE#", self::getDaibikiFee(), $content);
		
		/** メール送信日関連 **/
		$content = str_replace("#SEND_MAIL_YEAR#", date("Y"), $content);	//年
		$content = str_replace("#SEND_MAIL_MONTH#", date("m"), $content);	//月
		$content = str_replace("#SEND_MAIL_DATE#", date("d"), $content);	//日
		
		/** プラグイン周り　**/
		SOY2::import("util.SOYShopPluginUtil");
		if(SOYShopPluginUtil::checkIsActive("slip_number")){
			$content = str_replace("#SLIP_NUMBER#", SOY2Logic::createInstance("module.plugins.slip_number.logic.SlipNumberLogic")->getAttribute($order->getId())->getValue1(), $content);
		}
		
		$content = str_replace("#SHOP_NAME#", $config->getShopName(), $content);

		$company = $config->getCompanyInformation();
    	foreach($company as $key => $value){
    		$content = str_replace(strtoupper("#COMPANY_" . $key ."#"), $value, $content);
    	}

		$adminUrl = $config->getAdminUrl();
		if(false === strpos($adminUrl, "http")){
			$adminUrl = "http://" . $_SERVER["SERVER_NAME"] . $adminUrl;
		}

    	$content = str_replace("#SITE_URL#", soyshop_get_site_url(true), $content);
    	$content = str_replace("#ADMIN_URL#", $adminUrl, $content);
    	//$content = str_replace("#ADMIN_URL#",SOY2PageController::createRelativeLink("index.php", true),$content);

		//最初に改行が存在した場合は改行を削除する
		return trim($content);
	}
	
	private function getDeliveryMethod(){
		$moduleId = self::getSelectedDeliveryModuleId();
		return (isset($this->attributeList[$moduleId]["value"])) ? $this->attributeList[$moduleId]["value"] : "";
	}
	
	private function getDeliveryValue($mode = "date"){
		$moduleId = self::getSelectedDeliveryModuleId();
		foreach($this->attributeList as $attrId => $attr){
			if(strpos($attrId, $moduleId) === 0){
				/**
				 * @ToDo プラグイン毎に動く処理を書かなければならない
				 */
				if(strpos($attrId, "." . $mode) && isset($attr["value"])){
					
					//標準配送モジュールのお届け日周りの処理
					if($mode == "date" && $moduleId == "delivery_normal" && $attr["value"] == "指定なし"){
						if(isset($_GET["type"]) && $_GET["type"]){
							SOY2::import("module.plugins.delivery_normal.util.DeliveryNormalUtil");
							$conf = DeliveryNormalUtil::getDeliveryDateConfig();
							if(isset($conf["delivery_date_mail_insert_date"]) && (int)$conf["delivery_date_mail_insert_date"] > 0){
								//return SOY2Logic::createInstance("module.plugins.delivery_normal.logic.DeliveryDateFormatLogic")->convertDateString($conf["delivery_date_format"], time() + $conf["delivery_date_mail_insert_date"] * 24 * 60 * 60);
								return date("Y-m-d", time() + $conf["delivery_date_mail_insert_date"] * 24 * 60 * 60);
							}
						}
					}
					return $attr["value"];
				}
			}
		}
		return "";
	}
	
	private function getPostage(){
		$moduleId = self::getSelectedDeliveryModuleId();
		return (isset($this->moduleList[$moduleId])) ? number_format($this->moduleList[$moduleId]->getPrice()) : 0;
	}
	
	private function getDaibikiFee(){
		$moduleId = self::getSelectedPaymentModuleId();
		return (isset($this->moduleList[$moduleId])) ? number_format($this->moduleList[$moduleId]->getPrice()) : 0;
	}
	
	private function getSelectedDeliveryModuleId(){
		static $moduleId;
		if(is_null($moduleId)){
			foreach($this->moduleList as $modId => $mod){
				if(strpos($modId, "delivery") === 0){
					$moduleId = $modId;
					break;
				}
			}
		}
		return $moduleId;
	}
	
	private function getSelectedPaymentModuleId(){
		static $moduleId;
		if(is_null($moduleId)){
			foreach($this->moduleList as $modId => $mod){
				if(strpos($modId, "payment") === 0){
					$moduleId = $modId;
					break;
				}
			}
		}
		return $moduleId;
	}

	function getShopConfig() {
		return $this->shopConfig;
	}
	function setShopConfig($shopConfig) {
		$this->shopConfig = $shopConfig;
	}
}