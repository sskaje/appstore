<?php
if (isset($_REQUEST['s'])) {
	highlight_file(__FILE__);exit;
}
?>
<h1><a href="http://sskaje.me/index.php/2012/07/appshopper-%E5%AF%BC%E5%85%A5appstore-%E5%8E%86%E5%8F%B2%E8%B4%AD%E4%B9%B0%E8%AE%B0%E5%BD%95/">Appstore Purchase List to AppShopper </a></h1>
<strong>Warning: DO NOT use this unless u TRUST ME.</strong><br />
<form action="" method="post">
Apple ID: <input type="text" name="apple_id" /><br />
Apple ID Password: <input type="password" name="apple_password" /><br />
AppShopper Username: <input type="text" name="appshopper_username" /><br />
AppShopper Password: <input type="password" name="appshopper_password" /><br />
<label><input type="checkbox" name="import_hidden" value="1" />Import Hidden</label><br />
Import to: <label><input type="radio" name="to" value="0" checked="checked" />MyApps</label> <label><input type="radio" name="to" value="1" />WishList</label><br />
<input type="submit" value="Submit" />
</form>
<?php
if (isset($_REQUEST['to']) && $_REQUEST['to']) {
	header('location: http://github.com/sskaje/appstore/');
	exit;
}


$apple_id = isset($_REQUEST['apple_id']) && !empty($_REQUEST['apple_id']) ? trim($_REQUEST['apple_id']) : die('Apple ID!!!');
$apple_password = isset($_REQUEST['apple_password']) && !empty($_REQUEST['apple_password']) ? trim($_REQUEST['apple_password']) : die('Apple ID Password!!!');
$appshopper_username = isset($_REQUEST['appshopper_username']) && !empty($_REQUEST['appshopper_username']) ? trim($_REQUEST['appshopper_username']) : die('AppShopper Username!!!');
$appshopper_password = isset($_REQUEST['appshopper_password']) && !empty($_REQUEST['appshopper_password']) ? trim($_REQUEST['appshopper_password']) : die('AppShopper Password!!!');

require(__DIR__ . '/classes/iTunes.class.php');
require(__DIR__ . '/classes/Appshopper.class.php');
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

$ih = 'no';
if (isset($_REQUEST['import_hidden']) && $_REQUEST['import_hidden']) {
	$ids = $itunes->getPurchasedIDList(true);
	if (isset($ids['Apps']) && !empty($ids['Apps'])) {
		$app_ids += $ids['Apps'];
	}
	$ih = 'yes';
}

# IMPORT to myapp list only
$ret = $appshopper->import(array_keys($app_ids));
echo <<<RESULT
Apple ID: {$apple_id}<br />
AppShopper ID: {$appshopper_username}<br />
Import Hidden Items: {$ih}<br />
Result:<br />

RESULT;
echo '<pre>', $ret, '</pre>';


# if u want to import to wishlist, use code below
/*
foreach ($ids['Apps'] as $app_id=>$type) {
	echo $app_id, ': testing... ';
	$r = $appshopper->update($app_id, APPSHOPPER_STATUS_WANT);
	if ($r) {
		echo "wanted!";
	} else {
		echo "skiped";
	}
	echo "\n";
}

*/