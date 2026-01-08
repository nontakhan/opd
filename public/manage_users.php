<?php
// public/manage_users.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

// เช็คสิทธิ์ admin
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $page_title = 'ไม่มีสิทธิ์เข้าถึง - Nurse Activity System';
    require_once '../includes/header.php';
    require_once '../includes/navbar.php';
    echo '<div style="max-width:800px;margin:20px auto;font-family:\'Sarabun\',sans-serif;color:#d00;">
            คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการผู้ใช้ (Admin เท่านั้น)
          </div>';
    require_once '../includes/footer.php';
    exit;
}

$page_title = 'จัดการผู้ใช้ - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$conn = getMainDBConnection();

$message = '';
$message_type = ''; // success / error

// ค่าเริ่มต้นของฟอร์ม
$form_mode      = 'add'; // add / edit
$form_user_id   = '';
$form_username  = '';
$form_full_name = '';
$form_role      = 'nurse';
$form_status    = 1;

// ถ้าเข้าด้วย ?edit_id=...
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    if ($edit_id > 0) {
        $sql_one = "SELECT id, username, full_name, role, status FROM users WHERE id = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql_one)) {
            $stmt->bind_param('i', $edit_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $form_mode      = 'edit';
                $form_user_id   = $row['id'];
                $form_username  = $row['username'];
                $form_full_name = $row['full_name'];
                $form_role      = $row['role'];
                $form_status    = (int)$row['status'];
            }
            $res->free();
            $stmt->close();
        }
    }
}

// ---------- จัดการ POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    // บันทึก (เพิ่ม/แก้ไข)
    if ($action === 'save_user') {
        $form_user_id   = (int)($_POST['user_id'] ?? 0);
        $form_username  = trim($_POST['username'] ?? '');
        $form_full_name = trim($_POST['full_name'] ?? '');
        $form_role      = $_POST['role'] ?? 'nurse';
        $form_status    = (int)($_POST['status'] ?? 1);
        $password       = trim($_POST['password'] ?? '');

        if ($form_username === '' || $form_full_name === '') {
            $message_type = 'error';
            $message = 'กรุณากรอกชื่อผู้ใช้และชื่อ-สกุลให้ครบถ้วน';
        } elseif (!in_array($form_role, ['admin','nurse'], true)) {
            $message_type = 'error';
            $message = 'สิทธิ์ผู้ใช้ไม่ถูกต้อง';
        } else {
            // เพิ่ม
            if ($form_user_id <= 0) {
                if ($password === '') {
                    $message_type = 'error';
                    $message = 'กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่';
                } else {
                    // เช็ค username ซ้ำ
                    $sql_check = "SELECT COUNT(*) AS c FROM users WHERE username = ?";
                    $is_dup = false;
                    if ($stmt = $conn->prepare($sql_check)) {
                        $stmt->bind_param('s', $form_username);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $is_dup = ((int)$row['c'] > 0);
                        }
                        $res->free();
                        $stmt->close();
                    }

                    if ($is_dup) {
                        $message_type = 'error';
                        $message = 'ชื่อผู้ใช้ซ้ำ กรุณาใช้ชื่ออื่น';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);

                        $sql_ins = "
                            INSERT INTO users
                                (username, password_hash, full_name, role, status, created_at, updated_at)
                            VALUES
                                (?, ?, ?, ?, ?, NOW(), NOW())
                        ";
                        if ($stmt = $conn->prepare($sql_ins)) {
                            $stmt->bind_param(
                                'ssssi',
                                $form_username,
                                $password_hash,
                                $form_full_name,
                                $form_role,
                                $form_status
                            );
                            if ($stmt->execute()) {
                                $message_type = 'success';
                                $message = 'เพิ่มผู้ใช้ใหม่เรียบร้อยแล้ว';
                                // เคลียร์ฟอร์ม
                                $form_mode      = 'add';
                                $form_user_id   = '';
                                $form_username  = '';
                                $form_full_name = '';
                                $form_role      = 'nurse';
                                $form_status    = 1;
                            } else {
                                $message_type = 'error';
                                $message = 'ไม่สามารถเพิ่มผู้ใช้ได้';
                            }
                            $stmt->close();
                        } else {
                            $message_type = 'error';
                            $message = 'ไม่สามารถเตรียมคำสั่งเพิ่มผู้ใช้ได้';
                        }
                    }
                }
            }
            // แก้ไข
            else {
                // ถ้ามี password ใหม่ → อัพเดตด้วย
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $sql_upd = "
                        UPDATE users
                        SET full_name = ?, role = ?, status = ?, password_hash = ?, updated_at = NOW()
                        WHERE id = ?
                    ";
                    if ($stmt = $conn->prepare($sql_upd)) {
                        $stmt->bind_param(
                            'ssisi',
                            $form_full_name,
                            $form_role,
                            $form_status,
                            $password_hash,
                            $form_user_id
                        );
                        if ($stmt->execute()) {
                            $message_type = 'success';
                            $message = 'แก้ไขข้อมูลผู้ใช้และอัพเดตรหัสผ่านเรียบร้อยแล้ว';
                        } else {
                            $message_type = 'error';
                            $message = 'ไม่สามารถแก้ไขข้อมูลผู้ใช้ได้';
                        }
                        $stmt->close();
                    } else {
                        $message_type = 'error';
                        $message = 'ไม่สามารถเตรียมคำสั่งแก้ไขผู้ใช้ได้';
                    }
                } else {
                    // ไม่เปลี่ยนรหัสผ่าน
                    $sql_upd = "
                        UPDATE users
                        SET full_name = ?, role = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ";
                    if ($stmt = $conn->prepare($sql_upd)) {
                        $stmt->bind_param(
                            'ssii',
                            $form_full_name,
                            $form_role,
                            $form_status,
                            $form_user_id
                        );
                        if ($stmt->execute()) {
                            $message_type = 'success';
                            $message = 'แก้ไขข้อมูลผู้ใช้เรียบร้อยแล้ว';
                        } else {
                            $message_type = 'error';
                            $message = 'ไม่สามารถแก้ไขข้อมูลผู้ใช้ได้';
                        }
                        $stmt->close();
                    } else {
                        $message_type = 'error';
                        $message = 'ไม่สามารถเตรียมคำสั่งแก้ไขผู้ใช้ได้';
                    }
                }
            }
        }
    }
    // toggle status
    elseif ($action === 'toggle_status') {
        $toggle_id      = (int)($_POST['toggle_id'] ?? 0);
        $current_status = (int)($_POST['current_status'] ?? 0);

        if ($toggle_id > 0) {
            $new_status = $current_status === 1 ? 0 : 1;

            $sql_tg = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
            if ($stmt = $conn->prepare($sql_tg)) {
                $stmt->bind_param('ii', $new_status, $toggle_id);
                if ($stmt->execute()) {
                    $message_type = 'success';
                    $message = $new_status === 1
                        ? 'เปิดการใช้งานบัญชีผู้ใช้เรียบร้อยแล้ว'
                        : 'ปิดการใช้งานบัญชีผู้ใช้เรียบร้อยแล้ว';
                } else {
                    $message_type = 'error';
                    $message = 'ไม่สามารถเปลี่ยนสถานะผู้ใช้ได้';
                }
                $stmt->close();
            } else {
                $message_type = 'error';
                $message = 'ไม่สามารถเตรียมคำสั่งเปลี่ยนสถานะผู้ใช้ได้';
            }
        }
    }
}

