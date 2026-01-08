<?php
// public/index.php

session_start();

// ถ้ามีการ login แล้ว (เดี๋ยวเราจะตั้ง session ตอน login)
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// ถ้ายังไม่ login ให้ไปหน้า login
header('Location: login.php');
exit;
