SOY CMSとご利用中のSOY Appのバージョンアップは必ず行ってください。  
SOY Shopをバージョンアップする際、カートとマイページでカスタマイズしている場合は下記の修正を行ってください。  
  
クレジットカード支払いモジュール等の後から設置したモジュールを利用している場合は、  
モジュールの管理画面やモジュールからHTMLが出力されているページでエラーが発生することがあります。  
  
エラーが発生した場合は、該当するモジュール内のPHPファイルで、WebPage::WebPage();と書かれている箇所をWebPage::__construct();に変更してください。  
  
修正箇所がわからなければ、[saitodev.co](https://saitodev.co "saitodev.co")のお問い合わせフォームからご連絡ください。  
  
/** @ToDo 修正方法を記載する **/
