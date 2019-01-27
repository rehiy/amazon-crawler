<?php

set_time_limit(300);
error_reporting(32757);

require('./amazon.co.jp/app.php');

if (empty($_GET['pw']) || $_GET['pw'] != 'sy!0i2pk;f2%lk3:w2') {
    app_exit(array('error' => '权限错误'));
}

if (isset($_GET['kw']) && preg_match('@^\w+$@i', $_GET['kw'])) {
    $lg = isset($_GET['lg']) ? $_GET['lg'] : '';
    $as = new AmazonSearch($_GET['kw'], $lg);
    $as->showProductData();
}

app_exit(array('error' => '参数错误'));
