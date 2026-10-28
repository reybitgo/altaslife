<?php
$f = 'views/auth/register.php';
$c = file_get_contents($f);
$i = strpos($c, 'package_id');
if ($i !== false) {
    echo substr($c, $i-250, 600);
}
?>
