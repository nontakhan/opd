<?php
// public/report.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

$page_title = 'รายงานกิจกรรมพยาบาล - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$conn = getMainDBConnection();

// อ่านค่าจาก GET (default: เดือนปัจจุบัน, รายงานแบบ detailed, ทุกประเภทกิจกรรม)
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$category_code = isset($_GET['category_code']) ? $_GET['category_code'] : 'ALL'; // ALL, OPD, INJ
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'detail';  // detail, summary
$nurse_id = isset($_GET['nurse_id']) ? $_GET['nurse_id'] : 'ALL';

$detail_rows = [];
$summary_rows = [];



// ---------- รายงานแบบละเอียด (ต่อ visit) ----------
if ($report_type === 'detail') {
    $sql = "
        SELECT 
            h.id,
            h.hn,
            h.patient_name,
            h.visit_date,
            h.visit_time,
            ac.name AS category_name,
            ac.code AS category_code,
            h.note,
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

    if ($category_code !== 'ALL') {
        $sql .= " AND ac.code = ? ";
        $params[] = $category_code;
        $types .= 's';
    }

    $sql .= "
        GROUP BY h.id, h.hn, h.patient_name, h.visit_date, h.visit_time, ac.name, ac.code, h.note
        ORDER BY h.visit_date ASC, h.visit_time ASC, h.id ASC
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $detail_rows[] = $row;
        }
        $res->free();
        $stmt->close();
    }
}
// ---------- รายงานแบบสรุปนับจำนวนตามกิจกรรม ----------
else {
    $sql = "
        SELECT 
            ac.name AS category_name,
            ac.code AS category_code,
            a.id   AS activity_id,
            a.name AS activity_name,
            COUNT(d.id) AS total_used
        FROM patient_activity_detail d
        INNER JOIN patient_activity_header h ON h.id = d.header_id
        INNER JOIN activities a ON a.id = d.activity_id
        INNER JOIN activity_categories ac ON ac.id = a.category_id
        WHERE h.visit_date BETWEEN ? AND ?
    ";

    $params = [$start_date, $end_date];
    $types = 'ss';

    if ($category_code !== 'ALL') {
        $sql .= " AND ac.code = ? ";
        $params[] = $category_code;
        $types .= 's';
    }

    $sql .= "
        GROUP BY ac.id, ac.name, ac.code, a.id, a.name
        ORDER BY ac.id ASC, a.name ASC
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $summary_rows[] = $row;
        }
        $res->free();
        $stmt->close();
    }
}

$conn->close();
?>

