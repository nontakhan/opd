<?php
// test_password.php

// ป้อนรหัสผ่านที่คุณต้องการทดสอบ
$input_password = "Admin1234"; 

// วาง hash ที่เก็บในฐานข้อมูล (จากตาราง users)
$db_hash = '$2y$10$O2sCj1BHtXxrmfI7cATz5egHwVUSSR6yaU2qGCxEcf0E2rn5pxd/S';

echo "<h2>เช็ค password_verify()</h2>";

if (password_verify($input_password, $db_hash)) {
    echo "<span style='color:green;font-size:20px;'>✔ รหัสผ่านตรงกัน</span>";
} else {
    echo "<span style='color:red;font-size:20px;'>✘ รหัสผ่านไม่ตรงกัน</span>";
}

echo "<hr>";
echo "<h3>Hash ของรหัสผ่าน input:</h3>";
echo password_hash($input_password, PASSWORD_BCRYPT);
?>
