<?php
session_start();

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
}

function checkVendor() {
    if ($_SESSION['user_role'] != 'vendor' && $_SESSION['user_role'] != 'admin') {
        header("Location: ../index.php");
        exit();
    }
}

function checkAdmin() {
    if ($_SESSION['user_role'] != 'admin') {
        header("Location: ../index.php");
        exit();
    }
}

function getUserRole() {
    return $_SESSION['user_role'] ?? 'guest';
}
?>