<?php
$f = 'C:\laragon\www\altaslife\install.sql';
$c = file_get_contents($f);
$old = "    ('shop_max_delivery_attempts',  '3');";
$new = "    ('shop_max_delivery_attempts',  '3'),\n    ('packages_disabled',      '0');";
if (strpos($c, $old) !== false && strpos($c, 'packages_disabled') === false) {
    $c = str_replace($old, $new, $c);
    file_put_contents($f, $c);
    echo "ok\n";
} else {
    echo "skip\n";
}
