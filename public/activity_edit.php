<?php
// public/activity_edit.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';
require_once '../config/db_hospital.php';

$page_title = 'แก้ไขกิจกรรมที่บันทึก - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$connMain = getMainDBConnection();

$save_message = '';
$save_message_type = '';
$header_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ถ้าแก้ไขแบบ POST ให้ใช้ header_id จาก POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $header_id = isset($_POST['header_id']) ? (int)$_POST['header_id'] : 0;
}

// ถ้าไม่มี id → จบ
if ($header_id <= 0) {
    echo '<div style="max-width:800px;margin:20px auto;font-family:Sarabun;color:#d00;">ไม่พบรหัสรายการที่ต้องการแก้ไข</div>';
    require_once '../includes/footer.php';
    exit;
}

// ---------- โหลดข้อมูล header + detail ปัจจุบัน ----------

// โหลด header
$sql_header = "
    SELECT 
        h.*,
        ac.code AS category_code,
        ac.name AS category_name
    FROM patient_activity_header h
    LEFT JOIN activity_categories ac ON ac.id = h.category_id
    WHERE h.id = ?
    LIMIT 1
";

$header = null;
if ($stmt = $connMain->prepare($sql_header)) {
    $stmt->bind_param('i', $header_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $header = $row;
    }
    $res->free();
    $stmt->close();
}

if (!$header) {
    echo '<div style="max-width:800px;margin:20px auto;font-family:Sarabun;color:#d00;">ไม่พบข้อมูลรายการที่ต้องการแก้ไข</div>';
    require_once '../includes/footer.php';
    exit;
}

// โหลด detail (activity_id ทั้งหมดที่เคยเลือก)
$sql_detail = "
    SELECT activity_id
    FROM patient_activity_detail
    WHERE header_id = ?
";

$selected_activity_ids = [];
if ($stmt = $connMain->prepare($sql_detail)) {
    $stmt->bind_param('i', $header_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $selected_activity_ids[] = (int)$row['activity_id'];
    }
    $res->free();
    $stmt->close();
}

// โหลด activities ทั้งหมด (เหมือนหน้า activity_form)
$activities_by_category = [];
$sql_activities = "
    SELECT a.id, a.name, a.is_active, ac.code AS category_code, ac.name AS category_name
    FROM activities a
    INNER JOIN activity_categories ac ON ac.id = a.category_id
    ORDER BY ac.id, a.name
";
if ($result = $connMain->query($sql_activities)) {
    while ($row = $result->fetch_assoc()) {
        if ((int)$row['is_active'] !== 1) {
            continue;
        }
        $code = $row['category_code'];
        if (!isset($activities_by_category[$code])) {
            $activities_by_category[$code] = [];
        }
        $activities_by_category[$code][] = [
            'id'   => (int)$row['id'],
            'name' => $row['name']
        ];
    }
    $result->free();
}

// category code เริ่มต้น = ของเดิมที่บันทึกไว้
$selected_category_code = $header['category_code'];

