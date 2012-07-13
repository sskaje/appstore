<?php
# Apple ID: e.g sskaje@sskaje.me
$apple_id = ''; 
# Password for Apple ID
$apple_password = '';
# Account for AppShopper.com
$appshopper_username = '';
# Password for Appshopper.com
$appshopper_password = '';
# Import hidden apps in your purchased history ?
$import_hidden = true;

$import_to = 'myapps'; # wishlist, myapps

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

# IMPORT to myapp list only (http://appshopper.com/myapps)
# This is not an good idea if you wanna move any of your records to 'My Apps' from 'Wish List'.
#$ret = $appshopper->import(array_keys($app_ids));
#var_dump($ret);
#exit;

# if u want to import to wishlist, use code below  (http://appshopper.com/wishlist)
foreach ($ids['Apps'] as $app_id=>$type) {
	echo $app_id, ': testing... ';
	$r = $appshopper->update($app_id, $import_to == 'wishlist' ? APPSHOPPER_STATUS_WANT : APPSHOPPER_STATUS_OWN);
	if ($r) {
		echo $import_to == 'wishlist' ? 'wanted!' : 'owned!';
	} else {
		echo 'skiped';
	}
	echo "\n";
}

# EOF