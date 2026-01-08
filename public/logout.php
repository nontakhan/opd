<?php
// public/logout.php

// เริ่ม session ก่อนเพื่อให้สามารถเคลียร์ข้อมูลได้
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ลบตัวแปรทั้งหมดใน session
$_SESSION = [];

// ถ้ามี cookie ของ session ให้สั่งหมดอายุทิ้งด้วย (กันเคสบาง browser จำต่อ)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// redirect กลับไปหน้า login
header('Location: login.php');
exit;
