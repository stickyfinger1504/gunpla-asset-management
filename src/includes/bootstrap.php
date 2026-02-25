<?php
/**
 * Application Bootstrap
 * Include this at the top of every page BEFORE any HTML output.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';

foreach (glob(__DIR__ . '/functions/*.php') as $fn) {
    require_once $fn;
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

function set_flash_message($message) {
    $_SESSION['flash_message'] = $message;
}

