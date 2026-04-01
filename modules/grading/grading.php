<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'];
$ref_id = $_SESSION['reference_id'];

if ($role === 'Student') {
    die("Access Denied: Students cannot access the grading system.");
}

$error = '';
$success = '';

$academic_year = '2569';
$term = '1';

// Form Handling (Save Grades)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grades') {
    $subject_id = $_POST['subject_id'] ?? 0;
    $class_id = $_POST['class_id'] ?? 0;
    $teacher_id = $_POST['teacher_id'] ?? $ref_id;
    
    // We expect arrays of raw_score indexed by student_id
    $scores = $_POST['raw_score'] ?? [];
    
    if ($subject_id && $class_id && !empty($scores)) {
        try {
            $pdo->beginTransaction();
            
            $stmtCheck = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND class_id = ? AND academic_year = ? AND term = ?");
            $stmtUpdate = $pdo->prepare("UPDATE grades SET raw_score = ?, grade = ?, teacher_id = ? WHERE id = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO grades (student_id, subject_id, class_id, teacher_id, raw_score, grade, academic_year, term) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($scores as $std_id => $raw) {
                // If raw score is exactly empty string, we might want to skip or Null it. 
                // We'll treat empty as 0 or NULL based on logic. Let's allow NULL if empty.
                if ($raw === '') {
                    $raw = null;
                    $calc_grade = null;
                } else {
                    $raw = (float)$raw;
                    // Auto calculate grade mapping
                    if ($raw >= 80) $calc_grade = 4.0;
                    elseif ($raw >= 70) $calc_grade = 3.0;
                    elseif ($raw >= 60) $calc_grade = 2.0;
                    elseif ($raw >= 50) $calc_grade = 1.0;
                    else $calc_grade = 0.0;
                }
                
                $stmtCheck->execute([$std_id, $subject_id, $class_id, $academic_year, $term]);
                $existing = $stmtCheck->fetch();
                
                if ($existing) {
                    $stmtUpdate->execute([$raw, $calc_grade, $teacher_id, $existing['id']]);
                } else {
                    $stmtInsert->execute([$std_id, $subject_id, $class_id, $teacher_id, $raw, $calc_grade, $academic_year, $term]);
                }
            }
            
            $pdo->commit();
            $success = "บันทึกคะแนนเรียบร้อยแล้ว (Grades saved successfully)";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาดในการบันทึกคะแนน: " . $e->getMessage();
        }
    } else {
        $error = "ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบอีกครั้ง";
    }
}

// Data Fetching for Dropdowns
$filter_class_id = $_GET['class_id'] ?? '';
$filter_subject_id = $_GET['subject_id'] ?? '';
$filter_teacher_id = ($role === 'Teacher') ? $ref_id : ($_GET['teacher_id'] ?? '');

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

