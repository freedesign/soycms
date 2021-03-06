<?php
/**
 * ログイン権限があるか
 */
function soyshop_admin_login(){
	$session = SOY2ActionSession::getUserSession();

	//root user
	$root = $session->getAttribute("isdefault");
	if($root)return true;
	
	//auth level
	$level = soyshop_admin_auth_level();
	
	return ($level > 0);
}

/**
 * SOY Shopの権限レベルを取得
 */
function soyshop_admin_auth_level(){
	$session = SOY2ActionSession::getUserSession();
	$level = $session->getAttribute("app_shop_auth_level");
	
	if(is_null($level)){
		return 0;
	}else{
		return true;
	}
}

function print_update_date($time){
	if(date("Ymd") == date("Ymd",$time)){
		return date("H:i",$time);
	}

	return date("Y-m-d H:i", $time);
}

/**
 * 変数の文字列を数字に変換して返す。変数の文字列が数字でなかった場合は第二引数の値を返す
 * @param String, Integer
 * @return Integer
 */
function soyshop_convert_number($arg, $value){
	$arg = mb_convert_kana($arg, "a");
	if(strlen($arg) < 1 || !is_numeric($arg)){
		$arg = $value;
	}
	return $arg;
}

/**
 * 文字列の末尾のスラッシュを除く
 * @param String
 * @return String
 */
function soyshop_remove_close_slash($str){
	
	if(strrpos($str, "/") === strlen($str) - 1){
		$str = rtrim($str, "/");
	}
	
	return $str;
}

/**
 * 時刻からタイムスタンプへ変換
 * @param string $str, string mode:startとendがある
 * @return integer
 */
function soyshop_convert_timestamp($str, $mode = "start"){
	$array = explode("-", $str);

	if(
		(!isset($array[0]) || !isset($array[1]) || !isset($array[2])) || 
		(!is_numeric($array[0]) || !is_numeric($array[1]) || !is_numeric($array[2]))
	) {
		return ($mode == "start") ? 0 : 2147483647;
	}

	if($mode == "start"){
		return mktime(0, 0, 0, $array[1], $array[2], $array[0]);
	}else{
		return mktime(23, 59, 59, $array[1], $array[2], $array[0]);
	}
}

/**
 * タイムスタンプから時刻へ変換
 * @param integer $timestamp
 * @return string
 */
function soyshop_convert_date_string($timestamp){
	return ($timestamp == 0 || $timestamp == 2147483647) ? "" : date("Y-m-d", $timestamp);
}

?>