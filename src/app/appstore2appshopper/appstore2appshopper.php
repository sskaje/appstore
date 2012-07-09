<?php
$apple_id = '';
$apple_password = '';
$appshopper_username = '';
$appshopper_password = '';
$import_hidden = false;

require(__DIR__ . '/../../classes/iTunes.class.php');
require(__DIR__ . '/../../classes/Appshopper.class.php');
$appshopper = new Appshopper;
$appshopper->login($appshopper_username, $appshopper_password);

$itunes = new iTunes;
$itunes->apple_id = $apple_id;
$itunes->password = $apple_password;
$itunes->guid = '11111111.22222222.00000000.44444444.CC0183D3.33019387.512309CA';

$itunes->login();

$app_ids = array();
$ids = $itunes->getPurchasedIDList();
if (!isset($ids['Apps']) || empty($ids['Apps'])) {
	die('No purchased app found.');
}
$app_ids += $ids['Apps'];

if ($import_hidden) {
	$ids = $itunes->getPurchasedIDList(true);
	if (isset($ids['Apps']) && !empty($ids['Apps'])) {
		$app_ids += $ids['Apps'];
	}
}

# IMPORT to myapp list only
$appshopper->import(array_keys($app_ids));

# if u want to import to wishlist, use code below
/*
foreach ($ids['Apps'] as $app_id=>$type) {
#	$appshopper->update($app_id, APPSHOPPER_STATUS_WANT);
	echo $app_id, ': testing... ';
	$r = $appshopper->update($app_id, APPSHOPPER_STATUS_OWN);
	if ($r) {
		echo "owned!";
	} else {
		echo "skiped";
	}
	echo "\n";
}
*/