<?php
// public/manage_activities.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

// ตรวจสอบสิทธิ์ admin
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $page_title = 'ไม่มีสิทธิ์เข้าถึง - Nurse Activity System';
    require_once '../includes/header.php';
    require_once '../includes/navbar.php';
    echo '<div style="max-width:800px;margin:20px auto;font-family:\'Sarabun\',sans-serif;color:#d00;">
            คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการกิจกรรม (Admin เท่านั้น)
          </div>';
    require_once '../includes/footer.php';
    exit;
}

$page_title = 'จัดการกิจกรรม - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$conn = getMainDBConnection();

$message = '';
$message_type = ''; // success / error

// โหลดประเภทกิจกรรม (OPD / INJ)
$categories = [];
$sql_cat = "SELECT id, code, name FROM activity_categories ORDER BY id";
if ($res_cat = $conn->query($sql_cat)) {
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row;
    }
    $res_cat->free();
}

// ตัวแปรฟอร์ม
$form_mode = 'add'; // add / edit
$edit_id = null;
$form_activity_id = '';
$form_activity_name = '';
$form_activity_desc = '';
$form_category_id = '';
$form_is_active = 1;

// ถ้ามีการขอแก้ไข
if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];
    if ($edit_id > 0) {
        $sql_one = "SELECT id, name, description, category_id, is_active FROM activities WHERE id = ? LIMIT 1";
        if ($stmt_one = $conn->prepare($sql_one)) {
            $stmt_one->bind_param('i', $edit_id);
            $stmt_one->execute();
            $res_one = $stmt_one->get_result();
            if ($row = $res_one->fetch_assoc()) {
                $form_mode = 'edit';
                $form_activity_id = $row['id'];
                $form_activity_name = $row['name'];
                $form_activity_desc = $row['description'];
                $form_category_id = $row['category_id'];
                $form_is_active = (int) $row['is_active'];
            }
            $res_one->free();
            $stmt_one->close();
        }
    }
}

// จัดการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    // เพิ่ม / แก้ไขกิจกรรม
    if ($action === 'save_activity') {
        $form_activity_id = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;
        $form_activity_name = trim($_POST['activity_name'] ?? '');
        $form_activity_desc = trim($_POST['activity_desc'] ?? '');
        $form_category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $form_is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

        if ($form_activity_name === '' || $form_category_id <= 0) {
            $message_type = 'error';
            $message = 'กรุณากรอกชื่อกิจกรรมและเลือกประเภทกิจกรรมให้ครบถ้วน';
        } else {

            // เพิ่ม
            if ($form_activity_id <= 0) {
                $sql_ins = "
                    INSERT INTO activities
                        (category_id, name, description, is_active, created_by, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, NOW())
                ";
                if ($stmt_ins = $conn->prepare($sql_ins)) {
                    $uid = $_SESSION['user_id'];
                    $stmt_ins->bind_param(
                        'issii',
                        $form_category_id,
                        $form_activity_name,
                        $form_activity_desc,
                        $form_is_active,
                        $uid
                    );
                    if ($stmt_ins->execute()) {
                        $message_type = 'success';
                        $message = 'เพิ่มกิจกรรมใหม่เรียบร้อยแล้ว';
                        // รีเซ็ตฟอร์ม
                        $form_mode = 'add';
                        $form_activity_id = '';
                        $form_activity_name = '';
                        $form_activity_desc = '';
                        $form_category_id = '';
                        $form_is_active = 1;
                    } else {
                        $message_type = 'error';
                        $message = 'ไม่สามารถเพิ่มกิจกรรมได้';
                    }
                    $stmt_ins->close();
                } else {
                    $message_type = 'error';
                    $message = 'ไม่สามารถเตรียมคำสั่งสำหรับเพิ่มกิจกรรมได้';
                }
            }

            // แก้ไข
            else {
                $sql_upd = "
                    UPDATE activities
                    SET category_id = ?, name = ?, description = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ";
                if ($stmt_upd = $conn->prepare($sql_upd)) {
                    $stmt_upd->bind_param(
                        'issii',
                        $form_category_id,
                        $form_activity_name,
                        $form_activity_desc,
                        $form_is_active,
                        $form_activity_id
                    );
                    if ($stmt_upd->execute()) {
                        $message_type = 'success';
                        $message = 'แก้ไขข้อมูลกิจกรรมเรียบร้อยแล้ว';
                        $form_mode = 'edit';
                    } else {
                        $message_type = 'error';
                        $message = 'ไม่สามารถแก้ไขกิจกรรมได้';
                    }
                    $stmt_upd->close();
                } else {
                    $message_type = 'error';
                    $message = 'ไม่สามารถเตรียมคำสั่งสำหรับแก้ไขกิจกรรมได้';
                }
            }
        }
    }

    // toggle สถานะเปิด/ปิด
    elseif ($action === 'toggle_status') {
        $toggle_id = isset($_POST['toggle_id']) ? (int) $_POST['toggle_id'] : 0;
        $current_status = isset($_POST['current_status']) ? (int) $_POST['current_status'] : 0;

        if ($toggle_id > 0) {
            $new_status = $current_status === 1 ? 0 : 1;
            $sql_toggle = "UPDATE activities SET is_active = ?, updated_at = NOW() WHERE id = ?";

            if ($stmt_tg = $conn->prepare($sql_toggle)) {
                $stmt_tg->bind_param('ii', $new_status, $toggle_id);
                if ($stmt_tg->execute()) {
                    $message_type = 'success';
                    $message = $new_status === 1
                        ? 'เปิดการใช้งานกิจกรรมเรียบร้อยแล้ว'
                        : 'ปิดการใช้งานกิจกรรมเรียบร้อยแล้ว';
                } else {
                    $message_type = 'error';
                    $message = 'ไม่สามารถเปลี่ยนสถานะกิจกรรมได้';
                }
                $stmt_tg->close();
            } else {
                $message_type = 'error';
                $message = 'ไม่สามารถเตรียมคำสั่งสำหรับเปลี่ยนสถานะกิจกรรมได้';
            }
        }
    }
}

// โหลดรายการกิจกรรมทั้งหมด
$activities = [];
$sql_act = "
    SELECT a.id, a.name, a.description, a.is_active, a.category_id,
           ac.code AS category_code, ac.name AS category_name
    FROM activities a
    INNER JOIN activity_categories ac ON ac.id = a.category_id
    ORDER BY ac.id ASC, a.name ASC
";
if ($res_act = $conn->query($sql_act)) {
    while ($row = $res_act->fetch_assoc()) {
        $activities[] = $row;
    }
    $res_act->free();
}

$conn->close();
?>

