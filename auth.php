<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
}

function require_resident() {
    require_login();
    if ($_SESSION['role'] !== 'resident') {
        header('Location: ../login.php');
        exit();
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ../login.php');
        exit();
    }
}
