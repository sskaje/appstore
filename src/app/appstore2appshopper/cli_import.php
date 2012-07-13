<?php
$appshopper_username = '';
$appshopper_password = '';

$import_to = 'myapps'; # wishlist, myapps

$app_ids = array(
	'429208823',
);

require(__DIR__ . '/../../classes/Appshopper.class.php');
$appshopper = new Appshopper;
$appshopper->login($appshopper_username, $appshopper_password);

# IMPORT to myapp list only (http://appshopper.com/myapps)
#$ret = $appshopper->import($app_ids);
#var_dump($ret);
#exit;

# if u want to import to wishlist, use code below  (http://appshopper.com/wishlist)
foreach ($app_ids as $app_id) {
	echo $app_id, ': testing... ';
	$r = $appshopper->update($app_id, $import_to == 'wishlist' ? APPSHOPPER_STATUS_WANT : APPSHOPPER_STATUS_OWN);
	if ($r) {
		echo $import_to == 'wishlist' ? 'wanted!' : 'owned!';
	} else {
		echo 'skiped';
	}
	echo "\n";
}
