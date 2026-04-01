<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// Admin and Teacher can typically view. Admin handles full CRUD.
// Based on prompt: Teacher can "ดูตารางสอนของตนเอง" (View their own schedule)
// Student can "ดูตารางเรียนของตนเองเท่านั้น" (View their own class schedule)
// Admin can manage everything.
$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'];
$ref_id = $_SESSION['reference_id']; // teacher_id or student_id based on role

$message = '';
$error = '';

// Handle POST Requests (Add, Edit, Delete) - Admins only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Admin') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $class_id = $_POST['class_id'] ?? '';
            $subject_id = $_POST['subject_id'] ?? '';
            $teacher_id = $_POST['teacher_id'] ?? '';
            $classroom_id = $_POST['classroom_id'] ?? '';
            $day_of_week = $_POST['day_of_week'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';

            if (strtotime($end_time) <= strtotime($start_time)) {
                $error = "เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น";
            } else {
                $stmt = $pdo->prepare("INSERT INTO schedules (class_id, subject_id, teacher_id, classroom_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$class_id, $subject_id, $teacher_id, $classroom_id, $day_of_week, $start_time, $end_time]);
                $message = "เพิ่มข้อมูลตารางเรียนสำเร็จ";
            }
        } elseif ($action === 'edit') {
            $id = $_POST['id'] ?? 0;
            $class_id = $_POST['class_id'] ?? '';
            $subject_id = $_POST['subject_id'] ?? '';
            $teacher_id = $_POST['teacher_id'] ?? '';
            $classroom_id = $_POST['classroom_id'] ?? '';
            $day_of_week = $_POST['day_of_week'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';

            if (strtotime($end_time) <= strtotime($start_time)) {
                $error = "เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น";
            } else {
                $stmt = $pdo->prepare("UPDATE schedules SET class_id=?, subject_id=?, teacher_id=?, classroom_id=?, day_of_week=?, start_time=?, end_time=? WHERE id=?");
                $stmt->execute([$class_id, $subject_id, $teacher_id, $classroom_id, $day_of_week, $start_time, $end_time, $id]);
                $message = "แก้ไขข้อมูลตารางเรียนสำเร็จ";
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM schedules WHERE id=?");
            $stmt->execute([$id]);
            $message = "ลบข้อมูลตารางเรียนสำเร็จ";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Fetch Dropdown Data (For Admin Add/Edit forms)
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
$classrooms = $pdo->query("SELECT id, room_code, room_name FROM classrooms ORDER BY room_code")->fetchAll(PDO::FETCH_ASSOC);

// Handle Filter GET parameters (Admins can view all, select filter. Teachers see theirs, Students see theirs)
$filter_class_id = $_GET['class_id'] ?? '';
$filter_teacher_id = $_GET['teacher_id'] ?? '';

if ($role === 'Student') {
    // Student sees their own class schedule
    $stmt = $pdo->prepare("SELECT class_id FROM students WHERE id = ?");
    $stmt->execute([$ref_id]);
    $filter_class_id = $stmt->fetchColumn();
    $filter_teacher_id = ''; // Disregard teacher filter
} elseif ($role === 'Teacher') {
    // Teacher sees their own teaching schedule
    $filter_teacher_id = $ref_id;
    $filter_class_id = ''; // Unless they want to filter specific taught class, but we default to all their classes
} else {
    // For admin default behavior, maybe select the first class to show something
    if (empty($filter_class_id) && empty($filter_teacher_id) && !empty($classes)) {
        $filter_class_id = $classes[0]['id'];
    }
}

// Build query based on filters
$where_sql = "1=1";
$params = [];

if ($filter_class_id) {
    $where_sql .= " AND s.class_id = ?";
    $params[] = $filter_class_id;
}
if ($filter_teacher_id) {
    $where_sql .= " AND s.teacher_id = ?";
    $params[] = $filter_teacher_id;
}

$query = "SELECT s.*, 
          c.class_name, 
          sub.subject_code, sub.subject_name, 
          t.first_name AS teacher_first, t.last_name AS teacher_last, 
          cr.room_code
          FROM schedules s
          LEFT JOIN classes c ON s.class_id = c.id
          LEFT JOIN subjects sub ON s.subject_id = sub.id
          LEFT JOIN teachers t ON s.teacher_id = t.id
          LEFT JOIN classrooms cr ON s.classroom_id = cr.id
          WHERE $where_sql
          ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$schedule_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days_th = [
    'Monday' => 'วันจันทร์',
    'Tuesday' => 'วันอังคาร',
    'Wednesday' => 'วันพุธ',
    'Thursday' => 'วันพฤหัสบดี',
    'Friday' => 'วันศุกร์'
];

?>
<?php include APP_ROOT . '/includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-vintage-heading mb-0"><i class="fas fa-calendar-alt me-2 text-gold"></i> ตารางเรียน (Schedule)</h3>
    <?php if ($role === 'Admin'): ?>
        <button class="btn btn-vintage rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-2"></i>จัดตารางเรียน
        </button>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filter Form for Admin -->
<?php if ($role === 'Admin'): ?>
<div class="card vintage-card border-0 mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-muted small">ดูตารางของระดับชั้น</label>
                <select class="form-select" name="class_id" onchange="this.form.submit()">
                    <option value="">-- ไม่ระบุ --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filter_class_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto text-center">
                <span class="text-muted small">หรือ</span>
            </div>
            <div class="col-md-5">
                <label class="form-label text-muted small">ดูตารางสอนของครู</label>
                <select class="form-select" name="teacher_id" onchange="this.form.submit()">
                    <option value="">-- ไม่ระบุ --</option>
                    <?php foreach($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $filter_teacher_id == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Display Information Header -->
<div class="mb-3">
    <h5 class="text-vintage-dark">
        <?php 
        if ($filter_class_id && $filter_teacher_id) {
            echo "ผลการค้นหาข้อมูลตารางเรียน";
        } elseif ($filter_class_id) {
            $c_name = array_filter($classes, fn($c) => $c['id'] == $filter_class_id);
            echo "ตารางเรียน ระดับชั้น " . ($c_name ? htmlspecialchars(reset($c_name)['class_name']) : '');
        } elseif ($filter_teacher_id) {
            $t_name = array_filter($teachers, fn($t) => $t['id'] == $filter_teacher_id);
            echo "ตารางสอน คุณครู " . ($t_name ? htmlspecialchars(reset($t_name)['first_name'] . ' ' . reset($t_name)['last_name']) : '');
        } else {
            echo "ตารางเรียนทั้งหมด";
        }
        ?>
    </h5>
</div>

<!-- Schedule List / Table View -->
<div class="card vintage-card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle vintage-table w-100 mb-0">
                <thead>
                    <tr>
                        <th scope="col" width="15%">วัน</th>
                        <th scope="col" width="15%">เวลา</th>
                        <th scope="col" width="20%">วิชา</th>
                        <th scope="col" width="15%">ระดับชั้น</th>
                        <th scope="col" width="20%">ครูผู้สอน</th>
                        <th scope="col" width="10%">ห้อง</th>
                        <?php if ($role === 'Admin'): ?>
                        <th scope="col" width="5%" class="text-center">จัดการ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($schedule_data) > 0): ?>
                        <?php foreach ($schedule_data as $s): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $dayBgMapping = [
                                        'Monday' => 'bg-warning text-dark',
                                        'Tuesday' => 'bg-pink text-white',
                                        'Wednesday' => 'bg-success text-white',
                                        'Thursday' => 'bg-orange text-white',
                                        'Friday' => 'bg-primary text-white'
                                    ];
                                    $bgClass = $dayBgMapping[$s['day_of_week']] ?? 'bg-secondary text-white';
                                    ?>
                                    <span class="badge <?php echo $bgClass; ?> rounded-pill px-3 py-2 fw-normal" style="font-size: 0.85rem;">
                                        <?php echo isset($days_th[$s['day_of_week']]) ? $days_th[$s['day_of_week']] : $s['day_of_week']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo date('H:i', strtotime($s['start_time'])) . ' - ' . date('H:i', strtotime($s['end_time'])); ?></strong>
                                </td>
                                <td>
                                    <div class="fw-bold text-vintage-primary"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($s['subject_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($s['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['teacher_first'] . ' ' . $s['teacher_last']); ?></td>
                                <td><i class="fas fa-door-open text-muted me-1"></i> <?php echo htmlspecialchars($s['room_code']); ?></td>
                                <?php if ($role === 'Admin'): ?>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary mb-1 p-1 px-2" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $s['id']; ?>" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger p-1 px-2" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $s['id']; ?>" title="ลบ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($role === 'Admin'): ?>
                            <!-- Edit Modal for Admin -->
                            <div class="modal fade flex-modal" id="editModal<?php echo $s['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-vintage-dark text-white">
                                            <h5 class="modal-title"><i class="fas fa-edit me-2 text-gold"></i> แก้ไขตารางเรียน</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="schedule.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label text-muted small">ระดับชั้น</label>
                                                        <select class="form-select" name="class_id" required>
                                                            <?php foreach($classes as $c): ?>
                                                                <option value="<?php echo $c['id']; ?>" <?php echo $s['class_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-muted small">วิชา</label>
                                                        <select class="form-select" name="subject_id" required>
                                                            <?php foreach($subjects as $sub): ?>
                                                                <option value="<?php echo $sub['id']; ?>" <?php echo $s['subject_id'] == $sub['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub['subject_code'] . ' ' . $sub['subject_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-muted small">ครูผู้สอน</label>
                                                        <select class="form-select" name="teacher_id" required>
                                                            <?php foreach($teachers as $t): ?>
                                                                <option value="<?php echo $t['id']; ?>" <?php echo $s['teacher_id'] == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-muted small">ห้องเรียน</label>
                                                        <select class="form-select" name="classroom_id" required>
                                                            <?php foreach($classrooms as $cr): ?>
                                                                <option value="<?php echo $cr['id']; ?>" <?php echo $s['classroom_id'] == $cr['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cr['room_code'] . ' ' . $cr['room_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-muted small">วัน</label>
                                                        <select class="form-select" name="day_of_week" required>
                                                            <?php foreach($days_th as $en => $th): ?>
                                                                <option value="<?php echo $en; ?>" <?php echo $s['day_of_week'] == $en ? 'selected' : ''; ?>><?php echo $th; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-muted small">เวลาเริ่ม</label>
                                                        <input type="time" class="form-control" name="start_time" value="<?php echo date('H:i', strtotime($s['start_time'])); ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-muted small">เวลาสิ้นสุด</label>
                                                        <input type="time" class="form-control" name="end_time" value="<?php echo date('H:i', strtotime($s['end_time'])); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-top-0">
                                                <!-- Preserve filter -->
                                                <input type="hidden" name="filter_class" value="<?php echo htmlspecialchars($filter_class_id); ?>">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-vintage">บันทึกการแก้ไข</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Modal -->
                            <div class="modal fade flex-modal" id="deleteModal<?php echo $s['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content border-0 shadow-lg text-center">
                                        <div class="modal-body p-4">
                                            <i class="fas fa-exclamation-circle text-danger fa-3x mb-3"></i>
                                            <h5>ยืนยันการลบ?</h5>
                                            <p class="text-muted mb-4">คุณต้องการลบคาบเรียนวิชา <strong><?php echo htmlspecialchars($s['subject_name']); ?></strong> ใช่หรือไม่?</p>
                                            <form method="POST" action="schedule.php">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-danger">ลบข้อมูล</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-calendar-times mb-3 fa-2x d-block text-black-50"></i> ยังไม่มีข้อมูลตารางเรียนสำหรับตัวเลือกนี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($role === 'Admin'): ?>
<!-- Add Modal -->
<div class="modal fade flex-modal" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-vintage-dark text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-gold"></i> จัดตารางเรียนใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="schedule.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">ระดับชั้น</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">- เลือกระดับชั้น -</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">วิชา</label>
                            <select class="form-select" name="subject_id" required>
                                <option value="">- เลือกรายวิชา -</option>
                                <?php foreach($subjects as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subject_code'] . ' ' . $sub['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">ครูผู้สอน</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">- เลือกครูผู้สอน -</option>
                                <?php foreach($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">ห้องเรียน</label>
                            <select class="form-select" name="classroom_id" required>
                                <option value="">- เลือกห้องเรียน -</option>
                                <?php foreach($classrooms as $cr): ?>
                                    <option value="<?php echo $cr['id']; ?>"><?php echo htmlspecialchars($cr['room_code'] . ' ' . $cr['room_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">วัน</label>
                            <select class="form-select" name="day_of_week" required>
                                <option value="">- เลือกวัน -</option>
                                <?php foreach($days_th as $en => $th): ?>
                                    <option value="<?php echo $en; ?>"><?php echo $th; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">เวลาเริ่ม</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">เวลาสิ้นสุด</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-vintage">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.vintage-table th { background-color: var(--vintage-bg); color: var(--vintage-dark); font-family: 'Playfair Display', serif; font-weight: 600; border-bottom: 2px solid #e1dfd8; border-top: 1px solid #e1dfd8;}
.vintage-table td { color: #4a4743; border-color: #edebe4; border-bottom: 1px solid #edebe4;}
/* Custom colors for days */
.bg-pink { background-color: #d86c8f; }
.bg-orange { background-color: #d18236; }
.flex-modal .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>
