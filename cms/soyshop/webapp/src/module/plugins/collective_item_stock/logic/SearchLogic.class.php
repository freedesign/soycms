<?php

class SearchLogic extends SOY2LogicBase{
	
	private $itemDao;
	
	private $limit;
	
	private $where = array();
	private $binds = array();
	
	function __construct(){
		$this->itemDao = SOY2DAOFactory::create("shop.SOYShop_ItemDAO");
	}
	
	function get(){
		
		$sql = self::buildQuery();
		
		try{
			$res = $this->itemDao->executeQuery($sql, $this->binds);
		}catch(Exception $e){
			var_dump($e);
			$res = array();
		}
		
		if(!count($res)) return array();
		
		$items = array();
		foreach($res as $v){
			$items[] = $this->itemDao->getObject($v);
		}
		
		return $items;
	}
	
	private function buildQuery(){
		$sql = "SELECT * FROM soyshop_item ".
				"WHERE is_disabled != " . SOYShop_Item::IS_DISABLED . " ";
		
		foreach($this->where as $where){
			$sql .= " AND " . $where;
		}

		$sql .= " LIMIT " . $this->limit;
				
		return $sql;
	}
	
	function setCondition($conditions){
		if(count($conditions)) foreach($conditions as $key => $value){
			switch($key){
				//カテゴリーの場合は数字を直接指定
				case "item_category":
					$this->where[] = $key . " = :" . $key;
					$this->binds[":" . $key] = (int)$value;
					break;
				case "item_is_open":
					if(count($value)){
						$this->where[] = $key . " IN (" . implode(",", $value) . ") ";
					}
					break;
				case "item_type":
					//何もしない
					break;
				default:
					$this->where[] = $key . " LIKE :" . $key;
					$this->binds[":" . $key] = "%" . trim($value) . "%";
			}
		}
		
		//小商品の扱い
		if(isset($conditions["item_type"]["child"])){
			//何もしない
		}else{
			$this->where[] = "item_type IN (\"" . SOYShop_Item::TYPE_SINGLE ."\",\"" . SOYShop_Item::TYPE_GROUP . "\",\"" . SOYShop_Item::TYPE_DOWNLOAD . "\")";
		}
	}
	
	function setLimit($limit){
		$this->limit = $limit;
	}
}
?>