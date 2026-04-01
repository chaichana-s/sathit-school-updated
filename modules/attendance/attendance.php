<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'];
$ref_id = $_SESSION['reference_id'];

if ($role === 'Student') {
    die("Access Denied: Students cannot access the attendance recording system.");
}

$today = date('Y-m-d');
$filter_date = $_GET['date'] ?? $today;
$filter_schedule_id = $_GET['schedule_id'] ?? '';
$filter_class_id = $_GET['class_id'] ?? '';

// ── Admin Summary (for the selected date, across ALL schedules) ──
$admin_summary = null;
if ($role === 'Admin') {
    $stmtSummary = $pdo->prepare("
        SELECT 
            COUNT(*) as total_recorded,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as on_leave
        FROM attendance
        WHERE attendance_date = ?
    ");
    $stmtSummary->execute([$filter_date]);
    $admin_summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);
}

// ── Handle AJAX toggle ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    $student_id  = $_POST['student_id'] ?? 0;
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $date        = $_POST['date'] ?? $today;
    $status      = $_POST['status'] ?? 'Present';

    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM attendance WHERE schedule_id = ? AND student_id = ? AND attendance_date = ?");
        $stmtCheck->execute([$schedule_id, $student_id, $date]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE attendance SET status = ?, recorded_by = ? WHERE id = ?")->execute([$status, $user_id, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO attendance (schedule_id, student_id, attendance_date, status, recorded_by) VALUES (?, ?, ?, ?, ?)")->execute([$schedule_id, $student_id, $date, $status, $user_id]);
        }

        // Return updated summary for the whole date
        $stmtSum = $pdo->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present,
                   SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as absent,
                   SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) as late,
                   SUM(CASE WHEN status='Leave' THEN 1 ELSE 0 END) as on_leave
            FROM attendance WHERE attendance_date = ?
        ");
        $stmtSum->execute([$date]);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'summary' => $summary]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Fetch available classes for the class filter ──
$allClasses = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_ASSOC);

// ── Day of week for schedule lookup ──
$dayOfWeek = date('l', strtotime($filter_date));
$days_th = ['Monday'=>'วันจันทร์','Tuesday'=>'วันอังคาร','Wednesday'=>'วันพุธ','Thursday'=>'วันพฤหัสบดี','Friday'=>'วันศุกร์','Saturday'=>'วันเสาร์','Sunday'=>'วันอาทิตย์'];
$dayTh = $days_th[$dayOfWeek] ?? $dayOfWeek;

// ── Build schedule query ──
$sched_query = "SELECT s.*, c.class_name, sub.subject_name, sub.subject_code, cr.room_code,
                       CONCAT(t.prefix, t.first_name, ' ', t.last_name) as teacher_name
                FROM schedules s 
                JOIN classes c ON s.class_id = c.id 
                JOIN subjects sub ON s.subject_id = sub.id 
                JOIN classrooms cr ON s.classroom_id = cr.id
                JOIN teachers t ON s.teacher_id = t.id
                WHERE s.day_of_week = ?";
$sched_params = [$dayOfWeek];

if ($role === 'Teacher') {
    $sched_query .= " AND s.teacher_id = ?";
    $sched_params[] = $ref_id;
} elseif (!empty($filter_class_id)) {
    $sched_query .= " AND s.class_id = ?";
    $sched_params[] = $filter_class_id;
}
$sched_query .= " ORDER BY c.class_name, s.start_time";

$stmtSched = $pdo->prepare($sched_query);
$stmtSched->execute($sched_params);
$active_schedules = $stmtSched->fetchAll(PDO::FETCH_ASSOC);

if (empty($filter_schedule_id) && !empty($active_schedules)) {
    $filter_schedule_id = $active_schedules[0]['id'];
}

// ── Fetch students for the selected schedule ──
$students = [];
$current_schedule_info = null;

