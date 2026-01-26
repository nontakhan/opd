<?php
// public/activity_list.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

$page_title = 'รายการบันทึกกิจกรรมพยาบาล - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$conn = getMainDBConnection();

// --- รับค่าจาก filter ---
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$category = isset($_GET['category']) ? $_GET['category'] : 'ALL'; // ALL, OPD, INJ

$rows = [];

// ดึงล่าสุดไม่เกิน 500 แถว
$sql = "
    SELECT
        h.id,
        h.hn,
        h.patient_name,
        h.visit_date,
        h.visit_time,
        ac.name AS category_name,
        ac.code AS category_code,
        GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ') AS activity_names,
        u.full_name AS nurse_name
    FROM patient_activity_header h
    INNER JOIN activity_categories ac ON ac.id = h.category_id
    LEFT JOIN patient_activity_detail d ON d.header_id = h.id
    LEFT JOIN activities a ON a.id = d.activity_id
    LEFT JOIN users u ON u.id = h.created_by
    WHERE h.visit_date BETWEEN ? AND ?
";

$params = [$start_date, $end_date];
$types = 'ss';

if ($category === 'OPD' || $category === 'INJ') {
    $sql .= " AND ac.code = ? ";
    $params[] = $category;
    $types .= 's';
}

$sql .= "
    GROUP BY h.id, h.hn, h.patient_name, h.visit_date, h.visit_time, ac.name, ac.code, u.full_name
    ORDER BY h.visit_date DESC, h.visit_time DESC, h.id DESC
    LIMIT 500
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $stmt->close();
}

$conn->close();

?>

<!-- DataTables (jQuery is already in header) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<!-- SweetAlert2 is already in header -->

<style>
    .activity-list-container {
        max-width: 1200px;
        margin: 20px auto 40px;
        padding: 0 16px;
        font-family: "Sarabun", sans-serif;
    }

    .activity-list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .activity-list-title {
        font-size: 1.3rem;
        font-weight: 700;
    }

    .activity-list-subtitle {
        font-size: 0.9rem;
        color: #6b7280;
    }

    .filter-card {
        margin-top: 8px;
        padding: 12px 16px 14px;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        border: 1px solid #e2e8f0;
    }

    /* แถว filter ด้านบนตาราง */
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px 20px;
        /* ปรับให้ช่องวันที่ห่างกันกำลังดี */
        align-items: flex-end;
    }

    .filter-group {
        flex: 1 1 230px;
        min-width: 210px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 4px;
        font-size: 0.86rem;
        color: #475569;
        font-weight: 600;
    }

    .filter-group input[type="date"],
    .filter-group input[type="text"] {
        width: 100%;
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        background-color: #f8fafc;
        transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s;
    }

    .filter-group input[type="date"]:focus,
    .filter-group input[type="text"]:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
        background-color: #ffffff;
    }

    /* ปุ่มเลือกประเภท */
    .category-toggle {
        display: inline-flex;
        border-radius: 999px;
        background-color: #f3f4f6;
        padding: 2px;
        gap: 4px;
    }

    .category-toggle button {
        border: none;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 0.86rem;
        cursor: pointer;
        background: transparent;
        color: #4b5563;
    }

    .category-toggle button.active {
        background-color: #facc15;
        color: #1f2937;
        font-weight: 600;
    }

    /* ปุ่มแสดงรายงาน */
    .btn-primary {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
    }

    /* การ์ดตาราง */
    .activity-table-card {
        margin-top: 16px;
        padding: 12px 16px 16px;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07);
        border: 1px solid #e5e7eb;
    }

    /* DataTable */
    #activityTable {
        width: 100%;
        font-size: 0.88rem;
    }

    #activityTable thead th {
        background-color: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }

    /* badge */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.75rem;
        color: #fff;
    }

    .badge-opd {
        background-color: #22c55e;
    }

    .badge-inj {
        background-color: #f97316;
    }

    /* ปุ่มจัดการ */
    .btn-action {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        border: none;
        cursor: pointer;
        color: #fff;
    }

    .btn-edit {
        background-color: #22c55e;
    }

    .btn-edit:hover {
        background-color: #16a34a;
    }

    .btn-delete {
        background-color: #ef4444;
        margin-left: 4px;
    }

    .btn-delete:hover {
        background-color: #dc2626;
    }

    @media (max-width: 768px) {
        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }
    }

    .btn-delete {
        background-color: #ef4444;
        margin-left: 4px;
    }

    .btn-delete:hover {
        background-color: #dc2626;
    }