<!-- DataTables (jQuery is already in header) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<style>
    .manage-container {
        max-width: 1100px;
        margin: 20px auto 40px;
        padding: 0 15px;
        font-family: "Sarabun", sans-serif;
    }

    .manage-container h2 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: bold;
        margin-top: 18px;
        margin-bottom: 8px;
    }

    /* ฟอร์ม */
    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 10px;
    }

    .form-row-activity {
        gap: 18px;
        align-items: flex-end;
    }

    .form-group {
        flex: 1 1 200px;
        min-width: 200px;
    }

    /* ปรับสัดส่วนแต่ละช่องในแถวแรก */
    .form-group-name {
        flex: 2 1 320px;
    }

    .form-group-category {
        flex: 1 1 220px;
    }

    .form-group-status {
        flex: 0 0 180px;
    }

    .form-group label {
        display: block;
        margin-bottom: 3px;
        font-size: 0.9rem;
    }

    .form-group input[type="text"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 6px 8px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        background-color: #ffffff;
        box-sizing: border-box;
    }

    .form-group textarea {
        min-height: 70px;
    }

    /* ปุ่ม */
    .btn {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-primary {
        background-color: #2563eb;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #1d4ed8;
    }

    .btn-secondary {
        background-color: #6b7280;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #4b5563;
    }

    .btn-edit {
        font-size: 0.82rem;
        padding: 5px 10px;
        background-color: #22c55e;
        color: #fff;
        text-decoration: none;
        border-radius: 6px;
    }

    .btn-edit:hover {
        background-color: #16a34a;
    }

    .btn-toggle {
        font-size: 0.82rem;
        padding: 5px 10px;
    }

    /* ปุ่มในคอลัมน์จัดการ */
    .activity-actions {
        display: flex;
        gap: 6px;
        justify-content: center;
    }

    /* ตาราง */
    .table-wrapper {
        margin-top: 10px;
        overflow-x: auto;
    }

    /* override สไตล์ DataTables ให้เข้าชุด */
    table.dataTable.display.activity-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
        background-color: #ffffff;
    }

    table.activity-table th,
    table.activity-table td {
        border: 1px solid #e5e7eb;
        padding: 6px 8px;
    }

    table.activity-table th {
        background-color: #f9fafb;
        font-weight: 600;
    }

    table.activity-table tr:nth-child(even) {
        background-color: #fdfdfd;
    }

    /* badge */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.75rem;
        color: #fff;
        background-color: #17a2b8;
    }

    .badge-opd {
        background-color: #22c55e;
    }

    .badge-inj {
        background-color: #facc15;
        color: #1f2933;
    }

    .badge-inactive {
        background-color: #ef4444;
    }

    /* DataTables layout tweaks */
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 3px 6px;
        font-size: 0.85rem;
    }

    .dataTables_wrapper .dataTables_length select {
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 2px 4px;
        font-size: 0.85rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 2px 8px;
        margin: 0 1px;
        border-radius: 4px;
        border: 1px solid transparent;
        font-size: 0.84rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #2563eb;
        color: #ffffff !important;
        border-color: #2563eb;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #e5e7eb;
        color: #111827 !important;
    }

    .dataTables_info {
        font-size: 0.82rem;
    }

    /* responsive */
    @media (max-width: 768px) {

        .form-row,
        .form-row-activity {
            flex-direction: column;
        }

        .form-group-name,
        .form-group-category,
        .form-group-status {
            flex: 1 1 auto;
        }
    }
</style>