<!-- DataTables CSS & JS (ถ้ายังไม่มีใน header) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<style>
    .report-container {
        max-width: 1200px;
        margin: 20px auto 40px;
        padding: 0 16px;
        font-family: "Sarabun", sans-serif;
    }

    .report-header {
        margin-bottom: 10px;
    }

    .report-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .report-subtitle {
        font-size: 0.9rem;
        color: #6b7280;
    }

    /* การ์ดฟิลเตอร์ */
    .filter-card {
        margin-top: 12px;
        padding: 14px 16px;
        border-radius: 10px;
        background-color: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        border: 1px solid #e2e8f0;
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 16px;
        margin-bottom: 4px;
    }

    .filter-group {
        flex: 1 1 190px;
        min-width: 180px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 4px;
        font-size: 0.9rem;
    }

    .filter-group input[type="date"],
    .filter-group select {
        width: 100%;
        padding: 6px 8px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        box-sizing: border-box;
    }

    .filter-actions {
        flex: 0 0 150px;
        display: flex;
        align-items: flex-end;
    }

    .btn {
        display: inline-block;
        padding: 7px 14px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #fff;
    }

    .btn-primary:hover {
        filter: brightness(1.04);
    }

    /* การ์ดตาราง */
    .report-card {
        margin-top: 16px;
        padding: 14px 16px 18px;
        border-radius: 10px;
        background-color: #ffffff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07);
        border: 1px solid #e2e8f0;
    }

    .report-card-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    /* ตาราง DataTables */
    table.dataTable thead th {
        background-color: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        padding: 8px 10px;
        font-size: 0.86rem;
    }

    table.dataTable tbody td {
        padding: 6px 10px;
        font-size: 0.84rem;
    }

    table.dataTable tbody tr:nth-child(even) {
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
        color: #1f2937;
    }

    /* info "ไม่พบข้อมูล" */
    .report-empty {
        margin-top: 8px;
        font-size: 0.86rem;
        color: #6b7280;
    }

    @media (max-width: 768px) {
        .filter-row {
            flex-direction: column;
        }

        .filter-actions {
            flex: 1 1 auto;
        }
    }

    .form-select {
        width: 100%;
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        background-color: #f8fafc;
    }

    .form-select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
        background: #fff;
    }

    /* hover แถวรายงาน */
    .summary-row:hover {
        background-color: #8FAFCF;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    /* แถวรวมทั้งหมด */
    .summary-total-row {
        background-color: #e0f2fe;
        font-weight: 700;
        border-top: 2px solid #0284c7;
    }

    .summary-total-row td {
        padding-top: 10px;
        padding-bottom: 10px;
    }

    .summary-total-row {
        background-color: #bae6fd;
        color: #0c4a6e;
    }
</style>

<div class="report-container">
    <div class="report-header">
        <div class="report-title">รายงานกิจกรรมพยาบาล</div>
        <div class="report-subtitle">
            เลือกช่วงวันที่ ประเภทกิจกรรม และประเภทรายงาน จากนั้นกด “แสดงรายงาน”
        </div>
    </div>

    <!-- ฟอร์มกรองข้อมูล -->
    <div class="filter-card">
        <form method="get" action="report.php">
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
                    <label for="category_code">ประเภทกิจกรรม</label>
                    <select id="category_code" name="category_code">
                        <option value="ALL" <?= $category_code === 'ALL' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="OPD" <?= $category_code === 'OPD' ? 'selected' : ''; ?>>เฉพาะกิจกรรม OPD</option>
                        <option value="INJ" <?= $category_code === 'INJ' ? 'selected' : ''; ?>>เฉพาะห้องฉีดยา/ทำแผล
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="report_type">ประเภทรายงาน</label>
                    <select id="report_type" name="report_type">
                        <option value="detail" <?= $report_type === 'detail' ? 'selected' : ''; ?>>รายงานแบบละเอียด (ต่อ
                            Visit)</option>
                        <option value="summary" <?= $report_type === 'summary' ? 'selected' : ''; ?>>รายงานสรุปตามกิจกรรม
                        </option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">แสดงรายงาน</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($report_type === 'detail'): ?>
        <div class="report-card">
            <div class="report-card-title">
                รายงานแบบละเอียด: แสดงต่อ Visit / HN
            </div>

            <table id="reportDetailTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>HN</th>
                        <th>ชื่อ-สกุล</th>
                        <th>ประเภทกิจกรรม</th>
                        <th>กิจกรรมที่ทำ</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detail_rows)): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($detail_rows as $row): ?>
                            <?php
                            $badgeClass = '';
                            if ($row['category_code'] === 'OPD') {
                                $badgeClass = 'badge-opd';
                            } elseif ($row['category_code'] === 'INJ') {
                                $badgeClass = 'badge-inj';
                            }
                            ?>
                            <tr>
                                <td><?= $i++; ?></td>
                                <td><?= $row['visit_date'] ? date('d/m/Y', strtotime($row['visit_date'])) : ''; ?></td>
                                <td><?= $row['visit_time'] ? substr($row['visit_time'], 0, 5) : ''; ?></td>
                                <td><?= htmlspecialchars($row['hn']); ?></td>
                                <td><?= htmlspecialchars($row['patient_name']); ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass; ?>">
                                        <?= htmlspecialchars($row['category_name']); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['activity_names']); ?></td>
                                <td><?= nl2br(htmlspecialchars($row['note'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (empty($detail_rows)): ?>
                <div class="report-empty">
                    ไม่พบข้อมูลในช่วงวันที่และเงื่อนไขที่เลือก
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="report-card">
            <div class="report-card-title">
                รายงานสรุป: นับจำนวนการใช้แต่ละกิจกรรม
            </div>

            <table id="reportSummaryTable" class="display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ประเภทกิจกรรม</th>
                        <th>ชื่อกิจกรรม</th>
                        <th>จำนวนครั้งที่บันทึก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($summary_rows)): ?>
                        <?php
                        $i = 1;
                        // Calculate total once
                        $total_all = 0;
                        foreach ($summary_rows as $r) {
                            $total_all += (int) $r['total_used'];
                        }
                        ?>
                        <?php foreach ($summary_rows as $row): ?>
                            <?php
                            $badgeClass = '';
                            if ($row['category_code'] === 'OPD') {
                                $badgeClass = 'badge-opd';
                            } elseif ($row['category_code'] === 'INJ') {
                                $badgeClass = 'badge-inj';
                            }
                            ?>
                            <tr class="summary-row">
                                <td><?= $i++; ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass; ?>">
                                        <?= htmlspecialchars($row['category_name']); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['activity_name']); ?></td>
                                <td style="text-align:center;"><?= number_format($row['total_used']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($summary_rows)): ?>
                    <tfoot>
                        <tr class="summary-total-row">
                            <th colspan="3" style="text-align:right; padding-right:20px;">
                                รวมกิจกรรมทั้งหมด
                            </th>
                            <th style="text-align:center;">
                                <?= number_format($total_all) ?>
                            </th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>

            <?php if (empty($summary_rows)): ?>
                <div class="report-empty">
                    ไม่พบข้อมูลในช่วงวันที่และเงื่อนไขที่เลือก
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function () {
        // DataTables ภาษาไทย
        var dtLang = {
            "sProcessing": "กำลังประมวลผล...",
            "sLengthMenu": "แสดง _MENU_ แถว",
            "sZeroRecords": "ไม่พบข้อมูล",
            "sInfo": "แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว",
            "sInfoEmpty": "แสดง 0 ถึง 0 จาก 0 แถว",
            "sInfoFiltered": "(กรองข้อมูล _MAX_ ทุกแถว)",
            "sSearch": "ค้นหา:",
            "oPaginate": {
                "sFirst": "หน้าแรก",
                "sPrevious": "ก่อนหน้า",
                "sNext": "ถัดไป",
                "sLast": "หน้าสุดท้าย"
            }
        };

        <?php if ($report_type === 'detail'): ?>
            $('#reportDetailTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[1, 'asc'], [2, 'asc']],
                language: dtLang
            });
        <?php else: ?>
            $('#reportSummaryTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[0, 'asc']],
                language: dtLang
            });
        <?php endif; ?>
    });
</script>

<?php
require_once '../includes/footer.php';
?>