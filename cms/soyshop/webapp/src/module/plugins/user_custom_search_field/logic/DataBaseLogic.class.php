<?php

class DataBaseLogic extends SOY2LogicBase{
	
	function __construct(){
		SOY2::import("module.plugins.user_custom_search_field.util.UserCustomSearchFieldUtil");
	}
	
	function addColumn($key, $type){
		if(!preg_match("/^[[0-9a-zA-Z-_]+$/", $key)) return false;
		
		$sql = "ALTER TABLE soyshop_user_custom_search ADD COLUMN " . $key . " ";
		
		switch($type){
			case UserCustomSearchFieldUtil::TYPE_INTEGER:
			case UserCustomSearchFieldUtil::TYPE_RANGE:
				$sql .= "INTEGER";
				break;
			case UserCustomSearchFieldUtil::TYPE_TEXTAREA:
			case UserCustomSearchFieldUtil::TYPE_RICHTEXT:
				$sql .= "TEXT";
				break;
			default:
				$sql .= "VARCHAR(255)";
		}
		
		$dao = new SOY2DAO();
				
		try{
			$dao->executeUpdateQuery($sql, array());
		}catch(Exception $e){
			return false;
		}
		
		return true;
	}
	
	function deleteColumn($key){
		if(!preg_match("/^[[0-9a-zA-Z-_]+$/", $key)) return;
				
		$dao = new SOY2DAO();
		try{
			$dao->executeUpdateQuery("ALTER TABLE soyshop_user_custom_search DROP COLUMN " . $key, array());
		}catch(Exception $e){
			//SQLiteではカラムを削除できない
		}
	}
	
	/**
	 * @params itemId integer, values array(array("field_id" => string))
	 */
	function save($userId, $values){
		
		$sets = array();
		
		foreach(UserCustomSearchFieldUtil::getConfig() as $key => $field){
			if(!isset($values[$key])) {
				$sets[$key] = null;
				continue;
			}
			
			switch($field["type"]){
				case UserCustomSearchFieldUtil::TYPE_INTEGER:
				case UserCustomSearchFieldUtil::TYPE_RANGE:
					$sets[$key] = (is_numeric($values[$key])) ? (int)$values[$key] : null;
					break;
				case UserCustomSearchFieldUtil::TYPE_CHECKBOX:
					if(is_array($values[$key]) && count($values[$key])){
						$sets[$key] = implode(",", $values[$key]);
						
					//一括更新の際は、そのまま値を入れなければならない
					}elseif(strpos($values[$key], ",")){
						$sets[$key] = $values[$key];
						
					}else{
						$sets[$key] = null;
					}
					break;
				default:
					$sets[$key] = (strlen($values[$key])) ? $values[$key] : null;
			}
		}
		$this->insert($userId, $sets);
	}
	
	function insert($userId, $sets){		
		$columns = array();
		$values = array();
		$binds = array();
		
		$columns[] = "user_id";
		$values[] = (int)$userId;
		
		foreach($sets as $key => $value){
			$columns[] = $key;
			$values[] = ":" . $key;
			$binds[":" . $key] = $value;
		}
		
		$sql = "INSERT INTO soyshop_user_custom_search ".
				"(" . implode(",", $columns) . ") ".
				"VALUES (" . implode(",", $values) . ")";
		
		$dao = new SOY2DAO();
		
		try{
			$dao->executeQuery($sql, $binds);
		}catch(Exception $e){
			$this->update($userId, $columns, $values, $binds);
		}
	}
	
	function update($userId, $columns, $values, $binds){
		$sql = "UPDATE soyshop_user_custom_search SET ";
		$first = true;
		foreach($columns as $i => $column){
			if($column == "user_id") continue;
			if(!$first) $sql .= ", ";
			$first = false;
			$sql .= $column . " = " . $values[$i];
		}
		$sql .= " WHERE user_id = " . $userId;
		$dao = new SOY2DAO();
		try{
			$dao->executeUpdateQuery($sql, $binds);
		}catch(Exception $e){
			//
		}
	}
	
	function delete($userId){
		$dao = new SOY2DAO();
		try{
			$dao->executeQuery("DELETE FROM soyshop_user_custom_search WHERE user_id = :user_id", array(":user_id" => $userId));
		}catch(Exception $e){
			//
		}
	}
	
	
	function migrate(){
		$dao = new SOY2DAO();
		
		//すべての商品番号を取得する
		try{
			$results = $dao->executeQuery("SELECT id FROM soyshop_user");
		}catch(Exception $e){
			$results = array();
		}
		
		if(!count($results)) return false;
		$attrDao = SOY2DAOFactory::create("shop.SOYShop_UserAttributeDAO");
		$configs = SOYShop_UserAttributeConfig::load(true);
		$keys = array_keys($configs);
		
		$keysTmp = array();
		foreach($keys as $key){
			if($configs[$key]->getType() == "image" || $configs[$key]->getType() == "file" || $configs[$key]->getType() == "link"){
				//何もしない
			}else{
				$keysTmp[] = $key;
			}
		}
		$keys = $keysTmp;
		unset($configs);
		unset($keysTmp);
		
		if(!count($keys)) return false;
		
		//カスタムサーチフィールドの方のフィールドID
		$skeys = array_keys(UserCustomSearchFieldUtil::getConfig());
		
		foreach($results as $v){
			if(isset($v["id"]) && is_numeric($v["id"])){
				$userId = (int)$v["id"];
				try{
					$attrs = $attrDao->getByUserId($userId);
				}catch(Exception $e){
					continue;
				}
				
				if(!count($attrs)) continue;
				
				
				$sets = array();
				foreach($attrs as $key => $attr){
					if(array_search($key, $skeys) === false) continue;	//0を含むためにfalseにした
					if(array_search($key, $keys) !== false && strlen($attr->getValue())) $sets[$key] = $attr->getValue();	
				}
												
				$this->insert($userId, $sets);
			}
		}
				
		return true;
	}
	
	function getByUserId($userId){
		$dao = new SOY2DAO();
		try{
			$res = $dao->executeQuery("SELECT * FROM soyshop_user_custom_search WHERE user_id = :user_id LIMIT 1", array(":user_id" => $userId));
		}catch(Exception $e){
			return array();
		}
		
		return (isset($res[0])) ? $res[0] : array();
	}
}
?>