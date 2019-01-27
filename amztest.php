<?php

define('TEST', 1);
error_reporting(32757);

include('./amazon.co.jp/app.php');

$as = new AmazonSearch('B01BSA8BQ0', 'zh_CN');
$as->showProductData();

//http://xy26.xcdun.com/amazon.php?pw=sy!0i2pk;f2%lk3:w2&kw=B01M1DO8IQ&lg=zh_CN
//http://xy26.xcdun.com/amazon.php?pw=sy!0i2pk;f2%lk3:w2&kw=B01BSA8BQ0&lg=ja_JP
