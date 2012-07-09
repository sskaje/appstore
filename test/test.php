<?php
require(__DIR__ . '/../src/classes/iTunes.class.php');

$itunes = new iTunes;
$itunes->apple_id = 'sskaje@sskaje.me';
$itunes->password = 'password';
$itunes->guid = '11111111.22222222.00000000.44444444.CC0183D3.33019387.512309CA';

$itunes->login();

//$itunes->buy_purchased('319927587');

#$ids = $itunes->getPurchasedIDList();
#var_dump($ids);

$itunes->getPurchasedAppInfo();
