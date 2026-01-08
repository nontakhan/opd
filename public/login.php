<?php
// public/login.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../config/db_main.php';

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $login_error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $conn = getMainDBConnection();

        $sql = "SELECT id, username, password_hash, full_name, role, status 
                FROM users
                WHERE username = ?
                LIMIT 1";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if ((int)$row['status'] !== 1) {
                    $login_error = 'ผู้ใช้นี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
                } elseif (password_verify($password, $row['password_hash'])) {

                    $_SESSION['user_id']        = $row['id'];
                    $_SESSION['user_username']  = $row['username'];
                    $_SESSION['user_full_name'] = $row['full_name'];
                    $_SESSION['user_role']      = $row['role'];

                    header('Location: dashboard.php');
                    exit;

                } else {
                    $login_error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $login_error = 'ไม่พบชื่อผู้ใช้นี้ในระบบ';
            }

            $stmt->close();
            $conn->close();
        }
    }
}

$page_title = 'เข้าสู่ระบบ - Nurse Activity System';
require_once '../includes/header.php';
?>

<style>
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #bcdcff, #e6f1ff, #ffffff);
        font-family: "Sarabun", sans-serif;
        backdrop-filter: blur(4px);
    }

    .login-wrapper {
        width: 100%;
        max-width: 480px;
        padding: 20px;
    }

    .login-card {
        background-color: rgba(255, 255, 255, 0.93);
        border-radius: 18px;
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.15);
        padding: 40px 38px;
        border: 1px solid rgba(210, 220, 240, 0.7);
        backdrop-filter: blur(6px);
        text-align: center;
    }

    /* โลโก้ */
    .login-logo {
        width: 100px;
        height: 100px;
        object-fit: contain;
        margin-bottom: 14px;
        user-select: none;
    }

    .login-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0f172a;
    }

    .login-subtitle {
        margin-top: 6px;
        font-size: 0.95rem;
        color: #475569;
        margin-bottom: 25px;
    }

    /* INPUT GROUP */
    .form-group {
        margin-bottom: 16px;
        text-align: left;
    }

    .form-label {
        display: block;
        margin-bottom: 6px;
        font-size: 0.95rem;
        color: #475569;
        font-weight: 500;
    }

    .input-wrapper {
        position: relative;
        padding-right: 10px;
    }

    .input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2rem;
        color: #94a3b8;
    }

    .input-field {
        width: 100%;
        padding: 12px 18px 12px 44px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        font-size: 1rem;
        box-sizing: border-box;
        transition: 0.15s;
        color: #0f172a;
    }

    .input-field:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
        outline: none;
    }

    /* BUTTON */
    .login-btn {
        width: 100%;
        padding: 14px;
        border-radius: 10px;
        border: none;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: white;
        box-shadow: 0 8px 22px rgba(37, 99, 235, 0.35);
        transition: 0.15s;
        margin-top: 10px;
    }

    .login-btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.05);
    }

</style>

<div class="login-wrapper">
    <div class="login-card">

        <!-- โลโก้ -->
        <img src="../assets/img/logo.png" class="login-logo" alt="Hospital Logo">

        <div class="login-title">Nurse Activity System</div>
        <div class="login-subtitle">ระบบบันทึกกิจกรรมการพยาบาล โรงพยาบาลเทพา</div>

        <form method="post" action="login.php" autocomplete="off">

            <div class="form-group">
                <label class="form-label">ชื่อผู้ใช้</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="text" class="input-field" name="username" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">รหัสผ่าน</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" class="input-field" name="password" required>
                </div>
            </div>

            <button type="submit" class="login-btn">
                เข้าสู่ระบบ
            </button>

        </form>

    </div>
</div>

<?php if ($login_error !== ''): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'เกิดข้อผิดพลาด',
    text: <?= json_encode($login_error, JSON_UNESCAPED_UNICODE); ?>,
    confirmButtonText: 'ตกลง'
});
</script>
<?php endif; ?>
