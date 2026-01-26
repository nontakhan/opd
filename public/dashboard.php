<?php
// public/dashboard.php

require_once '../includes/auth_check.php';
require_once '../config/db_main.php';

$page_title = 'Dashboard - Nurse Activity System';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$conn = getMainDBConnection();

$today = date('Y-m-d');
$startWeek = date('Y-m-d', strtotime('-6 days'));
$startMonth = date('Y-m-d', strtotime('-29 days'));

// 1) จำนวนการบันทึกวันนี้ทั้งหมด
$total_today = 0;
$sql = "SELECT COUNT(*) AS total FROM patient_activity_header WHERE visit_date = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $total_today = (int) $row['total'];
    }
    $res->free();
    $stmt->close();
}

// 2) จำนวนแยกตามประเภท (วันนี้)
$cat_today = []; // [code] => ['name'=>..., 'total'=>...]
$sql = "
    SELECT ac.code, ac.name, COUNT(h.id) AS total
    FROM patient_activity_header h
    INNER JOIN activity_categories ac ON ac.id = h.category_id
    WHERE h.visit_date = ?
    GROUP BY ac.id, ac.code, ac.name
";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cat_today[$row['code']] = [
            'name' => $row['name'],
            'total' => (int) $row['total'],
        ];
    }
    $res->free();
    $stmt->close();
}
$opd_today = $cat_today['OPD']['total'] ?? 0;
$inj_today = $cat_today['INJ']['total'] ?? 0;

// 3) กราฟ 7 วันย้อนหลัง (นับ header ต่อวัน)
$daily_labels = [];
$daily_data = [];
// เตรียม array วันที่ให้ครบ 7 วันก่อน
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $daily_labels[$d] = date('d/m', strtotime($d));
    $daily_data[$d] = 0;
}
$sql = "
    SELECT visit_date, COUNT(*) AS total
    FROM patient_activity_header
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY visit_date
    ORDER BY visit_date
";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ss', $startWeek, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $d = $row['visit_date'];
        if (isset($daily_data[$d])) {
            $daily_data[$d] = (int) $row['total'];
        }
    }
    $res->free();
    $stmt->close();
}
$chart_daily_labels = array_values($daily_labels);
$chart_daily_data = array_values($daily_data);

// 4) กราฟสัดส่วนตามประเภท (เดือนล่าสุด)
$pie_labels = [];
$pie_data = [];
$sql = "
    SELECT ac.name AS category_name, ac.code AS category_code, COUNT(h.id) AS total
    FROM patient_activity_header h
    INNER JOIN activity_categories ac ON ac.id = h.category_id
    WHERE h.visit_date BETWEEN ? AND ?
    GROUP BY ac.id, ac.name, ac.code
";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ss', $startMonth, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $pie_labels[] = $row['category_name'];
        $pie_data[] = (int) $row['total'];
    }
    $res->free();
    $stmt->close();
}

// 5) Top 5 กิจกรรมยอดนิยม (30 วันล่าสุด)
$top_labels = [];
$top_data = [];
$sql = "
    SELECT a.name AS activity_name, COUNT(d.id) AS total_used
    FROM patient_activity_detail d
    INNER JOIN patient_activity_header h ON h.id = d.header_id
    INNER JOIN activities a ON a.id = d.activity_id
    WHERE h.visit_date BETWEEN ? AND ?
    GROUP BY a.id, a.name
    ORDER BY total_used DESC
    LIMIT 5
";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ss', $startMonth, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $top_labels[] = $row['activity_name'];
        $top_data[] = (int) $row['total_used'];
    }
    $res->free();
    $stmt->close();
}

// 6) รายการบันทึกล่าสุด 10 รายการ
$latest_rows = [];
$sql = "
    SELECT 
        h.id,
        h.hn,
        h.patient_name,
        h.visit_date,
        h.visit_time,
        ac.name AS category_name,
        ac.code AS category_code,
        GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ') AS activity_names
    FROM patient_activity_header h
    INNER JOIN activity_categories ac ON ac.id = h.category_id
    LEFT JOIN patient_activity_detail d ON d.header_id = h.id
    LEFT JOIN activities a ON a.id = d.activity_id
    GROUP BY h.id, h.hn, h.patient_name, h.visit_date, h.visit_time, ac.name, ac.code
    ORDER BY h.visit_date DESC, h.visit_time DESC, h.id DESC
    LIMIT 10
";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $latest_rows[] = $row;
    }
    $res->free();
}

$conn->close();
?>

