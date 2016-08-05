<?php

function buckaroo_autoload($payment_method) {
    $class_name = strtolower($payment_method);
    $path = dirname(__FILE__)."/{$class_name}/{$class_name}.php";
    if (file_exists($path)) {
        require_once ($path);
    } else {
        die('Class not found!');
    }
}

