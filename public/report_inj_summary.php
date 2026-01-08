<?php
// public/report_inj_summary.php
require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

$page_title = 'รายงานสรุปกิจกรรมฉีดยา/ทำแผล - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$conn = getMainDBConnection();

// --- รับค่า filter ---
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

$rows = [];

// --- ดึงข้อมูลเฉพาะหมวด INJ + จัดกลุ่มกิจกรรม ---
// ถ้าชื่อกิจกรรมขึ้นต้นด้วย "ฉีด" ให้รวมเป็นกลุ่มเดียวชื่อ "ฉีด/วัคซีน (รวมทุกชนิด)"
$sql = "
    SELECT 
        CASE 
            WHEN a.name LIKE 'ฉีด%' THEN 'ฉีด/วัคซีน (รวมทุกชนิด)'
            ELSE a.name
        END AS grouped_name,
        COUNT(d.id) AS total_used
    FROM patient_activity_detail d
    INNER JOIN patient_activity_header h ON h.id = d.header_id
    INNER JOIN activities a ON a.id = d.activity_id
    INNER JOIN activity_categories ac ON ac.id = a.category_id
    WHERE h.visit_date BETWEEN ? AND ?
      AND ac.code = 'INJ'
    GROUP BY 
        CASE 
            WHEN a.name LIKE 'ฉีด%' THEN 'ฉีด/วัคซีน (รวมทุกชนิด)'
            ELSE a.name
        END
    ORDER BY grouped_name ASC
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ss', $start_date, $end_date);
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

<style>
    .report-container {
        max-width: 900px;
        margin: 20px auto;
        padding: 0 15px 40px;
        font-family: "Sarabun", sans-serif;
    }

    .report-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.08);
        padding: 18px 20px 22px;
    }

    .report-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .report-subtitle {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 14px;
    }

    /* แถวฟิลเตอร์ด้านบน */
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        column-gap: 40px;
        /* <<< เพิ่มระยะห่างระหว่างกลุ่ม (โดยเฉพาะช่วงวันที่) */
        row-gap: 12px;
        margin-bottom: 12px;
        align-items: flex-end;
    }

    .filter-group {
        flex: 1 1 180px;
        min-width: 180px;
    }

    /* กลุ่มวันที่ ให้กว้างคงที่ จะได้เหลือช่องว่างตรงกลางมากขึ้น */
    .filter-group.filter-date {
        flex: 0 0 260px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 3px;
        font-size: 0.85rem;
        color: #475569;
    }

    .filter-group input[type="date"] {
        width: 100%;
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        box-sizing: border-box;
    }

    /* ปุ่ม */
    .btn {
        display: inline-block;
        padding: 7px 14px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
    }

    .table-wrapper {
        margin-top: 14px;
    }

    table.display {
        width: 100%;
        font-size: 0.9rem;
    }

    /* หัวตารางสวย ๆ */
    #injSummaryTable thead th {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    /* จัดกลางคอลัมน์ตัวเลข */
    #injSummaryTable td:nth-child(1),
    #injSummaryTable td:nth-child(3) {
        text-align: center;
    }

    .hint-note {
        font-size: 0.82rem;
        color: #64748b;
        margin-top: 6px;
    }

    @media (max-width: 768px) {
        .report-card {
            padding: 14px 12px 18px;
        }

        .filter-row {
            flex-direction: column;
            align-items: stretch;
            column-gap: 0;
        }

        .filter-group.filter-date {
            flex: 1 1 auto;
        }
    }
</style>

