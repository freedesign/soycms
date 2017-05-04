<form method="post">

<div class="soy_inquiry_message">
	<?php $message = $config->getMessage(); echo $message["confirm"]; ?>
</div>

<div class="soy_iqnuiry_responsive">
	<dl>
<?php foreach($columns as $column){ 
	
	$id = $column->getId();
	$obj = $column->getColumn();
	$label = $obj->getLabel();
	$view = $obj->getView();
	
	if(strlen($view) < 1) continue;
		
	if(strlen($label) > 0 && strlen($view) > 0){
		echo "<dt>";
		echo $label;
		echo "</dt>";
		echo "<dd>";
	    echo $view;
	    echo "</dd>";
	}
}    
?>
	</dl>
</div>

<?php
echo $hidden_forms;
?>

<?php if($config->getIsUseCaptcha()){ ?>
<div id="inquiry_form_captcha">
	<img src="<?php echo $captcha_url; ?>" />

	<div>
		<input type="text" name="captcha_value" value="" />
		表示されてる画像の文字(半角英数字大文字)を入力してください。
	</div>
</div>
<?php } ?>

<div style="margin-top:10px;text-align:center;">
	<input name="send" type="submit" value="送信" >
	<input name="form" type="submit" value="戻る" >
</div>

</form>