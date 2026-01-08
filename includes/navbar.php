<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจชื่อไฟล์ที่กำลังเปิดอยู่ เพื่อทำ active highlight
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* ฟอนต์ทุกอย่าง */
html, body, * {
    font-family: "Sarabun", sans-serif !important;
}

/* NAVBAR */
.navbar {
    width: 100%;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px clamp(20px, 4vw, 70px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    position: sticky;
    top: 0;
    z-index: 1000;
    box-sizing: border-box;
}

/* โลโก้ + ชื่อระบบ */
.nav-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-logo {
    height: 36px;
    width: 36px;
    object-fit: contain;
}

.nav-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e293b;
}

/* เมนูด้านขวา */
.nav-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

/* ลิงก์เมนู */
.nav-link {
    font-size: 0.95rem;
    color: #334155;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    transition: 0.2s;
    white-space: nowrap;
}

/* hover effect */
.nav-link:hover {
    background-color: #e0ecff;
    color: #1d4ed8;
}

/* --- ACTIVE MENU --- */
.nav-link.active {
    background-color: #1d4ed8;
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(29,78,216,0.3);
}

/* ชื่อผู้ใช้ */
.nav-user {
    font-size: 0.9rem;
    font-weight: 600;
    color: #2563eb;
}

/* ปุ่มออกระบบ */
.nav-logout {
    padding: 6px 12px;
    font-size: 0.9rem;
    border-radius: 6px;
    background: #ef4444;
    color: white !important;
    text-decoration: none;
    transition: 0.2s;
}
.nav-logout:hover {
    background: #dc2626;
}

/* มือถือ */
@media (max-width: 768px) {
    .navbar {
        padding: 10px 16px;
    }
    .nav-right {
        gap: 10px;
    }
    .nav-title {
        font-size: 1rem;
    }
    .nav-user {
        display: none;
    }
}
</style>

<div class="navbar">

    <div class="nav-left">
        <img src="../assets/img/logo.png" class="nav-logo" alt="Logo">
        <div class="nav-title">Nurse Activity System</div>
    </div>

    <div class="nav-right">
        <?php if (!empty($_SESSION['user_id'])): ?>

            <a href="dashboard.php"
               class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
               Dashboard
            </a>

            <a href="activity_form.php"
               class="nav-link <?= $current_page === 'activity_form.php' ? 'active' : '' ?>">
               บันทึกกิจกรรม
            </a>

            <a href="activity_list.php"
               class="nav-link <?= $current_page === 'activity_list.php' ? 'active' : '' ?>">
               รายการกิจกรรม
            </a>

            <a href="report.php"
               class="nav-link <?= $current_page === 'report.php' ? 'active' : '' ?>">
               รายงาน
            </a>
            <a href="report_inj_summary.php" 
               class="nav-link <?= $current_page === 'report_inj_summary.php' ? 'active' : ''; ?>">
                สรุปยอด ฉีดยา/ทำแผล
            </a>


            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="manage_activities.php"
                   class="nav-link <?= $current_page === 'manage_activities.php' ? 'active' : '' ?>">
                   กิจกรรม
                </a>

                <a href="manage_users.php"
                   class="nav-link <?= $current_page === 'manage_users.php' ? 'active' : '' ?>">
                   ผู้ใช้
                </a>
            <?php endif; ?>

            <span class="nav-user">
                <?= htmlspecialchars($_SESSION['user_full_name']); ?>
            </span>

            <a href="logout.php" class="nav-logout">ออกจากระบบ</a>

        <?php endif; ?>
    </div>

</div>
