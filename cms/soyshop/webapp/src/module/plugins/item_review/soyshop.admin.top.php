<?php
class ItemReviewAdminTop extends SOYShopAdminTopBase{

	function getLink(){
		return SOY2PageController::createLink("Review");
	}
	
	function getLinkTitle(){
		return "レビュー一覧";
	}

	function getTitle(){
		return "新着のレビュー";
	}

	function getContent(){
		SOY2::import("module.plugins.item_review.page.ItemReviewAreaPage");
		$form = SOY2HTMLFactory::createInstance("ItemReviewAreaPage");
		$form->setConfigObj($this);
		$form->execute();
		return $form->getObject();
	}
}
SOYShopPlugin::extension("soyshop.admin.top", "item_review", "ItemReviewAdminTop");
?>