<div class="report-container">
    <div class="report-card">
        <div class="report-title">รายงานสรุปกิจกรรมฉีดยา/ทำแผล</div>
        <div class="report-subtitle">
            สรุปจำนวนการบันทึกกิจกรรมเฉพาะหมวดห้องฉีดยา/ทำแผล โดยกิจกรรมที่ขึ้นต้นด้วยคำว่า
            “ฉีด...” จะถูกรวมเป็นหนึ่งกลุ่ม “ฉีด/วัคซีน (รวมทุกชนิด)”
        </div>

        <!-- ฟอร์มเลือกช่วงวันที่ -->
        <form method="get" action="report_inj_summary.php">
            <div class="filter-row">
                <div class="filter-group filter-date">
                    <label for="start_date">วันที่เริ่ม</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group filter-date">
                    <label for="end_date">วันที่สิ้นสุด</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-group" style="flex:0 0 150px;">
                    <button type="submit" class="btn btn-primary">แสดงรายงาน</button>
                </div>
            </div>
        </form>

        <div class="table-wrapper">
            <table id="injSummaryTable" class="display">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ชื่อกิจกรรม (จัดกลุ่ม)</th>
                        <th>จำนวนครั้งที่บันทึก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= $i++; ?></td>
                                <td><?= htmlspecialchars($row['grouped_name']); ?></td>
                                <td>
                                    <?php if ($row['total_used'] > 0): ?>
                                        <a href="javascript:void(0)" class="count-link fw-bold"
                                            onclick="showActivityDetails('<?= htmlspecialchars($row['grouped_name'], ENT_QUOTES); ?>')">
                                            <?= number_format($row['total_used']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?= number_format($row['total_used']); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- ถ้าไม่มีข้อมูล ปล่อยให้ DataTables แสดงข้อความ emptyTable เอง -->
                </tbody>
            </table>
        </div>

        <div class="hint-note">
            หมายเหตุ: รายการ “ฉีด/วัคซีน (รวมทุกชนิด)” รวมทุกกิจกรรมที่ชื่อขึ้นต้นด้วยคำว่า “ฉีด…”
        </div>
    </div>
</div>

<script>
    // Global function for inline onclick
    function showActivityDetails(groupedName) {

        // Check jQuery
        if (typeof jQuery === 'undefined') {
            alert('Error: jQuery is not loaded.');
            return;
        }

        // Check SweetAlert2
        if (typeof Swal === 'undefined') {
            alert('Error: SweetAlert2 is not loaded.');
            return;
        }

        const startDate = $('#start_date').val();
        const endDate = $('#end_date').val();

        Swal.fire({
            title: 'กำลังโหลดข้อมูล...',
            text: 'กรุณารอสักครู่ (Fetching details...)',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'get_activity_details.php',
            type: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate,
                grouped_name: groupedName
            },
            dataType: 'json',
            success: function (response) {
                if (response.length === 0) {
                    Swal.fire('ไม่พบข้อมูล', 'ไม่มีรายการกิจกรรมในช่วงเวลานี้', 'info');
                    return;
                }

                // Build table HTML
                let html = `
                    <div style="text-align: left; font-size: 0.9rem; margin-bottom: 10px;">
                        <strong>กิจกรรม:</strong> ${groupedName}<br>
                        <strong>ช่วงวันที่:</strong> ${formatDateTh(startDate)} - ${formatDateTh(endDate)}<br>
                        <strong>จำนวน:</strong> ${response.length} รายการ
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-bordered table-sm" style="width:100%; font-size: 0.85rem; text-align: left;">
                            <thead style="position: sticky; top: 0; background: #f8fafc;">
                                <tr>
                                    <th style="padding: 5px;">วันที่/เวลา</th>
                                    <th style="padding: 5px;">HN</th>
                                    <th style="padding: 5px;">ชื่อ-สกุล</th>
                                    <th style="padding: 5px;">รายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                response.forEach(item => {
                    const dt = new Date(item.visit_date + ' ' + item.visit_time);
                    const dtStr = dt.toLocaleDateString('th-TH') + ' ' + dt.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });

                    html += `
                        <tr>
                            <td style="padding: 5px; border-bottom: 1px solid #eee;">${dtStr}</td>
                            <td style="padding: 5px; border-bottom: 1px solid #eee;">${item.hn}</td>
                            <td style="padding: 5px; border-bottom: 1px solid #eee;">${item.patient_name}</td>
                            <td style="padding: 5px; border-bottom: 1px solid #eee;">${item.activity_name}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                Swal.fire({
                    title: 'รายชื่อผู้รับบริการ',
                    html: html,
                    width: '800px',
                    showConfirmButton: true,
                    confirmButtonText: 'ปิด'
                });
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
            }
        });
    }

    function formatDateTh(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    $(document).ready(function () {
        $('#injSummaryTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            info: true,
            order: [[2, 'desc']],
            language: {
                decimal: "",
                emptyTable: "ไม่พบข้อมูลในช่วงวันที่ที่เลือก",
                info: "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                infoEmpty: "แสดง 0 ถึง 0 จากทั้งหมด 0 รายการ",
                infoFiltered: "(กรองจากทั้งหมด _MAX_ รายการ)",
                lengthMenu: "แสดง _MENU_ รายการ",
                loadingRecords: "กำลังโหลด...",
                processing: "กำลังประมวลผล...",
                search: "ค้นหา:",
                zeroRecords: "ไม่พบข้อมูลที่ค้นหา",
                paginate: {
                    first: "แรกสุด",
                    last: "สุดท้าย",
                    next: "ถัดไป",
                    previous: "ก่อนหน้า"
                }
            }
        });
    });
</script>