// ---------- ถ้าเป็น POST: อัพเดตข้อมูล ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['form_action']) ? $_POST['form_action'] : '';

    if ($action === 'update_activity') {
        $selected_category_code = isset($_POST['category_code']) ? $_POST['category_code'] : 'OPD';
        $note       = isset($_POST['note']) ? trim($_POST['note']) : '';
        $activity_ids = isset($_POST['activity_ids']) && is_array($_POST['activity_ids'])
                            ? $_POST['activity_ids']
                            : [];

        if (empty($activity_ids)) {
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
                    $category_id = (int)$row_cat['id'];
                }
                $res_cat->free();
                $stmt_cat->close();
            }

            if ($category_id === null) {
                $save_message_type = 'error';
                $save_message = 'ไม่พบหมวดกิจกรรมที่เลือกในระบบ';
            } else {
                $connMain->begin_transaction();
                try {
                    // 1) อัพเดต header: category_id, note, updated_by, updated_at
                    $sql_upd_header = "
                        UPDATE patient_activity_header
                        SET note = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ";

                    if ($stmt_upd = $connMain->prepare($sql_upd_header)) {
                        $uid = $_SESSION['user_id'];
                        $stmt_upd->bind_param('sii', $category_id, $note, $uid, $header_id);
                        if (!$stmt_upd->execute()) {
                            throw new Exception('ไม่สามารถอัพเดตข้อมูลหัวรายการได้');
                        }
                        $stmt_upd->close();
                    } else {
                        throw new Exception('ไม่สามารถเตรียมคำสั่งอัพเดตหัวรายการได้');
                    }

                    // 2) ลบ detail เดิมทั้งหมดของ header นี้
                    $sql_del_detail = "DELETE FROM patient_activity_detail WHERE header_id = ?";
                    if ($stmt_del = $connMain->prepare($sql_del_detail)) {
                        $stmt_del->bind_param('i', $header_id);
                        if (!$stmt_del->execute()) {
                            throw new Exception('ไม่สามารถลบข้อมูลรายละเอียดเดิมได้');
                        }
                        $stmt_del->close();
                    } else {
                        throw new Exception('ไม่สามารถเตรียมคำสั่งลบรายละเอียดเดิมได้');
                    }

                    // 3) แทรก detail ใหม่ตาม activity_ids
                    $sql_ins_detail = "
                        INSERT INTO patient_activity_detail (header_id, activity_id)
                        VALUES (?, ?)
                    ";
                    if ($stmt_ins = $connMain->prepare($sql_ins_detail)) {
                        foreach ($activity_ids as $aid) {
                            $aid_int = (int)$aid;
                            $stmt_ins->bind_param('ii', $header_id, $aid_int);
                            if (!$stmt_ins->execute()) {
                                throw new Exception('ไม่สามารถบันทึกรายละเอียดกิจกรรมใหม่บางส่วนได้');
                            }
                        }
                        $stmt_ins->close();
                    } else {
                        throw new Exception('ไม่สามารถเตรียมคำสั่งบันทึกรายละเอียดใหม่ได้');
                    }

                    $connMain->commit();
                    $save_message_type = 'success';
                    $save_message = 'อัพเดตข้อมูลเรียบร้อยแล้ว';

                    // อัพเดตตัวแปรในหน้านี้ (selected_activity_ids, header note, category)
                    $selected_activity_ids = array_map('intval', $activity_ids);
                    $header['note'] = $note;
                    $header['category_code'] = $selected_category_code;

                } catch (Exception $e) {
                    $connMain->rollback();
                    $save_message_type = 'error';
                    $save_message = $e->getMessage();
                }
            }
        }
    }
}

$connMain->close();
?>

<style>
.activity-edit-container {
    max-width: 1000px;
    margin: 20px auto 40px;
    padding: 0 15px;
    font-family: "Sarabun", sans-serif;
}

/* card รวมแต่ละส่วน */
.edit-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
    padding: 14px 16px 16px;
    margin-bottom: 14px;
}

/* header ของ card */
.edit-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.edit-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #111827;
}
.edit-card-subtitle {
    font-size: 0.85rem;
    color: #6b7280;
}
.edit-card-icon {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    background: #e5f3ff;
    color: #1d4ed8;
}

/* กล่องข้อมูลผู้ป่วย */
.patient-info-line {
    background: #f9fafb;
    border-radius: 8px;
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    font-size: 0.9rem;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 4px;
}
.patient-info-line span.label {
    font-weight: 600;
}

/* ฟอร์มทั่วไป */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}
.form-group {
    flex: 1;
    min-width: 180px;
}
.form-group label {
    display: block;
    margin-bottom: 3px;
    font-size: 0.9rem;
    color: #374151;
}
.form-group textarea {
    width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.9rem;
    min-height: 80px;
    box-sizing: border-box;
}
.form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
}

/* ปุ่ม */
.btn {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
    font-family: "Sarabun", sans-serif;
}
.btn-primary {
    background-color: #2563eb;
    color: #fff;
}
.btn-primary:hover {
    background-color: #1d4ed8;
}
.btn-secondary {
    background-color: #e5e7eb;
    color: #111827;
}
.btn-secondary:hover {
    background-color: #d1d5db;
}
.btn-back {
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    font-size: 0.85rem;
    background-color: #f9fafb;
    color: #374151;
}

