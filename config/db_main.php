<?php
// config/db_main.php

// ตั้งค่าการเชื่อมต่อฐานข้อมูลหลัก (ของระบบบันทึกกิจกรรม)
$DB_MAIN_HOST = '192.168.203.6';
$DB_MAIN_USER = 'thepha';
$DB_MAIN_PASS = '_iL0veU2';
$DB_MAIN_NAME = 'opd_activity';
$DB_MAIN_CHARSET = 'utf8mb4';

/**
 * ฟังก์ชันคืนค่า mysqli connection สำหรับฐานข้อมูลหลัก
 *
 * @return mysqli
 */
function getMainDBConnection()
{
    global $DB_MAIN_HOST, $DB_MAIN_USER, $DB_MAIN_PASS, $DB_MAIN_NAME, $DB_MAIN_CHARSET;

    $conn = new mysqli($DB_MAIN_HOST, $DB_MAIN_USER, $DB_MAIN_PASS, $DB_MAIN_NAME);

    if ($conn->connect_error) {
        // ใน production ควร log error แล้วแสดงข้อความทั่วไปแทน
        die("Database connection failed (main): " . $conn->connect_error);
    }

    // ตั้งค่า charset ให้รองรับภาษาไทย
    if (!$conn->set_charset($DB_MAIN_CHARSET)) {
        die("Error loading character set " . $DB_MAIN_CHARSET . ": " . $conn->error);
    }

    return $conn;
}
