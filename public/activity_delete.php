<?php
require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

// ป้องกันการเข้าตรง
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: activity_list.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: activity_list.php');
    exit;
}

$conn = getMainDBConnection();

/*
  ลำดับการลบ:
  1. detail
  2. header
*/

$conn->begin_transaction();

try {
    // ลบ detail
    $stmt = $conn->prepare("DELETE FROM patient_activity_detail WHERE header_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // ลบ header
    $stmt = $conn->prepare("DELETE FROM patient_activity_header WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
}

$conn->close();

header('Location: activity_list.php');
exit;
?>