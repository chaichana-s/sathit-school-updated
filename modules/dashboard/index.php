<?php 
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$displayName = $_SESSION['username'] ?? 'User';
$displayRole = $_SESSION['user_role'] ?? 'Guest';

// Fetch today's attendance summary for the stat badge
$today = date('Y-m-d');
$todaySummary = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
    FROM attendance WHERE attendance_date = ?
");
$todaySummary->execute([$today]);
$todayAtt = $todaySummary->fetch(PDO::FETCH_ASSOC);

// Fetch recent real activities (last attendance changes + grade entries)
$recentActivities = [];

// Recent attendance records
$attRecent = $pdo->query("
    SELECT a.attendance_date, a.status, a.recorded_by,
           s.first_name as student_fname, s.last_name as student_lname,
           c.class_name, sub.subject_name,
           u.username as recorder_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN schedules sch ON a.schedule_id = sch.id
    JOIN classes c ON sch.class_id = c.id
    JOIN subjects sub ON sch.subject_id = sub.id
    LEFT JOIN users u ON a.recorded_by = u.id
    ORDER BY a.created_at DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($attRecent as $act) {
    $statusTh = ['Present'=>'มาเรียน','Absent'=>'ขาดเรียน','Late'=>'สาย','Leave'=>'ลา'];
    $statusClass = ['Present'=>'success','Absent'=>'danger','Late'=>'warning','Leave'=>'info'];
    $statusIcon = ['Present'=>'check-circle','Absent'=>'times-circle','Late'=>'clock','Leave'=>'envelope-open-text'];
    $st = $act['status'];
    $recentActivities[] = [
        'icon' => $statusIcon[$st] ?? 'info-circle',
        'color' => $statusClass[$st] ?? 'primary',
        'text' => "บันทึกเวลาเรียน {$act['student_fname']} {$act['student_lname']} ({$act['class_name']}) วิชา{$act['subject_name']} - {$statusTh[$st]}",
        'time' => date('d/m/Y', strtotime($act['attendance_date'])),
        'recorder' => $act['recorder_name'] ?? 'ระบบ'
    ];
}

// Fallback if no activities
if (empty($recentActivities)) {
    $recentActivities[] = [
        'icon' => 'info-circle',
        'color' => 'secondary',
        'text' => 'ยังไม่มีกิจกรรมที่บันทึกในระบบ',
        'time' => '-',
        'recorder' => '-'
    ];
}

include APP_ROOT . '/includes/header.php'; 
?>

<!-- Page Header / Breadcrumb -->
<div class="page-title-box d-flex align-items-center justify-content-between mb-4">
    <h3 class="mb-0 text-vintage-heading"><i class="fas fa-tachometer-alt me-2 text-gold"></i> แดชบอร์ดผู้บริหาร (Executive Dashboard)</h3>
    <div class="page-title-right">
        <ol class="breadcrumb m-0">
            <li class="breadcrumb-item"><a href="javascript: void(0);">สาธิตวิทยา</a></li>
            <li class="breadcrumb-item active">แดชบอร์ด</li>
        </ol>
    </div>
</div>

<!-- Welcome Notification Alert -->
<div class="alert custom-vintage-alert alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
    <i class="fas fa-bell fa-2x me-3 text-gold"></i>
    <div>
        <h5 class="alert-heading mb-1 text-dark">ยินดีต้อนรับกลับเข้าสู่ระบบ, <?= htmlspecialchars($displayName) ?>!</h5>
        <p class="mb-0 text-muted small" id="dashboard-today-summary-text">ระบบฐานข้อมูลสถิติของโรงเรียนสาธิตวิทยา — <?= date('d/m/Y H:i') ?> น.
        <?php if ($todayAtt && $todayAtt['total'] > 0): ?>
            | วันนี้บันทึกเวลาเรียนแล้ว <strong id="dash-today-total"><?= number_format($todayAtt['total']) ?></strong> รายการ
            (มา <span id="dash-today-present"><?= number_format($todayAtt['present']) ?></span> | ขาด <span id="dash-today-absent"><?= number_format($todayAtt['absent']) ?></span> | สาย <span id="dash-today-late"><?= number_format($todayAtt['late']) ?></span>)
        <?php else: ?>
            | <span id="dash-today-empty">ยังไม่มีการบันทึกเวลาเรียนสำหรับวันนี้</span>
        <?php endif; ?>
        </p>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Stat 1 -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center justify-content-between position-relative overflow-hidden">
                <div class="stat-content z-index-1">
                    <p class="text-uppercase fw-bold mb-2 small text-muted">บุคลากรครู (Teachers)</p>
                    <h2 class="mb-0 text-vintage-primary counter" id="dashboard-teachers">-</h2>
                </div>
                <div class="stat-icon z-index-1">
                    <div class="icon-wrapper bg-vintage-light text-vintage-primary">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                </div>
                <i class="fas fa-chalkboard-teacher dashboard-card-bg-icon"></i>
            </div>
        </div>
    </div>
    <!-- Stat 2 -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center justify-content-between position-relative overflow-hidden">
                <div class="stat-content z-index-1">
                    <p class="text-uppercase fw-bold mb-2 small text-muted">นักเรียน (Students)</p>
                    <h2 class="mb-0 text-vintage-primary counter" id="dashboard-students">-</h2>
                </div>
                <div class="stat-icon z-index-1">
                    <div class="icon-wrapper bg-gold-light text-gold">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                </div>
                <i class="fas fa-user-graduate dashboard-card-bg-icon"></i>
            </div>
        </div>
    </div>
    <!-- Stat 3 -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center justify-content-between position-relative overflow-hidden">
                <div class="stat-content z-index-1">
                    <p class="text-uppercase fw-bold mb-2 small text-muted">รายวิชาเปิดสอน (Subjects)</p>
                    <h2 class="mb-0 text-vintage-primary counter" id="dashboard-subjects">-</h2>
                </div>
                <div class="stat-icon z-index-1">
                    <div class="icon-wrapper bg-vintage-light text-vintage-primary">
                        <i class="fas fa-book-open fa-2x"></i>
                    </div>
                </div>
                <i class="fas fa-book-open dashboard-card-bg-icon"></i>
            </div>
        </div>
    </div>
    <!-- Stat 4 -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center justify-content-between position-relative overflow-hidden">
                <div class="stat-content z-index-1">
                    <p class="text-uppercase fw-bold mb-2 small text-muted">อัตราเข้าเรียน (Attendance)</p>
                    <h2 class="mb-0 text-vintage-primary counter"><span id="dashboard-attendance">-</span>%</h2>
                </div>
                <div class="stat-icon z-index-1">
                    <div class="icon-wrapper bg-gold-light text-gold">
                        <i class="fas fa-clipboard-check fa-2x"></i>
                    </div>
                </div>
                <i class="fas fa-clipboard-check dashboard-card-bg-icon"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4">
    <!-- Attendance Chart -->
    <div class="col-lg-8">
        <div class="card vintage-card shadow-sm border-0 mb-4 h-100">
            <div class="card-header bg-transparent border-bottom px-4 pt-4 pb-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-vintage-heading"><i class="fas fa-chart-area me-2 text-gold"></i> สถิติการมาเรียน (วัน/สัปดาห์)</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-vintage dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                        สัปดาห์ปัจจุบัน
                    </button>
                    <ul class="dropdown-menu shadow-sm" aria-labelledby="dropdownMenuButton1">
                        <li><a class="dropdown-item" href="#">1 เดือนย้อนหลัง</a></li>
                        <li><a class="dropdown-item" href="#">ภาคการศึกษานี้</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body px-4">
                <div class="chart-container" style="position: relative; height:300px; width:100%">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Grade Distribution -->
    <div class="col-lg-4">
        <div class="card vintage-card shadow-sm border-0 mb-4 h-100">
            <div class="card-header bg-transparent border-bottom px-4 pt-4 pb-3">
                <h5 class="mb-0 text-vintage-heading"><i class="fas fa-chart-pie me-2 text-gold"></i> ภาพรวมผลการเรียนเฉลี่ย</h5>
            </div>
            <div class="card-body px-4 d-flex flex-column justify-content-center">
                <div class="chart-container align-self-center mt-2" style="position: relative; height:240px; width:100%">
                    <canvas id="gradeChart"></canvas>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-muted small mb-0">สัดส่วนเกรดจากฐานข้อมูลจริง (อัปเดตอัตโนมัติ)</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & recent activity -->
<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="card vintage-card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom px-4 pt-4 pb-3">
                 <h5 class="mb-0 text-vintage-heading"><i class="fas fa-bolt me-2 text-gold"></i> เมนูลัด (Quick Actions)</h5>
            </div>
            <div class="card-body p-4">
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= get_base_url() ?>/modules/master_data/students.php" class="btn btn-vintage btn-lg flex-grow-1"><i class="fas fa-user-plus me-2"></i> เพิ่มนักเรียนใหม่</a>
                    <a href="<?= get_base_url() ?>/modules/schedule/schedule.php" class="btn btn-outline-vintage btn-lg flex-grow-1"><i class="fas fa-calendar-plus me-2"></i> จัดตารางเรียน</a>
                    <button class="btn btn-outline-vintage btn-lg flex-grow-1" data-bs-toggle="modal" data-bs-target="#exportReportModal"><i class="fas fa-file-export me-2"></i> ส่งออกรายงาน</button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
         <div class="card vintage-card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom px-4 pt-4 pb-3">
                 <h5 class="mb-0 text-vintage-heading"><i class="fas fa-history me-2 text-gold"></i> กิจกรรมล่าสุด (Recent Activities)</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush mb-0" id="dashboard-recent-activities">
                    <?php foreach ($recentActivities as $activity): ?>
                    <li class="list-group-item px-4 py-3 d-flex align-items-center">
                        <div class="activity-icon bg-<?= $activity['color'] ?> bg-opacity-10 text-<?= $activity['color'] ?> rounded-circle p-2 me-3">
                            <i class="fas fa-<?= $activity['icon'] ?>"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-medium"><?= htmlspecialchars($activity['text']) ?></p>
                            <small class="text-muted"><i class="fas fa-clock me-1"></i><?= $activity['time'] ?> — โดย <?= htmlspecialchars($activity['recorder']) ?></small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Export Report Modal -->
<div class="modal fade" id="exportReportModal" tabindex="-1" aria-labelledby="exportReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom" style="background: linear-gradient(135deg, var(--vintage-primary) 0%, #6366f1 100%);">
                <h5 class="modal-title text-white" id="exportReportModalLabel"><i class="fas fa-file-export me-2"></i> ส่งออกรายงาน (Export Report)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="exportReportForm">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-muted">ประเภทรายงาน (Report Type)</label>
                        <select class="form-select" id="reportType" required>
                            <option value="">-- เลือกประเภทรายงาน --</option>
                            <option value="students">รายชื่อนักเรียน (Student List)</option>
                            <option value="teachers">รายชื่อบุคลากร (Teacher List)</option>
                            <option value="attendance">สรุปเวลาเรียน (Attendance Summary)</option>
                            <option value="grades">สรุปผลการเรียน (Grade Summary)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium text-muted">รูปแบบไฟล์ (Format)</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatCSV" value="csv" checked>
                                <label class="form-check-label" for="formatCSV"><i class="fas fa-file-csv text-success me-1"></i> CSV</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatJSON" value="json">
                                <label class="form-check-label" for="formatJSON"><i class="fas fa-file-code text-primary me-1"></i> JSON</label>
                            </div>
                        </div>
                    </div>
                    <div id="exportProgress" class="d-none">
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-vintage-primary progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                        </div>
                        <p class="text-muted small text-center mb-0">กำลังเตรียมข้อมูล...</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-vintage" id="btnExportReport"><i class="fas fa-download me-2"></i> ดาวน์โหลด</button>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>

<script>
// Export Report Logic - uses server-side API for reliable file downloads
document.getElementById('btnExportReport').addEventListener('click', function() {
    const reportType = document.getElementById('reportType').value;
    const format = document.querySelector('input[name="exportFormat"]:checked').value;
    
    if (!reportType) {
        document.getElementById('reportType').classList.add('is-invalid');
        return;
    }
    document.getElementById('reportType').classList.remove('is-invalid');

    const progress = document.getElementById('exportProgress');
    const btn = this;
    
    // Show progress animation
    progress.classList.remove('d-none');
    btn.disabled = true;

    // Brief delay for UX, then trigger server-side download
    setTimeout(function() {
        progress.classList.add('d-none');
        btn.disabled = false;

        // Redirect to server-side export API (sets Content-Disposition header for proper filename)
        window.location.href = APP_BASE + '/api/export_api.php?type=' + encodeURIComponent(reportType) + '&format=' + encodeURIComponent(format);

        // Close modal after a short delay
        setTimeout(function() {
            bootstrap.Modal.getInstance(document.getElementById('exportReportModal')).hide();
        }, 500);
    }, 600);
});
</script>