if ($role === 'Admin') {
    $teachers = $pdo->query("SELECT id, first_name, last_name, prefix FROM teachers ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Students and existing grades if filters applied
$students_roster = [];
if ($filter_class_id && $filter_subject_id && $filter_teacher_id) {
    // 1. Get Students in this class
    // 2. Left join grades table
    $query = "SELECT st.id, st.student_code, st.prefix, st.first_name, st.last_name,
                     g.raw_score, g.grade
              FROM students st
              LEFT JOIN grades g ON st.id = g.student_id 
                   AND g.subject_id = ? 
                   AND g.class_id = ? 
                   AND g.academic_year = ? 
                   AND g.term = ?
              WHERE st.class_id = ?
              ORDER BY st.student_code";
              
    $stmtRoster = $pdo->prepare($query);
    $stmtRoster->execute([$filter_subject_id, $filter_class_id, $academic_year, $term, $filter_class_id]);
    $students_roster = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);
}

?>
<?php include APP_ROOT . '/includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-vintage-heading mb-0"><i class="fas fa-star-half-alt me-2 text-gold"></i> บันทึกคะแนน (Grading System)</h3>
    <div class="badge bg-light text-dark border p-2 vintage-badge">
        <i class="far fa-calendar-alt me-1 text-gold"></i> ปีการศึกษา <?php echo $academic_year; ?> / เทอม <?php echo $term; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card vintage-card border-0">
            <div class="card-body p-4">
                <form method="GET" action="grading.php" class="row g-3 align-items-end">
                    
                    <?php if ($role === 'Admin'): ?>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">ครูผู้สอน (Teacher)</label>
                        <select name="teacher_id" class="form-select focus-vintage" required>
                            <option value="">-- เลือกครูผู้สอน --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($filter_teacher_id == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['prefix'] . $t['first_name'] . ' ' . $t['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">รายวิชา (Subject)</label>
                        <select name="subject_id" class="form-select focus-vintage" required>
                            <option value="">-- เลือกรายวิชา --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($filter_subject_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['subject_code'] . ' - ' . $s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">ระดับชั้น (Class)</label>
                        <select name="class_id" class="form-select focus-vintage" required>
                            <option value="">-- เลือกระดับชั้น --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($filter_class_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-vintage w-100"><i class="fas fa-search me-1"></i> โหลดข้อมูล</button>
                    </div>
                    <div class="col-md-1">
                        <a href="grading.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt"></i></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($filter_class_id && $filter_subject_id): ?>
<div class="card vintage-card border-0 mb-4">
    <div class="card-header bg-vintage-dark text-white p-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list-ol me-2 text-gold"></i> แบบบันทึกคะแนน</h5>
        <span class="badge bg-light text-dark"><?php echo count($students_roster); ?> นักเรียน</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students_roster)): ?>
            <div class="p-5 text-center text-muted">
                <p>ไม่พบนักเรียนในชั้นเรียนนี้</p>
            </div>
        <?php else: ?>
            <form method="POST" action="grading.php?class_id=<?php echo $filter_class_id; ?>&subject_id=<?php echo $filter_subject_id; ?>&teacher_id=<?php echo $filter_teacher_id; ?>">
                <input type="hidden" name="action" value="save_grades">
                <input type="hidden" name="class_id" value="<?php echo $filter_class_id; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $filter_subject_id; ?>">
                <input type="hidden" name="teacher_id" value="<?php echo $filter_teacher_id; ?>">
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 vintage-table">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th scope="col" width="10%" class="ps-4">ลำดับ</th>
                                <th scope="col" width="15%">รหัสนักเรียน</th>
                                <th scope="col" width="30%">ชื่อ - สกุล</th>
                                <th scope="col" width="20%" class="text-center">คะแนนเก็บรวม (100)</th>
                                <th scope="col" width="25%" class="text-center">เกรดที่ได้ (ผลการเรียน)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter=1; foreach ($students_roster as $st): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($st['student_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($st['prefix'] . $st['first_name'] . ' ' . $st['last_name']); ?></td>
                                <td class="text-center">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control text-center mx-auto score-input focus-vintage" style="width: 100px;" 
                                           name="raw_score[<?php echo $st['id']; ?>]" 
                                           value="<?php echo isset($st['raw_score']) ? htmlspecialchars($st['raw_score']) : ''; ?>"
                                           data-student-id="<?php echo $st['id']; ?>"
                                           placeholder="-">
                                </td>
                                <td class="text-center">
                                    <div class="grade-display p-2 fw-bold text-vintage-primary fs-5" id="grade_display_<?php echo $st['id']; ?>">
                                        <?php 
                                            if (isset($st['grade'])) {
                                                echo htmlspecialchars(number_format($st['grade'], 1));
                                            } else {
                                                echo '<span class="text-muted fs-6 fw-normal">-</span>';
                                            }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 bg-light border-top text-end">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm rounded-pill"><i class="fas fa-save me-2"></i> บันทึกผลการเรียน</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
.focus-vintage:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(179, 139, 74, 0.25); }
.vintage-table th { font-family: 'Playfair Display', serif; font-weight: 600; padding-top: 15px; padding-bottom: 15px; }
.score-input { border-radius: 8px; font-weight: 600; background-color: #fcfbf9; }
.grade-display { font-family: 'Playfair Display', serif; letter-spacing: 1px; }

/* Dynamic Grade Colors Addicted by JS */
.grade-A { color: #198754 !important; } /* Green */
.grade-B { color: #0d6efd !important; } /* Blue */
.grade-C { color: #fd7e14 !important; } /* Orange */
.grade-D { color: #ffc107 !important; } /* Yellow */
.grade-F { color: #dc3545 !important; } /* Red */
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto calculate grades on input blur or keyup (debounced)
    const scoreInputs = document.querySelectorAll('.score-input');
    
    function calculateGrade(score) {
        if (score === '' || isNaN(score)) return '-';
        score = parseFloat(score);
        if (score >= 80) return { val: '4.0', class: 'grade-A' };
        if (score >= 70) return { val: '3.0', class: 'grade-B' };
        if (score >= 60) return { val: '2.0', class: 'grade-C' };
        if (score >= 50) return { val: '1.0', class: 'grade-D' };
        return { val: '0.0', class: 'grade-F' };
    }

    scoreInputs.forEach(input => {
        
        // Initial color setting on load
        if(input.value !== '') {
            const initialResult = calculateGrade(input.value);
            const displayObj = document.getElementById('grade_display_' + input.dataset.studentId);
            if(initialResult !== '-') {
                 displayObj.className = 'grade-display p-2 fw-bold fs-5 ' + initialResult.class;
            }
        }

        input.addEventListener('input', function() {
            const studentId = this.dataset.studentId;
            const score = this.value;
            const displayEl = document.getElementById('grade_display_' + studentId);
            
            const result = calculateGrade(score);
            
            if (result === '-') {
                displayEl.innerHTML = '<span class="text-muted fs-6 fw-normal">-</span>';
                displayEl.className = 'grade-display p-2 fw-bold text-vintage-primary fs-5';
            } else {
                displayEl.innerHTML = result.val;
                displayEl.className = 'grade-display p-2 fw-bold fs-5 ' + result.class;
            }
        });
    });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