<div class="manage-container">
    <h2>จัดการกิจกรรม (Admin)</h2>

    <!-- ฟอร์มเพิ่ม / แก้ไขกิจกรรม -->
    <div class="section-title">
        <?= $form_mode === 'edit' ? 'แก้ไขกิจกรรม' : 'เพิ่มกิจกรรมใหม่'; ?>
    </div>

    <form method="post"
        action="manage_activities.php<?= $form_mode === 'edit' ? '?edit_id=' . (int) $form_activity_id : ''; ?>">
        <input type="hidden" name="form_action" value="save_activity">
        <input type="hidden" name="activity_id" value="<?= htmlspecialchars((string) $form_activity_id); ?>">

        <div class="form-row form-row-activity">
            <div class="form-group form-group-name">
                <label for="activity_name">ชื่อกิจกรรม</label>
                <input type="text" id="activity_name" name="activity_name"
                    value="<?= htmlspecialchars($form_activity_name); ?>" required>
            </div>
            <div class="form-group form-group-category">
                <label for="category_id">ประเภทกิจกรรม</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- เลือกประเภทกิจกรรม --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id']; ?>" <?= (string) $form_category_id === (string) $cat['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['name']); ?> (<?= htmlspecialchars($cat['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-group-status">
                <label for="is_active">สถานะการใช้งาน</label>
                <select id="is_active" name="is_active">
                    <option value="1" <?= (int) $form_is_active === 1 ? 'selected' : ''; ?>>เปิดใช้งาน</option>
                    <option value="0" <?= (int) $form_is_active === 0 ? 'selected' : ''; ?>>ปิดใช้งาน</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="activity_desc">คำอธิบาย (ถ้ามี)</label>
                <textarea id="activity_desc"
                    name="activity_desc"><?= htmlspecialchars($form_activity_desc); ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex:0 0 200px;">
                <button type="submit" class="btn btn-primary">
                    <?= $form_mode === 'edit' ? 'บันทึกการแก้ไข' : 'เพิ่มกิจกรรม'; ?>
                </button>
            </div>
            <?php if ($form_mode === 'edit'): ?>
                <div class="form-group" style="flex:0 0 200px;">
                    <a href="manage_activities.php" class="btn btn-secondary" style="text-decoration:none;">
                        ยกเลิก / เพิ่มกิจกรรมใหม่
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- ตารางรายการกิจกรรม -->
    <div class="section-title">รายการกิจกรรมทั้งหมด</div>
    <div class="table-wrapper">
        <table id="activities-table" class="activity-table display">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>ชื่อกิจกรรม</th>
                    <th style="width:160px;">ประเภท</th>
                    <th style="width:120px;">สถานะ</th>
                    <th>คำอธิบาย</th>
                    <th style="width:170px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($activities)): ?>
                    <?php $i = 1; ?>
                    <?php foreach ($activities as $act): ?>
                        <?php
                        $badgeClass = '';
                        if ($act['category_code'] === 'OPD') {
                            $badgeClass = 'badge-opd';
                        } elseif ($act['category_code'] === 'INJ') {
                            $badgeClass = 'badge-inj';
                        }
                        ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($act['name']); ?></td>
                            <td>
                                <span class="badge <?= $badgeClass; ?>">
                                    <?= htmlspecialchars($act['category_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int) $act['is_active'] === 1): ?>
                                    <span class="badge">เปิดใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars($act['description'])); ?></td>
                            <td>
                                <div class="activity-actions">
                                    <a href="manage_activities.php?edit_id=<?= (int) $act['id']; ?>" class="btn-edit">
                                        แก้ไข
                                    </a>

                                    <form method="post" action="manage_activities.php">
                                        <input type="hidden" name="form_action" value="toggle_status">
                                        <input type="hidden" name="toggle_id" value="<?= (int) $act['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?= (int) $act['is_active']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-toggle"
                                            onclick="return confirm('ยืนยันการเปลี่ยนสถานะกิจกรรมนี้หรือไม่?');">
                                            <?= (int) $act['is_active'] === 1 ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#activities-table').DataTable({
            pageLength: 25,
            language: {
                emptyTable: "ยังไม่มีกิจกรรมในระบบ",
                zeroRecords: "ไม่พบข้อมูลที่ค้นหา",
                sProcessing: "กำลังประมวลผล...",
                sLengthMenu: "แสดง _MENU_ แถว",
                sInfo: "แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว",
                sInfoEmpty: "แสดง 0 ถึง 0 จาก 0 แถว",
                sInfoFiltered: "(กรองข้อมูล _MAX_ ทุกแถว)",
                sSearch: "ค้นหา:",
                oPaginate: {
                    sFirst: "หน้าแรก",
                    sPrevious: "ก่อนหน้า",
                    sNext: "ถัดไป",
                    sLast: "หน้าสุดท้าย"
                }
            },
            columnDefs: [
                { orderable: false, targets: 5 } // Disable sorting on Action column
            ]
        });
    });
</script>

<?php if ($message !== ''): ?>
    <script>
        Swal.fire({
            icon: <?= json_encode($message_type === 'success' ? 'success' : 'error'); ?>,
            title: <?= json_encode($message_type === 'success' ? 'สำเร็จ' : 'เกิดข้อผิดพลาด'); ?>,
            text: <?= json_encode($message, JSON_UNESCAPED_UNICODE); ?>,
            confirmButtonText: 'ตกลง'
        });
    </script>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>