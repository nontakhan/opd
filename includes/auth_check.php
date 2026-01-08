<?php
// includes/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ถ้ายังไม่ได้ login
if (empty($_SESSION['user_id'])) {
    // redirect ไปหน้า login
    header('Location: login.php');
    exit;
}
