<?php
// public/activity_form.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';
require_once '../config/db_hospital.php';

// 🛑 DEBUG: Enable Error Display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function tis620_to_utf8($str)
{
    if ($str === null)
        return '';
    return iconv('TIS-620', 'UTF-8//IGNORE', $str);
}

// ตั้งชื่อหน้า
$page_title = 'บันทึกกิจกรรมพยาบาล - Nurse Activity System';

// include header + navbar
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// ตัวแปรไว้ใช้ในหน้า
$search_hn = '';
$patient_data = null;      // array เก็บข้อมูลจากฐาน รพ.
$patient_visits = [];      // array เก็บ visits ทั้งหมด (สำหรับเลือก)
$show_visit_modal = false; // แสดง modal เลือก visit
$selected_category_code = '';     // ค่าเริ่มต้น
$save_message = '';
$save_message_type = '';        // success / error

$connMain = getMainDBConnection();

// 1) โหลดรายการกิจกรรมทั้งหมด (แยกตามหมวด)
$activities_by_category = []; // [code] => [ {id, name}, ... ]


$sql_activities = "
    SELECT a.id, a.name, a.is_active, ac.code AS category_code, ac.name AS category_name
    FROM activities a
    INNER JOIN activity_categories ac ON ac.id = a.category_id
    ORDER BY ac.id, a.name
