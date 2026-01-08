<?php
// config/db_hospital.php

// ตั้งค่าการเชื่อมต่อฐานข้อมูลโรงพยาบาล (เช่น HOSxP)
define('DB_HOS_HOST', '192.168.204.9');
define('DB_HOS_USER', 'it');
define('DB_HOS_PASS', 'It@11390!#');
define('DB_HOS_NAME', 'hos');
define('DB_HOS_CHARSET', 'tis620'); // HOSxP ส่วนใหญ่ใช้ tis620

/**
 * ฟังก์ชันคืนค่า mysqli connection สำหรับฐานข้อมูลโรงพยาบาล
 */
function getHospitalDBConnection() {
    $conn = new mysqli(DB_HOS_HOST, DB_HOS_USER, DB_HOS_PASS, DB_HOS_NAME);

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    // บอก MySQL ว่า connection ใช้ tis620 (ฝั่ง HOSxP)
    $conn->set_charset(DB_HOS_CHARSET);

    return $conn;
}