<style>
    .dashboard-container {
        padding: 20px clamp(16px, 4vw, 64px);
        font-family: "Sarabun", sans-serif;
    }

    .dashboard-wrapper {
        max-width: 1200px;
        /* ปรับตามความเหมาะสม 1100 / 1200 / 1300 ได้ */
        margin: 0 auto;
        padding: 10px 20px;
    }


    /* หัวข้อ */
    .dashboard-header-title {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .dashboard-header-sub {
        font-size: 0.9rem;
        color: #6b7280;
        margin-bottom: 16px;
    }

    /* การ์ดสรุปด้านบน */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .stat-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        border: 1px solid #e5e7eb;
    }

    .stat-title {
        font-size: 0.9rem;
        color: #6b7280;
    }

    .stat-number {
        margin-top: 6px;
        font-size: 1.8rem;
        font-weight: 700;
        color: #1d4ed8;
    }

    .stat-footer {
        margin-top: 6px;
        font-size: 0.8rem;
        color: #64748b;
    }

    /* ป้ายประเภท */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.75rem;
        color: #fff;
        background-color: #64748b;
    }

    .badge-opd {
        background-color: #22c55e;
    }

    .badge-inj {
        background-color: #facc15;
        color: #111827;
    }

    /* layout กราฟ */
    .charts-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
        gap: 14px;
        margin-bottom: 16px;
    }

    .chart-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        border: 1px solid #e5e7eb;
    }

    .chart-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    /* ตารางล่าสุด */
    .latest-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 14px 16px 18px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        border: 1px solid #e5e7eb;
    }

    .latest-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .latest-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }

    .latest-table th,
    .latest-table td {
        border: 1px solid #e5e7eb;
        padding: 6px 8px;
    }

    .latest-table th {
        background-color: #f9fafb;
    }

    .latest-table tr:nth-child(even) {
        background-color: #fdfdfd;
    }

    @media (max-width: 900px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<div class="dashboard-wrapper">
    <div class="dashboard-container">
        <div class="dashboard-header-title">
            Dashboard - สรุปการบันทึกกิจกรรมของพยาบาล
        </div>
        <div class="dashboard-header-sub">
            วันที่วันนี้: <?= date('d/m/Y'); ?> (ข้อมูลจากระบบบันทึกกิจกรรม)
        </div>

        <!-- การ์ดสรุปด้านบน -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">จำนวนครั้งที่บันทึกกิจกรรม (วันนี้)</div>
                <div class="stat-number"><?= number_format($total_today); ?></div>
                <div class="stat-footer">รวมทุกประเภทกิจกรรม</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">กิจกรรม OPD (วันนี้)</div>
                <div class="stat-number"><?= number_format($opd_today); ?></div>
                <div class="stat-footer">ประเภท: OPD</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">กิจกรรมห้องฉีดยา/ทำแผล (วันนี้)</div>
                <div class="stat-number"><?= number_format($inj_today); ?></div>
                <div class="stat-footer">ประเภท: ห้องฉีดยา/ทำแผล</div>
            </div>
        </div>

        <!-- กราฟต่าง ๆ -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">แนวโน้มจำนวนการบันทึกย้อนหลัง 7 วัน</div>
                <canvas id="dailyChart" height="120"></canvas>
            </div>

            <div class="chart-card">
                <div class="chart-title">สัดส่วนตามประเภทกิจกรรม (30 วันล่าสุด)</div>
                <canvas id="categoryPieChart" height="120"></canvas>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Top 5 กิจกรรมที่ถูกบันทึกบ่อย (30 วันล่าสุด)</div>
                <canvas id="topActivityChart" height="120"></canvas>
            </div>
            <div></div>
        </div>

        <!-- ตารางรายการล่าสุด -->
        <div class="latest-card">
            <div class="latest-title">บันทึกกิจกรรมล่าสุด 10 รายการ</div>
            <table class="latest-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:90px;">วันที่</th>
                        <th style="width:70px;">เวลา</th>
                        <th style="width:80px;">HN</th>
                        <th>ชื่อ-สกุล</th>
                        <th style="width:130px;">ประเภท</th>
                        <th>กิจกรรมที่ทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($latest_rows)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#6b7280;">
                                ยังไม่มีข้อมูลการบันทึกกิจกรรม
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $i = 1; ?>
                        <?php foreach ($latest_rows as $r): ?>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
    // Register the plugin to all charts:
    Chart.register(ChartDataLabels);

    // ข้อมูลจาก PHP
    const dailyLabels = <?= json_encode($chart_daily_labels, JSON_UNESCAPED_UNICODE); ?>;
    const dailyData = <?= json_encode($chart_daily_data, JSON_UNESCAPED_UNICODE); ?>;
    const pieLabels = <?= json_encode($pie_labels, JSON_UNESCAPED_UNICODE); ?>;
    const pieData = <?= json_encode($pie_data, JSON_UNESCAPED_UNICODE); ?>;
    const topLabels = <?= json_encode($top_labels, JSON_UNESCAPED_UNICODE); ?>;
    const topData = <?= json_encode($top_data, JSON_UNESCAPED_UNICODE); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        // Default global options for datalabels (optional)
        // Chart.defaults.set('plugins.datalabels', { ... });

        // กราฟเส้น/แท่ง 7 วัน
        const ctxDaily = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'จำนวนครั้ง',
                    data: dailyData,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    datalabels: {
                        align: 'top',
                        anchor: 'end',
                        color: '#1d4ed8',
                        font: { weight: 'bold' },
                        formatter: function (value) {
                            return value > 0 ? value : ''; // Show only if > 0 if preferred, or just return value
                        }
                    }
                }
            }
        });

        // กราฟวงกลมสัดส่วนประเภท
        const ctxPie = document.getElementById('categoryPieChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieData,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                layout: {
                    padding: 20
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold' },
                        formatter: (value, ctx) => {
                            let sum = 0;
                            let dataArr = ctx.chart.data.datasets[0].data;
                            dataArr.map(data => {
                                sum += data;
                            });
                            let percentage = (value * 100 / sum).toFixed(1) + "%";
                            return value + ' (' + percentage + ')';
                        },
                        anchor: 'center',
                        align: 'center',
                        backgroundColor: 'rgba(0,0,0,0.5)',
                        borderRadius: 4,
                        padding: 4
                    }
                }
            }
        });

        // กราฟแท่งแนวนอน top 5 กิจกรรม
        const ctxTop = document.getElementById('topActivityChart').getContext('2d');
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: 'จำนวนครั้ง',
                    data: topData,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                layout: {
                    padding: {
                        right: 40 // Add padding to ensure label fits
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#333',
                        font: { weight: 'bold' }
                    }
                }
            }
        });
    });
</script>

<?php
require_once '../includes/footer.php';
?>