";
if ($result = $connMain->query($sql_activities)) {
    while ($row = $result->fetch_assoc()) {
        if ((int) $row['is_active'] !== 1) {
            continue; // ข้ามกิจกรรมที่ปิดใช้งาน
        }
        $code = $row['category_code'];
        if (!isset($activities_by_category[$code])) {
            $activities_by_category[$code] = [];
        }
        $activities_by_category[$code][] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    $result->free();
}


// ---------- จัดการ POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['form_action']) ? $_POST['form_action'] : '';

    // 2) กดค้นหา HN
    if ($action === 'search_hn') {

        // -------------------------------
        // ค่าเริ่มต้น
        // -------------------------------
        $block_search = false;
        $patient_data = null;

        // รับค่า HN
        $search_hn_input = trim($_POST['hn'] ?? '');
        $search_hn_digits = preg_replace('/\D/', '', $search_hn_input);
        $search_hn = $search_hn_digits;

        // รับค่า category จากฟอร์ม (ต้องเป็นค่าที่เลือกจริงเท่านั้น)
        if (
            isset($_POST['category_code']) &&
            in_array($_POST['category_code'], ['OPD', 'INJ'], true)
        ) {
            $selected_category_code = $_POST['category_code'];
        } else {
            $selected_category_code = '';
        }

        // -------------------------------
        // ตรวจ HN ว่าง
        // -------------------------------
        if ($search_hn_digits === '') {
            $save_message_type = 'error';
            $save_message = 'กรุณากรอก HN ก่อนค้นหา';
            $search_hn = '';
            $block_search = true;
        }

        // เติม 0 ให้ครบ 9 หลัก
        $hn_for_query = str_pad($search_hn_digits, 9, '0', STR_PAD_LEFT);

        // -------------------------------
        // 🔒 ตรวจซ้ำ: 1 HN / 1 วัน / 1 หมวด
        // -------------------------------
        if (!$block_search && $selected_category_code !== '') {

            $sql_check = "
                SELECT h.id
                FROM patient_activity_header h
                INNER JOIN activity_categories ac ON ac.id = h.category_id
                WHERE h.hn = ?
                  AND h.visit_date = CURDATE()
                  AND ac.code = ?
                LIMIT 1
            ";

            if ($stmt_check = $connMain->prepare($sql_check)) {
                $stmt_check->bind_param('ss', $hn_for_query, $selected_category_code);
                $stmt_check->execute();
                $res_check = $stmt_check->get_result();

                if ($res_check->num_rows > 0) {
                    $save_message_type = 'error';
                    $save_message =
                        'มีการบันทึกกิจกรรมประเภทนี้แล้วในวันนี้ '
                        . 'หากต้องการเพิ่ม กรุณาเลือกประเภทกิจกรรมอื่น';

                    $search_hn = '';        // 🔥 เคลียร์ HN
                    $block_search = true;
                }

                $stmt_check->close();
            }
        }

        // -------------------------------
        // โหลดข้อมูลผู้ป่วยจาก HIS
        // -------------------------------
        if (!$block_search) {

            $connHos = getHospitalDBConnection();

            // ดึงข้อมูล visits ทั้งหมดของผู้ป่วยในวันนี้ (ไม่ใช้ LIMIT 1)
            $sql_patient = "
                SELECT 
                    p.hn,
                    CONCAT(p.pname, p.fname, ' ', p.lname) AS patient_name,
                    p.cid,
                    o.vn,
                    o.vstdate,
                    o.vsttime
                FROM patient p
                LEFT JOIN ovst o ON o.hn = p.hn
                WHERE o.vstdate = CURDATE()
                  AND p.hn = ?
                ORDER BY o.vsttime DESC
            ";

            if ($stmt = $connHos->prepare($sql_patient)) {
                $stmt->bind_param('s', $hn_for_query);
                $stmt->execute();
                $result = $stmt->get_result();

                $patient_visits = [];
                while ($row = $result->fetch_assoc()) {
                    $patient_visits[] = [
                        'hn' => tis620_to_utf8($row['hn']),
                        'patient_name' => tis620_to_utf8($row['patient_name']),
                        'cid' => tis620_to_utf8($row['cid']),
                        'vn' => tis620_to_utf8($row['vn']),
                        'visit_date' => $row['vstdate'],
                        'visit_time' => $row['vsttime']
                    ];
                }

                if (count($patient_visits) === 0) {
                    $save_message_type = 'error';
                    $save_message = 'ไม่พบประวัติผู้ป่วย HN นี้ในวันนี้';
                    $search_hn = '';
                } elseif (count($patient_visits) === 1) {
                    // มี visit เดียว ใช้เลย
                    $patient_data = $patient_visits[0];
                } else {
                    // มีหลาย visits แสดง modal ให้เลือก
                    $show_visit_modal = true;
                }

                $stmt->close();
            } else {
                $save_message_type = 'error';
                $save_message = 'ไม่สามารถเตรียมคำสั่งค้นหาข้อมูลผู้ป่วยได้';
            }

            $connHos->close();
        }
    }

    // 2.5) เลือก visit จาก modal
    elseif ($action === 'select_visit') {
        $selected_vn = isset($_POST['selected_vn']) ? trim($_POST['selected_vn']) : '';
        $search_hn_input = trim($_POST['hn'] ?? '');
        $search_hn_digits = preg_replace('/\D/', '', $search_hn_input);
        $search_hn = $search_hn_digits;
        $hn_for_query = str_pad($search_hn_digits, 9, '0', STR_PAD_LEFT);

        // รับค่า category จากฟอร์ม
        if (
            isset($_POST['category_code']) &&
            in_array($_POST['category_code'], ['OPD', 'INJ'], true)
        ) {
            $selected_category_code = $_POST['category_code'];
        }

        if ($selected_vn !== '' && $search_hn_digits !== '') {
            $connHos = getHospitalDBConnection();

            $sql_patient = "
                SELECT 
                    p.hn,
                    CONCAT(p.pname, p.fname, ' ', p.lname) AS patient_name,
                    p.cid,
                    o.vn,
                    o.vstdate,
                    o.vsttime
                FROM patient p
                LEFT JOIN ovst o ON o.hn = p.hn
                WHERE o.vstdate = CURDATE()
                  AND p.hn = ?
                  AND o.vn = ?
                LIMIT 1
            ";

            if ($stmt = $connHos->prepare($sql_patient)) {
                $stmt->bind_param('ss', $hn_for_query, $selected_vn);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $patient_data = [
                        'hn' => tis620_to_utf8($row['hn']),
                        'patient_name' => tis620_to_utf8($row['patient_name']),
                        'cid' => tis620_to_utf8($row['cid']),
                        'vn' => tis620_to_utf8($row['vn']),
                        'visit_date' => $row['vstdate'],
                        'visit_time' => $row['vsttime']
                    ];
                } else {
                    $save_message_type = 'error';
                    $save_message = 'ไม่พบ visit ที่เลือก';
                    $search_hn = '';
                }

                $stmt->close();
            }

            $connHos->close();
        }
    }



    // 3) กดบันทึกกิจกรรม
    elseif ($action === 'save_activity') {
        try {



            // ข้อมูลที่ส่งมาจากฟอร์ม
            $hn = isset($_POST['hn']) ? trim($_POST['hn']) : '';
            $vn = isset($_POST['vn']) ? trim($_POST['vn']) : '';
            $cid = isset($_POST['cid']) ? trim($_POST['cid']) : '';
            $pname = isset($_POST['patient_name']) ? trim($_POST['patient_name']) : '';
            $visit_date = isset($_POST['visit_date']) ? trim($_POST['visit_date']) : '';
            $visit_time = isset($_POST['visit_time']) ? trim($_POST['visit_time']) : '';
            $selected_category_code = isset($_POST['category_code']) ? $_POST['category_code'] : 'OPD';
            $note = isset($_POST['note']) ? trim($_POST['note']) : '';
            $activity_ids = isset($_POST['activity_ids']) && is_array($_POST['activity_ids'])
                ? $_POST['activity_ids']
                : [];

            if ($selected_category_code === '') {
                $save_message_type = 'error';
                $save_message = 'กรุณาเลือกประเภทกิจกรรมก่อนบันทึก';
                // ลบ return ออก หรือเก็บ logic ไว้ แต่ต้องไม่ return แบบนี้
                // แต่ถ้า category_code ไม่มี ก็ควรจะ error
            }

            // ตรวจสอบข้อมูลเบื้องต้น
            if ($hn === '' || $vn === '' || $pname === '' || $visit_date === '' || $visit_time === '') {
                $save_message_type = 'error';
                $save_message = 'ข้อมูลผู้ป่วยไม่ครบถ้วน กรุณาค้นหา HN ใหม่อีกครั้ง';
            } elseif (empty($activity_ids)) {
                $save_message_type = 'error';
                $save_message = 'กรุณาเลือกอย่างน้อย 1 กิจกรรม';
            } else {
                // หา category_id จาก code
                $sql_cat = "SELECT id FROM activity_categories WHERE code = ? LIMIT 1";
                $category_id = null;
                if ($stmt_cat = $connMain->prepare($sql_cat)) {
                    $stmt_cat->bind_param('s', $selected_category_code);
                    $stmt_cat->execute();
                    $res_cat = $stmt_cat->get_result();
                    if ($row_cat = $res_cat->fetch_assoc()) {
                        $category_id = (int) $row_cat['id'];
                    }
                    $res_cat->free();
                    $stmt_cat->close();
                }

                if ($category_id === null) {
                    $save_message_type = 'error';
                    $save_message = 'ไม่พบหมวดกิจกรรมที่เลือกในระบบ';
                } else {
                    // 🔒 ป้องกันการบันทึกซ้ำ (Server-side check)
                    $is_duplicate = false;
                    $sql_dup = "
                    SELECT id FROM patient_activity_header 
                    WHERE hn = ? AND visit_date = ? AND category_id = ?
                    LIMIT 1
                ";
                    if ($stmt_dup = $connMain->prepare($sql_dup)) {
                        $stmt_dup->bind_param('ssi', $hn, $visit_date, $category_id);
                        $stmt_dup->execute();
                        $stmt_dup->store_result();
                        if ($stmt_dup->num_rows > 0) {
                            $is_duplicate = true;
                        }
                        $stmt_dup->close();
                    }

                    if ($is_duplicate) {
                        $save_message_type = 'error';
                        $save_message = 'มีการบันทึกกิจกรรมหมวดนี้ ของผู้ป่วยรายนี้ ในวันนี้ไปแล้ว ไม่สามารถบันทึกซ้ำได้';
                    } else {
                        // เริ่ม transaction

                        $connMain->begin_transaction();

                        try {
                            // 3.1 แทรก patient_activity_header
                            $sql_header = "
                        INSERT INTO patient_activity_header
                            (hn, vn, cid, patient_name, visit_date, visit_time, category_id, note, created_by, created_at)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ";

                            if ($stmt_header = $connMain->prepare($sql_header)) {
                                $uid = $_SESSION['user_id'];

                                $stmt_header->bind_param(
                                    'ssssssisi',
                                    $hn,
                                    $vn,
                                    $cid,
                                    $pname,
                                    $visit_date,
                                    $visit_time,
                                    $category_id,
                                    $note,
                                    $uid
                                );

                                if (!$stmt_header->execute()) {
                                    throw new Exception('ไม่สามารถบันทึกหัวรายการกิจกรรมได้');
                                }

                                $header_id = $stmt_header->insert_id;
                                $stmt_header->close();
                            } else {
                                throw new Exception('ไม่สามารถเตรียมคำสั่งบันทึกหัวรายการกิจกรรมได้');
                            }

                            // 3.2 แทรก patient_activity_detail หลายแถว (ตามกิจกรรมที่เลือก)
                            $sql_detail = "
                        INSERT INTO patient_activity_detail
                            (header_id, activity_id)
                        VALUES
                            (?, ?)
                    ";

                            if ($stmt_detail = $connMain->prepare($sql_detail)) {
                                foreach ($activity_ids as $aid) {
                                    $aid_int = (int) $aid;
                                    $stmt_detail->bind_param('ii', $header_id, $aid_int);
                                    if (!$stmt_detail->execute()) {
                                        throw new Exception('ไม่สามารถบันทึกรายละเอียดกิจกรรมบางรายการได้');
                                    }
                                }
                                $stmt_detail->close();
                            } else {
                                throw new Exception('ไม่สามารถเตรียมคำสั่งบันทึกรายละเอียดกิจกรรมได้');
                            }

                            // ถ้าทุกอย่างผ่าน → commit
                            $connMain->commit();
                            $save_message_type = 'success';
                            $save_message = 'บันทึกกิจกรรมเรียบร้อยแล้ว';

                            // เคลียร์ค่าในฟอร์ม (ยกเว้นหมวดกิจกรรม)
                            $search_hn = '';
                            $patient_data = null;

                        } catch (Exception $e) {
                            // rollback ถ้ามี error
                            $connMain->rollback();
                            $save_message_type = 'error';
                            $save_message = $e->getMessage();
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $save_message_type = 'error';
            $save_message = 'Fatal Error: ' . $e->getMessage();
        }
    }
}

// ปิด connection main ตอนท้ายไฟล์
$connMain->close();
?>

<style>
    .activity-form-container {
        max-width: 960px;
        margin: 20px auto 40px;
        padding: 0 clamp(16px, 4vw, 40px);
        font-family: "Sarabun", sans-serif;
        box-sizing: border-box;
    }

    .activity-form-container h2 {
        margin-bottom: 4px;
    }

    .page-subtitle {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 14px;
    }

    .card {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        padding: 14px 14px 12px;
        margin-bottom: 14px;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.06);
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .section-title span.icon {
        font-size: 1.1rem;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
    }

    .form-group {
        flex: 1;
        min-width: 180px;
    }

    .form-group label {
        display: block;
        margin-bottom: 3px;
        font-size: 0.9rem;
        color: #475569;
    }

    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="time"],
    .form-group textarea {
        width: 100%;
        padding: 7px 9px;
        border-radius: 7px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        box-sizing: border-box;
        background-color: #f8fafc;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #2563eb;
        background-color: #ffffff;
        box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.15);
    }

    /* ปุ่ม */
    .btn {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        font-family: "Sarabun", sans-serif;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #fff;
        box-shadow: 0 6px 14px rgba(37, 99, 235, 0.4);
    }

    .btn-primary:hover {
        filter: brightness(1.03);
    }

    .btn-secondary {
        background-color: #6b7280;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #4b5563;
    }

    .btn-category {
        margin-right: 6px;
        margin-bottom: 6px;
        border-radius: 999px;
        padding-inline: 14px;
    }

    .btn-category.active {
        background: linear-gradient(135deg, #22c55e, #16a34a);
    }

    /* กล่องข้อมูลผู้ป่วย */
    .patient-card {
        background-color: #f9fafb;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        padding: 10px 12px;
        font-size: 0.9rem;
    }

    .patient-card-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .patient-card-row>div {
        min-width: 200px;
    }

    .patient-empty {
        border-radius: 8px;
        border: 1px dashed #d1d5db;
        padding: 10px 12px;
        font-size: 0.88rem;
        color: #6b7280;
        background-color: #f9fafb;
    }

    /* รายการกิจกรรม: 3 คอลัมน์ */
    .activity-list {
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        padding: 10px 12px;
        max-height: 320px;
        overflow-y: auto;
        background-color: #ffffff;

        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px 14px;
    }

    .activity-item {
        font-size: 0.9rem;
    }

    .activity-item label {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 8px;
        border-radius: 8px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: 0.15s;
        background-color: #f8fafc;
    }

    .activity-item input[type="checkbox"] {
        transform: scale(1.2);
        accent-color: #2563eb;
    }

    .activity-item label:hover {
        border-color: #bfdbfe;
        background-color: #eff6ff;
    }

    .note-textarea {
        min-height: 80px;
    }

    @media (max-width: 900px) {
        .activity-list {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .form-row {
            flex-direction: column;
        }

        .activity-list {
            grid-template-columns: 1fr;
        }
    }

    /* ===== Nurse Dropdown ===== */
    .form-select-nurse {
        width: 100%;
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        font-family: "Sarabun", sans-serif;
        background-color: #f8fafc;
        color: #0f172a;
        cursor: pointer;
        appearance: none;
        background-image:
            linear-gradient(45deg, transparent 50%, #475569 50%),
            linear-gradient(135deg, #475569 50%, transparent 50%);
        background-position:
            calc(100% - 18px) 55%,
            calc(100% - 12px) 55%;
        background-size:
            6px 6px,
            6px 6px;
        background-repeat: no-repeat;
        transition: all 0.15s ease-in-out;
    }

    .form-select-nurse:hover {
        background-color: #f1f5f9;
    }

    .form-select-nurse:focus {
        outline: none;
        border-color: #2563eb;
        background-color: #ffffff;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
    }

    /* option */
    .form-select-nurse option {
        padding: 6px;
    }

    .select2-container--default .select2-selection--single {
        height: 42px;
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 14px;
        display: flex;
        align-items: center;
    }

    .select2-selection__arrow {
        height: 42px !important;
    }

    .select2-container--default .select2-selection--single:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 1px #2563eb;
    }

    /* ===== Visit Selection Modal ===== */
    .visit-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .visit-modal {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
    }

    .visit-modal-header {
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #fff;
        padding: 16px 20px;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .visit-modal-body {
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }

    .visit-modal-info {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 16px;
        font-size: 0.9rem;
        color: #92400e;
    }

    .visit-option {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .visit-option:hover {
        border-color: #2563eb;
        background-color: #eff6ff;
    }

    .visit-option.selected {
        border-color: #2563eb;
        background-color: #dbeafe;
    }

    .visit-option-radio {
        width: 20px;
        height: 20px;
        border: 2px solid #9ca3af;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .visit-option.selected .visit-option-radio {
        border-color: #2563eb;
        background-color: #2563eb;
    }

    .visit-option.selected .visit-option-radio::after {
        content: '';
        width: 8px;
        height: 8px;
        background: #fff;
        border-radius: 50%;
    }

    .visit-option-content {
        flex: 1;
    }

    .visit-option-vn {
        font-weight: 600;
        color: #1e40af;
        font-size: 0.95rem;
    }

    .visit-option-time {
        color: #475569;
        font-size: 0.9rem;
        margin-top: 2px;
    }

    .visit-modal-footer {
        padding: 16px 20px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn-confirm-visit {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #fff;
        padding: 10px 24px;
        border-radius: 999px;
        border: none;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        font-family: "Sarabun", sans-serif;
    }

    .btn-confirm-visit:hover {
        filter: brightness(1.05);
    }

    .btn-confirm-visit:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }

    .btn-cancel-visit {
        background: #f3f4f6;
        color: #374151;
        padding: 10px 24px;
        border-radius: 999px;
        border: 1px solid #d1d5db;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        font-family: "Sarabun", sans-serif;
    }

    .btn-cancel-visit:hover {
        background: #e5e7eb;
    }
</style>

<!-- Modal เลือก Visit (แสดงเมื่อมีหลาย visits) -->
<?php if ($show_visit_modal && count($patient_visits) > 1): ?>
    <div class="visit-modal-overlay" id="visitModalOverlay">
        <div class="visit-modal">
            <div class="visit-modal-header">
                🏥 เลือก Visit ที่ต้องการบันทึกกิจกรรม
            </div>
            <div class="visit-modal-body">
                <div class="visit-modal-info">
                    ⚠️ ผู้ป่วย <strong><?= htmlspecialchars($patient_visits[0]['patient_name']); ?></strong>
                    (HN: <?= htmlspecialchars($patient_visits[0]['hn']); ?>)
                    มี <?= count($patient_visits); ?> visits ในวันนี้ กรุณาเลือก visit ที่ต้องการบันทึกกิจกรรม
                </div>

                <form method="post" action="activity_form.php" id="selectVisitForm">
                    <input type="hidden" name="form_action" value="select_visit">
                    <input type="hidden" name="hn" value="<?= htmlspecialchars($search_hn); ?>">
                    <input type="hidden" name="category_code" value="<?= htmlspecialchars($selected_category_code); ?>">
                    <input type="hidden" name="selected_vn" id="selectedVn" value="">

                    <?php foreach ($patient_visits as $index => $visit): ?>
                        <div class="visit-option" data-vn="<?= htmlspecialchars($visit['vn']); ?>">
                            <div class="visit-option-radio"></div>
                            <div class="visit-option-content">
                                <div class="visit-option-vn">VN: <?= htmlspecialchars($visit['vn']); ?></div>
                                <div class="visit-option-time">
                                    เวลา: <?= substr($visit['visit_time'], 0, 5); ?> น.
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
            <div class="visit-modal-footer">
                <button type="button" class="btn-cancel-visit" onclick="closeVisitModal()">ยกเลิก</button>
                <button type="button" class="btn-confirm-visit" id="confirmVisitBtn" disabled
                    onclick="confirmVisitSelection()">
                    ยืนยันการเลือก
                </button>
            </div>
        </div>
    </div>

    <script>
        // จัดการการเลือก visit ใน modal
        document.querySelectorAll('.visit-option').forEach(option => {
            option.addEventListener('click', function () {
                // ลบ selected จากทุกตัว
                document.querySelectorAll('.visit-option').forEach(o => o.classList.remove('selected'));
                // เพิ่ม selected ให้ตัวที่คลิก
                this.classList.add('selected');
                // อัพเดท hidden input
                document.getElementById('selectedVn').value = this.dataset.vn;
                // เปิดใช้งานปุ่มยืนยัน
                document.getElementById('confirmVisitBtn').disabled = false;
            });
        });

        function confirmVisitSelection() {
            const selectedVn = document.getElementById('selectedVn').value;
            if (selectedVn) {
                document.getElementById('selectVisitForm').submit();
            }
        }

        function closeVisitModal() {
            document.getElementById('visitModalOverlay').style.display = 'none';
            // clear HN input และ focus
            const hnInput = document.getElementById('hn');
            if (hnInput) {
                hnInput.value = '';
                hnInput.focus();
            }
        }
    </script>
<?php endif; ?>

<div class="activity-form-container">
    <h2>บันทึกกิจกรรมพยาบาล</h2>
    <div class="page-subtitle">
        ค้นหาผู้ป่วยจาก HN (เฉพาะผู้ที่มารับบริการวันนี้) แล้วเลือกกิจกรรมที่พยาบาลได้ดำเนินการกับผู้ป่วย
    </div>

    <!-- ฟอร์มหา HN -->
    <div class="card">
        <div class="section-title">
            <span class="icon">🔎</span> 1. ค้นหาผู้ป่วยโดย HN (วันนี้)
        </div>
        <form method="post" action="activity_form.php">
            <input type="hidden" name="form_action" value="search_hn">
            <div class="form-row">
                <div class="form-group" style="flex:0 0 220px;">
                    <label for="hn">HN</label>
                    <input type="text" name="hn" id="hn" value="<?= htmlspecialchars($search_hn); ?>" required>
                    <div style="font-size:0.8rem; color:#6b7280; margin-top:2px;">
                        ไม่ต้องใส่เลข 0 นำหน้า ระบบจะเติมให้เอง
                    </div>
                </div>
                <div class="form-group" style="flex:0 0 170px; align-self:flex-end;">
                    <button type="submit" class="btn btn-secondary">ค้นหาข้อมูลผู้ป่วย</button>
                </div>
            </div>
            <div style="font-size:0.82rem; color:#6b7280; margin-top:-4px;">
                ระบบจะค้นหาจากประวัติการมารับบริการของวันนี้เท่านั้น
            </div>
        </form>
    </div>

    <!-- ข้อมูลผู้ป่วย -->
    <div class="card">
        <div class="section-title">
            <span class="icon">👤</span> 2. ข้อมูลผู้ป่วย
        </div>

        <?php if ($patient_data): ?>
            <div class="patient-card">
                <div class="patient-card-row">
                    <div><strong>HN:</strong> <?= htmlspecialchars($patient_data['hn']); ?></div>
                    <div><strong>ชื่อ-สกุล:</strong> <?= htmlspecialchars($patient_data['patient_name']); ?></div>
                    <div><strong>เลขบัตรประชาชน:</strong> <?= htmlspecialchars($patient_data['cid']); ?></div>
                    <div><strong>VN:</strong> <?= htmlspecialchars($patient_data['vn']); ?></div>
                    <div>
                        <strong>วันที่-เวลา Visit:</strong>
                        <?= date('d/m/Y', strtotime($patient_data['visit_date'])); ?>
                        <?= substr($patient_data['visit_time'], 0, 5); ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="patient-empty">
                ยังไม่มีข้อมูลผู้ป่วย กรุณาค้นหา HN เพื่อโหลดข้อมูลผู้ป่วยจากระบบโรงพยาบาล (เฉพาะประจำวันนี้)
            </div>
        <?php endif; ?>
    </div>

    <!-- ฟอร์มบันทึกกิจกรรม -->
    <div class="card">
        <div class="section-title">
            <span class="icon">✅</span> 3. บันทึกกิจกรรมที่ทำกับผู้ป่วย
        </div>

        <form method="post" action="activity_form.php" id="activitySaveForm">
            <input type="hidden" name="form_action" value="save_activity">

            <!-- hidden: ข้อมูลผู้ป่วยจากการค้นหา -->
            <input type="hidden" name="hn" value="<?= $patient_data ? htmlspecialchars($patient_data['hn']) : ''; ?>">
            <input type="hidden" name="vn" value="<?= $patient_data ? htmlspecialchars($patient_data['vn']) : ''; ?>">
            <input type="hidden" name="cid" value="<?= $patient_data ? htmlspecialchars($patient_data['cid']) : ''; ?>">
            <input type="hidden" name="patient_name"
                value="<?= $patient_data ? htmlspecialchars($patient_data['patient_name']) : ''; ?>">
            <input type="hidden" name="visit_date"
                value="<?= $patient_data ? htmlspecialchars($patient_data['visit_date']) : ''; ?>">
            <input type="hidden" name="visit_time"
                value="<?= $patient_data ? htmlspecialchars($patient_data['visit_time']) : ''; ?>">

            <!-- 3.1 เลือกหมวดกิจกรรม -->
            <div class="form-row" style="margin-bottom:8px;">
                <div class="form-group">
                    <label>ประเภทกิจกรรม</label>
                    <input type="hidden" name="category_code" id="category_code" value="">

                    <button type="button" class="btn btn-secondary btn-category" data-cat="OPD">
                        กิจกรรม OPD
                    </button>
                    <button type="button" class="btn btn-secondary btn-category" data-cat="INJ">
                        กิจกรรมห้องฉีดยา/ทำแผล
                    </button>
                </div>
            </div>

            <!-- 3.2 แสดง checkbox กิจกรรม ตามหมวดที่เลือก -->
            <div class="form-row">
                <div class="form-group">
                    <label>รายการกิจกรรม (เลือกได้หลายข้อ)</label>
                    <div class="activity-list" id="activityList">
                        <!-- จะถูกเติมด้วย JS จากตัวแปร activitiesData -->
                    </div>
                </div>
            </div>

            <!-- 3.3 หมายเหตุ -->
            <div class="form-row">
                <div class="form-group">
                    <label for="note">หมายเหตุ</label>
                    <textarea name="note" id="note" class="note-textarea"></textarea>
                </div>
            </div>

            <div class="form-row" style="margin-top:5px;">
                <div class="form-group" style="flex:0 0 220px;">
                    <button type="submit" class="btn btn-primary" <?= $patient_data ? '' : 'disabled'; ?>>
                        บันทึกกิจกรรม
                    </button>
                </div>
                <?php if (!$patient_data): ?>
                    <div class="form-group" style="color:#b91c1c; font-size:0.82rem;">
                        * ต้องค้นหาผู้ป่วยและพบประวัติการมารับบริการวันนี้ก่อน จึงจะบันทึกกิจกรรมได้
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    // เตรียมข้อมูลกิจกรรมจาก PHP มาที่ JS
    const activitiesData = <?= json_encode($activities_by_category, JSON_UNESCAPED_UNICODE); ?>;
    let selectedCategoryCode = <?= json_encode($selected_category_code); ?>;

    // ฟังก์ชันแสดงกิจกรรมตามหมวด (3 คอลัมน์)
    function renderActivities() {
        const container = document.getElementById('activityList');
        container.innerHTML = '';

        if (!activitiesData[selectedCategoryCode] || activitiesData[selectedCategoryCode].length === 0) {
            container.innerHTML = '<div style="color:#777; font-size:0.85rem;">ยังไม่มีกิจกรรมในหมวดนี้ หรือถูกปิดการใช้งาน</div>';
            return;
        }

        activitiesData[selectedCategoryCode].forEach(act => {
            const div = document.createElement('div');
            div.className = 'activity-item';

            const id = 'activity_' + act.id;

            div.innerHTML = `
            <label for="${id}">
                <input type="checkbox" name="activity_ids[]" value="${act.id}" id="${id}">
                <span>${act.name}</span>
            </label>
        `;
            container.appendChild(div);
        });
    }

    // จัดการปุ่มเลือกหมวด
    document.querySelectorAll('.btn-category').forEach(btn => {
        btn.addEventListener('click', function () {
            const cat = this.dataset.cat;

            selectedCategoryCode = cat;
            document.getElementById('category_code').value = cat;

            // highlight
            document.querySelectorAll('.btn-category').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            renderActivities();

            // 👉 ตรงนี้ค่อย trigger การตรวจซ้ำ (ถ้าจะทำ ajax)
        });
    });


    // ตั้ง active button เริ่มต้น
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-category').forEach(btn => {
            if (btn.getAttribute('data-cat') === selectedCategoryCode) {
                btn.classList.add('active');
            }
        });
        renderActivities();
    });

    $(document).ready(function () {
        $('#nurse_id').select2({
            placeholder: 'ค้นหาชื่อพยาบาล...',
            allowClear: true,
            width: '100%'
        });
    });

</script>

<?php if ($save_message !== ''): ?>
    <script>
        Swal.fire({
            icon: <?= json_encode($save_message_type === 'success' ? 'success' : 'error'); ?>,
            title: <?= json_encode($save_message_type === 'success' ? 'สำเร็จ' : 'แจ้งเตือน'); ?>,
            text: <?= json_encode($save_message, JSON_UNESCAPED_UNICODE); ?>,
            confirmButtonText: 'ตกลง'
        }).then(() => {
            const hnInput = document.getElementById('hn');
            if (hnInput) {
                hnInput.value = '';
                hnInput.focus();
            }
        });
    </script>
<?php endif; ?>


<?php
require_once '../includes/footer.php';
?>