if ($filter_schedule_id) {
    $stmtClassId = $pdo->prepare("
        SELECT s.class_id, sub.subject_name, s.start_time, s.end_time, c.class_name, sub.subject_code, cr.room_code,
               CONCAT(t.prefix, t.first_name, ' ', t.last_name) as teacher_name
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN classes c ON s.class_id = c.id
        JOIN classrooms cr ON s.classroom_id = cr.id
        JOIN teachers t ON s.teacher_id = t.id
        WHERE s.id = ?
    ");
    $stmtClassId->execute([$filter_schedule_id]);
    $current_schedule_info = $stmtClassId->fetch(PDO::FETCH_ASSOC);

    if ($current_schedule_info) {
        $class_id = $current_schedule_info['class_id'];
        $std_query = "SELECT st.id, st.student_code, st.prefix, st.first_name, st.last_name, 
                      COALESCE(a.status, 'Present') as current_status
                      FROM students st
                      LEFT JOIN attendance a ON st.id = a.student_id AND a.schedule_id = ? AND a.attendance_date = ?
                      WHERE st.class_id = ?
                      ORDER BY st.student_code";
        $stmtStd = $pdo->prepare($std_query);
        $stmtStd->execute([$filter_schedule_id, $filter_date, $class_id]);
        $students = $stmtStd->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Count per schedule for badges
$schedCounts = [];
if (!empty($active_schedules)) {
    $schedIds = array_column($active_schedules, 'id');
    $placeholders = implode(',', array_fill(0, count($schedIds), '?'));
    $stmtCounts = $pdo->prepare("
        SELECT schedule_id, COUNT(*) as cnt FROM attendance
        WHERE schedule_id IN ($placeholders) AND attendance_date = ?
        GROUP BY schedule_id
    ");
    $stmtCounts->execute(array_merge($schedIds, [$filter_date]));
    foreach ($stmtCounts->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $schedCounts[$row['schedule_id']] = $row['cnt'];
    }
}
?>
<?php include APP_ROOT . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="text-vintage-heading mb-0"><i class="fas fa-clipboard-check me-2 text-gold"></i> ระบบบันทึกเวลาเรียน (Attendance)</h3>
    <span class="badge bg-vintage-primary text-white px-3 py-2 fs-6"><i class="fas fa-calendar-alt me-1"></i> <?= $dayTh ?> — <?= date('d/m/Y', strtotime($filter_date)) ?></span>
</div>

<?php if ($role === 'Admin' && $admin_summary): ?>
<!-- ══════ Admin Summary Statistics ══════ -->
<div class="row g-3 mb-4">
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid var(--vintage-primary) !important;">
            <div class="card-body text-center py-3">
                <p class="text-muted small fw-bold mb-1 text-uppercase">บันทึกทั้งหมด</p>
                <h2 class="mb-0 fw-bold" id="att-stat-total" style="color: var(--vintage-primary);"><?= number_format($admin_summary['total_recorded']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #198754 !important;">
            <div class="card-body text-center py-3">
                <p class="text-success small fw-bold mb-1"><i class="fas fa-check-circle me-1"></i>มาเรียน</p>
                <h2 class="mb-0 fw-bold text-success" id="att-stat-present"><?= number_format($admin_summary['present'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #dc3545 !important;">
            <div class="card-body text-center py-3">
                <p class="text-danger small fw-bold mb-1"><i class="fas fa-times-circle me-1"></i>ขาดเรียน</p>
                <h2 class="mb-0 fw-bold text-danger" id="att-stat-absent"><?= number_format($admin_summary['absent'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #ffc107 !important;">
            <div class="card-body text-center py-3">
                <p class="text-warning small fw-bold mb-1"><i class="fas fa-clock me-1"></i>มาสาย</p>
                <h2 class="mb-0 fw-bold text-warning" id="att-stat-late"><?= number_format($admin_summary['late'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0dcaf0 !important;">
            <div class="card-body text-center py-3">
                <p class="text-info small fw-bold mb-1"><i class="fas fa-envelope-open-text me-1"></i>ลา</p>
                <h2 class="mb-0 fw-bold text-info" id="att-stat-leave"><?= number_format($admin_summary['on_leave'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- ══════ LEFT: Filters & Schedule List ══════ -->
    <div class="col-lg-4 mb-4">
        <div class="card vintage-card border-0 h-100">
            <div class="card-header bg-vintage-dark text-white p-3 border-0">
                <h5 class="mb-0"><i class="fas fa-calendar-day me-2 text-gold"></i> เลือกคาบเรียน</h5>
            </div>
            <div class="card-body p-4 bg-white">
                <form method="GET" action="attendance.php" id="filterForm">
                    <!-- Date filter -->
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">วันที่ (Date)</label>
                        <input type="date" class="form-control focus-vintage" name="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
                    </div>

                    <!-- Class filter (Admin only) -->
                    <?php if ($role === 'Admin'): ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">กรองตามชั้น (Class)</label>
                        <select class="form-select focus-vintage" name="class_id" onchange="this.form.submit()">
                            <option value="">ทุกชั้นเรียน</option>
                            <?php foreach ($allClasses as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= ($filter_class_id == $cls['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cls['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Schedule list -->
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold">คาบเรียนสำหรับ <?= $dayTh ?></label>

                        <?php if (empty($active_schedules)): ?>
                            <div class="alert alert-light text-center border text-muted py-4">
                                <i class="fas fa-calendar-times fa-2x mb-2 d-block text-black-50"></i>
                                ไม่มีคาบสอนในวันนี้
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush border rounded overflow-hidden">
                                <?php foreach ($active_schedules as $sched): 
                                    $isActive = ($filter_schedule_id == $sched['id']);
                                    $recorded = $schedCounts[$sched['id']] ?? 0;
                                ?>
                                    <button type="submit" name="schedule_id" value="<?= $sched['id'] ?>" 
                                            class="list-group-item list-group-item-action py-3 <?= $isActive ? 'active-schedule' : '' ?>">
                                        <div class="d-flex w-100 justify-content-between mb-1">
                                            <h6 class="mb-0 fw-bold <?= $isActive ? 'text-vintage-primary' : '' ?>"><?= htmlspecialchars($sched['subject_name']) ?></h6>
                                            <small class="text-muted fw-bold"><?= date('H:i', strtotime($sched['start_time'])) ?> – <?= date('H:i', strtotime($sched['end_time'])) ?></small>
                                        </div>
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <small class="text-muted"><i class="fas fa-users me-1"></i><?= htmlspecialchars($sched['class_name']) ?> · <i class="fas fa-door-open me-1"></i><?= htmlspecialchars($sched['room_code']) ?></small>
                                            <?php if ($recorded > 0): ?>
                                                <span class="badge bg-success rounded-pill"><?= $recorded ?> <i class="fas fa-check-circle ms-1"></i></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill">ยังไม่บันทึก</span>
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ══════ RIGHT: Student Roster ══════ -->
    <div class="col-lg-8 mb-4">
        <div class="card vintage-card border-0 h-100">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 text-vintage-dark fw-bold"><i class="fas fa-users me-2 text-gold"></i>รายชื่อนักเรียน</h5>
                    <?php if ($current_schedule_info): ?>
                        <p class="text-muted small mb-0">
                            <span class="badge bg-vintage-light text-vintage-primary me-1"><?= htmlspecialchars($current_schedule_info['subject_code']) ?></span>
                            <?= htmlspecialchars($current_schedule_info['subject_name']) ?> 
                            | <?= htmlspecialchars($current_schedule_info['class_name']) ?> 
                            | <?= date('H:i', strtotime($current_schedule_info['start_time'])) ?>–<?= date('H:i', strtotime($current_schedule_info['end_time'])) ?>
                            | ห้อง <?= htmlspecialchars($current_schedule_info['room_code']) ?>
                            | ครู<?= htmlspecialchars($current_schedule_info['teacher_name']) ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted small mb-0">กรุณาเลือกคาบเรียนจากเมนูด้านซ้าย</p>
                    <?php endif; ?>
                </div>
                <?php if ($current_schedule_info): ?>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-light text-dark border p-2"><i class="fas fa-users me-1"></i> <?= count($students) ?> คน</span>
                    <button class="btn btn-sm btn-outline-success" id="btnMarkAllPresent" title="เช็คชื่อทั้งหมด: มาเรียน"><i class="fas fa-check-double me-1"></i> มาทั้งหมด</button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-0">
                <?php if ($filter_schedule_id && !empty($students)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 vintage-table">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th scope="col" width="7%" class="ps-4">ลำดับ</th>
                                    <th scope="col" width="13%">รหัส</th>
                                    <th scope="col" width="30%">ชื่อ – สกุล</th>
                                    <th scope="col" width="50%" class="text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; foreach ($students as $st): ?>
                                <tr id="row_<?= $st['id'] ?>">
                                    <td class="ps-4 text-muted"><?= $counter++ ?></td>
                                    <td><strong class="text-vintage-primary"><?= htmlspecialchars($st['student_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($st['prefix'] . $st['first_name'] . ' ' . $st['last_name']) ?></td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <div class="btn-group rounded-pill overflow-hidden shadow-sm" role="group">
                                                <?php 
                                                $statusOptions = [
                                                    'Present' => ['label' => 'มา', 'icon' => 'check', 'outline' => 'success'],
                                                    'Absent'  => ['label' => 'ขาด', 'icon' => 'times', 'outline' => 'danger'],
                                                    'Late'    => ['label' => 'สาย', 'icon' => 'clock', 'outline' => 'warning'],
                                                    'Leave'   => ['label' => 'ลา', 'icon' => 'envelope-open-text', 'outline' => 'info'],
                                                ];
                                                foreach ($statusOptions as $val => $opt): 
                                                ?>
                                                <input type="radio" class="btn-check attendance-toggle" 
                                                       name="status_<?= $st['id'] ?>" 
                                                       id="<?= strtolower($val) ?>_<?= $st['id'] ?>" value="<?= $val ?>" autocomplete="off"
                                                       data-student="<?= $st['id'] ?>" data-schedule="<?= $filter_schedule_id ?>" data-date="<?= $filter_date ?>"
                                                       <?= ($st['current_status'] === $val) ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-<?= $opt['outline'] ?> px-3 py-1 btn-sm" for="<?= strtolower($val) ?>_<?= $st['id'] ?>">
                                                    <i class="fas fa-<?= $opt['icon'] ?> me-1"></i> <?= $opt['label'] ?>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <span class="spinner-border spinner-border-sm text-vintage-primary ms-2 d-none" id="spinner_<?= $st['id'] ?>" role="status"></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($filter_schedule_id && empty($students)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-user-slash fa-3x mb-3 text-black-50"></i>
                        <h5>ไม่พบรายชื่อนักเรียน</h5>
                        <p>ไม่มีนักเรียนลงทะเบียนในชั้นเรียนนี้</p>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted d-flex flex-column justify-content-center h-100" style="min-height: 350px;">
                        <i class="fas fa-tasks fa-3x mb-3 text-black-50"></i>
                        <h5>เลือกคาบเรียนเพื่อเช็คชื่อ</h5>
                        <p>กรุณาคลิกเลือกคาบสอนจากเมนูด้านซ้ายเพื่อแสดงรายชื่อนักเรียนและบันทึกเวลาเรียน</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.focus-vintage:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(179, 139, 74, 0.25); }
.active-schedule { background: #f0f4ff; border-left: 4px solid var(--vintage-primary) !important; }
.btn-group .btn { font-size: 0.85rem; transition: all 0.15s ease; }
.btn-group.rounded-pill { border-radius: 50rem !important; }
.btn-group.rounded-pill .btn:first-of-type { border-top-left-radius: 50rem !important; border-bottom-left-radius: 50rem !important; }
.btn-group.rounded-pill .btn:last-of-type { border-top-right-radius: 50rem !important; border-bottom-right-radius: 50rem !important; }
.btn-group .btn-check:checked + .btn-outline-success { background-color: #198754; color: white; border-color: #198754; }
.btn-group .btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; border-color: #dc3545; }
.btn-group .btn-check:checked + .btn-outline-warning { background-color: #ffc107; color: #000; border-color: #ffc107; }
.btn-group .btn-check:checked + .btn-outline-info { background-color: #0dcaf0; color: #000; border-color: #0dcaf0; }
.stat-animated { animation: statPulse 0.3s ease; }
@keyframes statPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.15); }
    100% { transform: scale(1); }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // ── Animate stat card update ──
    function animateStat(el, value) {
        el.text(Number(value).toLocaleString());
        el.addClass('stat-animated');
        setTimeout(function() { el.removeClass('stat-animated'); }, 350);
    }

    // ── Attendance toggle AJAX ──
    $('.attendance-toggle').change(function() {
        var $radio     = $(this);
        var studentId  = $radio.data('student');
        var scheduleId = $radio.data('schedule');
        var dateVal    = $radio.data('date');
        var statusVal  = $radio.val();
        var $spinner   = $('#spinner_' + studentId);
        
        $spinner.removeClass('d-none');
        
        $.ajax({
            url: 'attendance.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'toggle_status',
                student_id: studentId,
                schedule_id: scheduleId,
                date: dateVal,
                status: statusVal
            },
            success: function(res) {
                setTimeout(function(){ $spinner.addClass('d-none'); }, 250);
                if (!res.success) {
                    alert('Error: ' + res.error);
                    return;
                }
                // Update stat cards from server response
                if (res.summary) {
                    animateStat($('#att-stat-total'),   res.summary.total);
                    animateStat($('#att-stat-present'), res.summary.present);
                    animateStat($('#att-stat-absent'),  res.summary.absent);
                    animateStat($('#att-stat-late'),    res.summary.late);
                    animateStat($('#att-stat-leave'),   res.summary.on_leave);
                }
            },
            error: function() {
                $spinner.addClass('d-none');
                alert('Connection error.');
            }
        });
    });

    // ── Mark All Present ──
    $('#btnMarkAllPresent').click(function() {
        if (!confirm('ต้องการเช็คชื่อ "มาเรียน" ทั้งหมดหรือไม่?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...');

        var radios = $('input.attendance-toggle[value="Present"]');
        var total = radios.length;
        var done = 0;

        radios.each(function() {
            var $r = $(this);
            if ($r.is(':checked')) { done++; checkDone(); return; }
            $r.prop('checked', true).trigger('change');
            done++;
            checkDone();
        });

        function checkDone() {
            if (done >= total) {
                setTimeout(function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-check-double me-1"></i> มาทั้งหมด');
                }, 1000);
            }
        }
    });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