// โหลดรายการผู้ใช้ทั้งหมด
$users = [];
$sql_users = "
    SELECT id, username, full_name, role, status, created_at
    FROM users
    ORDER BY role DESC, username ASC
";

if ($res = $conn->query($sql_users)) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $res->free();
}

$conn->close();
?>

<style>
.manage-users-page {
    max-width: 1100px;
    margin: 20px auto 60px;
    padding: 0 clamp(16px, 4vw, 40px);
    font-family: "Sarabun", sans-serif;
    box-sizing: border-box;
}

.manage-header {
    margin-bottom: 14px;
}
.manage-header h2 {
    margin: 0;
    font-size: 1.4rem;
}
.manage-header p {
    margin: 4px 0 0;
    font-size: 0.9rem;
    color: #6b7280;
}

/* การ์ด */
.card {
    background-color: #ffffff;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    padding: 16px 18px 18px;
    margin-bottom: 18px;
}

/* ส่วนหัวในการ์ด */
.card-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
}

/* ฟอร์ม */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 10px;
}
.form-group {
    flex: 1 1 200px;
    min-width: 200px;
}
.form-group label {
    display: block;
    margin-bottom: 4px;
    font-size: 0.86rem;
    color: #4b5563;
}
.form-group input[type="text"],
.form-group input[type="password"],
.form-group select {
    width: 100%;
    padding: 7px 9px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.9rem;
    background-color: #ffffff;
    box-sizing: border-box;
}
.form-group input[type="text"]:focus,
.form-group input[type="password"]:focus,
.form-group select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.2);
    outline: none;
}

/* ปุ่ม */
.btn {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    font-size: 0.88rem;
}
.btn-primary {
    background-color: #2563eb;
    color: #ffffff;
}
.btn-primary:hover {
    background-color: #1d4ed8;
}
.btn-secondary {
    background-color: #6b7280;
    color: #ffffff;
}
.btn-secondary:hover {
    background-color: #4b5563;
}
.btn-edit {
    font-size: 0.8rem;
    padding: 5px 11px;
    background-color: #22c55e;
    color: #fff;
    text-decoration: none;
    border-radius: 999px;
}
.btn-edit:hover {
    background-color: #16a34a;
}
.btn-toggle {
    font-size: 0.8rem;
    padding: 5px 11px;
}

