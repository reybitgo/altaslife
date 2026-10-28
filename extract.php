<?php
$f = 'views/auth/register.php';
$c = file_get_contents($f);
$i = strpos($c, 'step-ind-1');
if ($i !== false) {
    echo substr($c, $i-80, 500);
}
?>