/* ปุ่มหมวดกิจกรรม */
.btn-category {
    margin-right: 6px;
    margin-bottom: 6px;
}
.btn-category.active {
    background-color: #16a34a;
    color: #ffffff;
}

/* กล่องรายการกิจกรรม – 3 คอลัมน์ */
.activity-list-wrapper {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px;
    background-color: #f9fafb;
}

.activity-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.activity-option {
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background-color: #ffffff;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color 0.12s ease, border-color 0.12s ease, box-shadow 0.12s ease;
}
.activity-option input[type="checkbox"] {
    transform: scale(1.1);
    accent-color: #2563eb;
}
.activity-option:hover {
    background-color: #eff6ff;
    border-color: #2563eb;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.12);
}

/* มือถือ: ให้เหลือ 1–2 คอลัมน์ */
@media (max-width: 900px) {
    .activity-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .activity-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    .activity-edit-container {
        padding: 0 10px;
    }
}
/* dropdown style ให้เข้ากับระบบ */
.select-ui {
    width: 100%;
    padding: 9px 14px;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    font-size: 0.9rem;
    font-family: "Sarabun", sans-serif;
    appearance: none;
    background-image:
        linear-gradient(45deg, transparent 50%, #64748b 50%),
        linear-gradient(135deg, #64748b 50%, transparent 50%);
    background-position:
        calc(100% - 20px) 50%,
        calc(100% - 14px) 50%;
    background-size: 6px 6px;
    background-repeat: no-repeat;
}

.select-ui:focus {
    outline: none;
    border-color: #2563eb;
    background-color: #ffffff;
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
}

</style>

<div class="activity-edit-container">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h2 style="margin:0;font-size:1.3rem;">แก้ไขกิจกรรมที่บันทึก</h2>
        <a href="activity_list.php" class="btn-back">&laquo; กลับไปหน้ารายการกิจกรรม</a>
    </div>

    <!-- การ์ดข้อมูลผู้ป่วย -->
    <div class="edit-card">
        <div class="edit-card-header">
            <div class="edit-card-icon">👤</div>
            <div>
                <div class="edit-card-title">1. ข้อมูลผู้ป่วย</div>
                <div class="edit-card-subtitle">ข้อมูลผู้ป่วยในครั้งที่บันทึกกิจกรรมนี้</div>
            </div>
        </div>

        <div class="patient-info-line">
            <span class="label">HN:</span> <?= htmlspecialchars($header['hn']); ?>
        </div>
        <div class="patient-info-line">
            <span class="label">ชื่อ-สกุล:</span> <?= htmlspecialchars($header['patient_name']); ?>
        </div>
        <div class="patient-info-line">
            <span class="label">เลขบัตรประชาชน:</span> <?= htmlspecialchars($header['cid']); ?>
        </div>
        <div class="patient-info-line">
            <span class="label">VN:</span> <?= htmlspecialchars($header['vn']); ?>
        </div>
        <div class="patient-info-line">
            <span class="label">วันที่-เวลา Visit:</span>
            <?= $header['visit_date'] ? date('d/m/Y', strtotime($header['visit_date'])) : ''; ?>
            <?= $header['visit_time'] ? substr($header['visit_time'], 0, 5) : ''; ?>
        </div>
    </div>

    <!-- การ์ดแก้ไขกิจกรรม -->
    <div class="edit-card">
        <div class="edit-card-header">
            <div class="edit-card-icon" style="background:#dcfce7;color:#16a34a;">✔</div>
            <div>
                <div class="edit-card-title">2. แก้ไขรายละเอียดกิจกรรม</div>
                <div class="edit-card-subtitle">เลือกประเภทกิจกรรมและรายการกิจกรรมที่ทำกับผู้ป่วย</div>
                <div style="font-size:0.85rem;color:#6b7280;margin-top:4px;"> * ไม่สามารถเปลี่ยนประเภทกิจกรรมหลังจากบันทึกแล้ว </div>
            </div>
        </div>

        <form method="post" action="activity_edit.php?id=<?= (int)$header_id; ?>">
            <input type="hidden" name="form_action" value="update_activity">
            <input type="hidden" name="header_id" value="<?= (int)$header_id; ?>">
            <input type="hidden" name="category_code" id="category_code" value="<?= htmlspecialchars($selected_category_code); ?>">

            <!-- ปุ่มเลือกประเภทกิจกรรม -->
            <div class="form-row">
                <div class="form-group">
                    <label>ประเภทกิจกรรม</label>
                    <button type="button"
                            class="btn btn-category active"
                            disabled>
                        <?= $header['category_name']; ?>
                    </button>
                </div>
            </div>

            <!-- รายการกิจกรรม (3 คอลัมน์) -->
            <div class="form-row">
                <div class="form-group">
                    <label>รายการกิจกรรม (เลือกได้หลายข้อ)</label>
                    <div class="activity-list-wrapper">
                        <div id="activityList" class="activity-grid">
                            <!-- ใส่ด้วย JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- หมายเหตุ -->
            <div class="form-row">
                <div class="form-group">
                    <label for="note">หมายเหตุ</label>
                    <textarea name="note" id="note"><?= htmlspecialchars($header['note']); ?></textarea>
                </div>
            </div>

            <div class="form-row" style="margin-top:5px;">
                <div class="form-group" style="flex:0 0 220px;">
                    <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// ===============================
// activity_edit.php (LOCK CATEGORY)
// ===============================

// ข้อมูลกิจกรรมทั้งหมดจาก PHP
const activitiesData = <?= json_encode($activities_by_category, JSON_UNESCAPED_UNICODE); ?>;

// หมวดกิจกรรม "เดิม" จากฐานข้อมูล (ห้ามเปลี่ยน)
const selectedCategoryCode = <?= json_encode($header['category_code']); ?>;

// activity_id ที่เคยเลือกไว้
const preSelectedActivityIds = <?= json_encode($selected_activity_ids); ?>;

// render รายการกิจกรรม (เฉพาะหมวดเดิม)
function renderActivities() {
    const container = document.getElementById('activityList');
    container.innerHTML = '';

    // ถ้าไม่มีข้อมูลในหมวดนี้
    if (
        !activitiesData[selectedCategoryCode] ||
        activitiesData[selectedCategoryCode].length === 0
    ) {
        container.innerHTML = `
            <div style="
                grid-column:1/-1;
                color:#6b7280;
                font-size:0.85rem;
                text-align:center;
                padding:10px;
            ">
                ไม่พบรายการกิจกรรมในหมวดนี้
            </div>
        `;
        return;
    }

    // วนแสดงกิจกรรม
    activitiesData[selectedCategoryCode].forEach(act => {
        const label = document.createElement('label');
        label.className = 'activity-option';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'activity_ids[]';
        checkbox.value = act.id;

        // ถ้าเคยเลือกไว้ → checked
        if (preSelectedActivityIds.includes(parseInt(act.id))) {
            checkbox.checked = true;
        }

        const text = document.createTextNode(' ' + act.name);

        label.appendChild(checkbox);
        label.appendChild(text);
        container.appendChild(label);
    });
}

// โหลดครั้งเดียวตอนเปิดหน้า
document.addEventListener('DOMContentLoaded', function () {
    renderActivities();
});
</script>


<?php if ($save_message !== ''): ?>
<script>
Swal.fire({
    icon: <?= json_encode($save_message_type === 'success' ? 'success' : 'error'); ?>,
    title: <?= json_encode($save_message_type === 'success' ? 'บันทึกข้อมูล' : 'เกิดข้อผิดพลาด'); ?>,
    text: <?= json_encode($save_message, JSON_UNESCAPED_UNICODE); ?>,
    confirmButtonText: 'ตกลง'
}).then(() => {
    window.location.href = "activity_list.php";
});

</script>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
