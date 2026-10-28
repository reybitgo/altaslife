<?php
$f = 'views/auth/register.php';
$c = file_get_contents($f);
var_dump(strpos($c, 'packages_disabled'));
var_dump(strpos($c, 'Select Package'));
var_dump(strpos($c, 'package_id'));
?>