/* ตารางผู้ใช้ */
.table-card {
    margin-top: 10px;
}
.table-wrapper {
    margin-top: 6px;
    overflow-x: auto;
}
table.users-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
    background-color: #ffffff;
}
table.users-table th,
table.users-table td {
    border: 1px solid #e5e7eb;
    padding: 6px 8px;
}
table.users-table th {
    background-color: #f9fafb;
    font-weight: 600;
}
table.users-table tr:nth-child(even) {
    background-color: #fdfdfd;
}

/* badge */
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    color: #fff;
    background-color: #0ea5e9;
}
.badge-admin {
    background-color: #7c3aed;
}
.badge-nurse {
    background-color: #10b981;
}
.badge-inactive {
    background-color: #ef4444;
}

/* ปุ่มในคอลัมน์จัดการ */
.user-actions {
    display: flex;
    justify-content: center;
    gap: 6px;
}

/* mobile */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
}
</style>

<div class="manage-users-page">
    <div class="manage-header">
        <h2>จัดการผู้ใช้</h2>
        <p>เพิ่ม/แก้ไขสิทธิ์ผู้ใช้สำหรับเข้าสู่ระบบบันทึกกิจกรรม</p>
    </div>

    <!-- การ์ดฟอร์ม -->
    <div class="card">
        <div class="card-title">
            <?= $form_mode === 'edit' ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่'; ?>
        </div>

        <form method="post" action="manage_users.php<?= $form_mode === 'edit' ? '?edit_id=' . (int)$form_user_id : ''; ?>">
            <input type="hidden" name="form_action" value="save_user">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($form_user_id); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($form_username); ?>"
                           <?= $form_mode === 'edit' ? 'readonly' : 'required'; ?>>
                </div>
                <div class="form-group">
                    <label for="full_name">ชื่อ-สกุล</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($form_full_name); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:0 0 200px;">
                    <label for="role">สิทธิ์</label>
                    <select id="role" name="role">
                        <option value="nurse" <?= $form_role === 'nurse' ? 'selected' : ''; ?>>
                            พยาบาล/เจ้าหน้าที่
                        </option>
                        <option value="admin" <?= $form_role === 'admin' ? 'selected' : ''; ?>>
                            ผู้ดูแลระบบ (Admin)
                        </option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 0 200px;">
                    <label for="status">สถานะ</label>
                    <select id="status" name="status">
                        <option value="1" <?= (int)$form_status === 1 ? 'selected' : ''; ?>>เปิดใช้งาน</option>
                        <option value="0" <?= (int)$form_status === 0 ? 'selected' : ''; ?>>ปิดใช้งาน</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password">
                        รหัสผ่าน <?= $form_mode === 'edit' ? '(เว้นว่างถ้าไม่เปลี่ยน)' : '(จำเป็นต้องกรอก)'; ?>
                    </label>
                    <input type="password" id="password" name="password"
                           <?= $form_mode === 'add' ? 'required' : ''; ?>>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:0 0 220px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $form_mode === 'edit' ? 'บันทึกการแก้ไข' : 'เพิ่มผู้ใช้'; ?>
                    </button>
                </div>
                <?php if ($form_mode === 'edit'): ?>
                    <div class="form-group" style="flex:0 0 220px;">
                        <a href="manage_users.php" class="btn btn-secondary" style="text-decoration:none;">
                            ยกเลิก / เพิ่มผู้ใช้ใหม่
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- การ์ดตารางผู้ใช้ -->
    <div class="card table-card">
        <div class="card-title">รายการผู้ใช้ทั้งหมด</div>

        <div class="table-wrapper">
            <table class="users-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-สกุล</th>
                        <th style="width:120px;">สิทธิ์</th>
                        <th style="width:120px;">สถานะ</th>
                        <th style="width:160px;">สร้างเมื่อ</th>
                        <th style="width:190px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#6b7280;">
                                ยังไม่มีผู้ใช้ในระบบ
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $i = 1; ?>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $badgeRoleClass = $u['role'] === 'admin' ? 'badge-admin' : 'badge-nurse';
                            ?>
                            <tr>
                                <td><?= $i++; ?></td>
                                <td><?= htmlspecialchars($u['username']); ?></td>
                                <td><?= htmlspecialchars($u['full_name']); ?></td>
                                <td>
                                    <span class="badge <?= $badgeRoleClass; ?>">
                                        <?= htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$u['status'] === 1): ?>
                                        <span class="badge">เปิดใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">ปิดการใช้งาน</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $u['created_at'] ? date('d/m/Y H:i', strtotime($u['created_at'])) : ''; ?>
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <a href="manage_users.php?edit_id=<?= (int)$u['id']; ?>" class="btn-edit">
                                            แก้ไข
                                        </a>

                                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                            <form method="post" action="manage_users.php">
                                                <input type="hidden" name="form_action" value="toggle_status">
                                                <input type="hidden" name="toggle_id" value="<?= (int)$u['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?= (int)$u['status']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-toggle"
                                                    onclick="return confirm('ยืนยันการเปลี่ยนสถานะบัญชีผู้ใช้นี้หรือไม่?');">
                                                    <?= (int)$u['status'] === 1 ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
