<?php
// public/get_activity_details.php
require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

header('Content-Type: application/json; charset=utf-8');

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$grouped_name = isset($_GET['grouped_name']) ? trim($_GET['grouped_name']) : '';

if (empty($start_date) || empty($end_date) || empty($grouped_name)) {
    echo json_encode([]);
    exit;
}

$conn = getMainDBConnection();
$data = [];

// Query base
$sql = "
    SELECT 
        h.hn,
        h.patient_name,
        h.visit_date,
        h.visit_time,
        a.name AS activity_name
    FROM patient_activity_detail d
    INNER JOIN patient_activity_header h ON h.id = d.header_id
    INNER JOIN activities a ON a.id = d.activity_id
    INNER JOIN activity_categories ac ON ac.id = a.category_id
    WHERE h.visit_date BETWEEN ? AND ?
      AND ac.code = 'INJ'
";

if ($grouped_name === 'ฉีด/วัคซีน (รวมทุกชนิด)') {
    // กรณีกลุ่มฉีด/วัคซีน
    $sql .= " AND a.name LIKE 'ฉีด%' ";
    $sql .= " ORDER BY h.visit_date DESC, h.visit_time DESC, h.id DESC";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
} else {
    // กรณีชื่อกิจกรรมปกติ
    $sql .= " AND a.name = ? ";
    $sql .= " ORDER BY h.visit_date DESC, h.visit_time DESC, h.id DESC";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sss', $start_date, $end_date, $grouped_name);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
}

$conn->close();

echo json_encode($data);
