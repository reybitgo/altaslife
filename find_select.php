<?php
$f = 'views/auth/register.php';
$c = file_get_contents($f);
$i = strpos($c, 'Select Package');
if ($i !== false) {
    $start = $i > 150 ? $i - 150 : 0;
    echo substr($c, $start, 500);
}
?>