</style>

<div class="activity-list-container">
    <div class="activity-list-header">
        <div>
            <div class="activity-list-title">รายการบันทึกกิจกรรมพยาบาล</div>
            <div class="activity-list-subtitle">
                แสดงรายการบันทึกล่าสุด (จำกัด 500 รายการ) สามารถค้นหา / เรียง / เลือกจำนวนแถวได้
            </div>
        </div>
        <div>
            <a href="activity_form.php" class="btn-primary">+ บันทึกกิจกรรมใหม่</a>
        </div>
    </div>

    <!-- ฟอร์ม filter -->
    <div class="filter-card">
        <form method="get" action="activity_list.php">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="start_date">วันที่เริ่ม</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group">
                    <label for="end_date">วันที่สิ้นสุด</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-group">
                    <label>ประเภทกิจกรรม</label>
                    <div class="category-toggle">
                        <button type="submit" name="category" value="ALL"
                            class="<?= $category === 'ALL' ? 'active' : ''; ?>">ทั้งหมด</button>
                        <button type="submit" name="category" value="OPD"
                            class="<?= $category === 'OPD' ? 'active' : ''; ?>">กิจกรรม OPD</button>
                        <button type="submit" name="category" value="INJ"
                            class="<?= $category === 'INJ' ? 'active' : ''; ?>">กิจกรรมห้องฉีดยา/ทำแผล</button>
                    </div>
                </div>
                <div class="filter-group" style="flex:0 0 160px;">
                    <button type="submit" class="btn-primary" style="width:100%;">แสดงรายงาน</button>
                </div>
            </div>
        </form>
    </div>

    <div class="activity-table-card">
        <table id="activityTable" class="display">
            <thead>
                <tr>
                    <th>#</th>
                    <th>วันที่</th>
                    <th>เวลา</th>
                    <th>HN</th>
                    <th>ชื่อ-สกุล</th>
                    <th>ประเภท</th>
                    <th>กิจกรรมที่ทำ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $badgeClass = '';
                    if ($r['category_code'] === 'OPD') {
                        $badgeClass = 'badge-opd';
                    } elseif ($r['category_code'] === 'INJ') {
                        $badgeClass = 'badge-inj';
                    }
                    ?>
                    <tr>
                        <td><?= $i++; ?></td>
                        <td><?= $r['visit_date'] ? date('d/m/Y', strtotime($r['visit_date'])) : ''; ?></td>
                        <td><?= $r['visit_time'] ? substr($r['visit_time'], 0, 5) : ''; ?></td>
                        <td><?= htmlspecialchars($r['hn']); ?></td>
                        <td><?= htmlspecialchars($r['patient_name']); ?></td>
                        <td>
                            <span class="badge <?= $badgeClass; ?>">
                                <?= htmlspecialchars($r['category_name']); ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['activity_names']); ?></td>
                        <td style="text-align:center; white-space:nowrap;">
                            <a href="activity_edit.php?id=<?= (int) $r['id']; ?>" class="btn-action btn-edit">แก้ไข</a>

                            <button type="button" class="btn-action btn-delete" data-id="<?= (int) $r['id']; ?>">
                                ลบ
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Check for 'search' param
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');

        // 1. Init DataTables
        $('#activityTable').DataTable({
            search: { search: searchParam || "" },
            pageLength: 25,
            order: [[1, 'desc'], [2, 'desc']],
            language: {
                emptyTable: "ยังไม่มีรายการบันทึกกิจกรรม",
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
            }
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-delete');
        if (!btn) return;

        e.preventDefault();
        const id = btn.dataset.id;

        Swal.fire({
            title: 'ยืนยันการลบรายการนี้หรือไม่?',
            text: 'เมื่อลบแล้วจะไม่สามารถกู้คืนได้',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'activity_delete.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
</script>

<?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'ลบข้อมูลสำเร็จ',
            text: 'รายการบันทึกกิจกรรมถูกลบเรียบร้อยแล้ว',
            timer: 2000,
            showConfirmButton: false
        });
    </script>
<?php endif; ?>

<script>
    if (window.location.search.includes('delete=success')) {
        const url = new URL(window.location);
        url.searchParams.delete('delete');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
</script>


<?php
require_once '../includes/footer.php';
?>