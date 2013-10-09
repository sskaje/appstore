<?php

require(__DIR__ . '/../src/classes/Appshopper.class.php');
$appshopper = new Appshopper;
$appshopper->login('sskaje', 'password');
$appshopper->detail('473056224');