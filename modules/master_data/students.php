<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('Admin');

$message = '';
$error = '';

// Handle POST Requests (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $student_code = $_POST['student_code'] ?? '';
            $prefix = $_POST['prefix'] ?? '';
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $dob = $_POST['dob'] ?? '';
            $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;

            // Check duplicate code
            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
            $checkStmt->execute([$student_code]);
            if ($checkStmt->fetch()) {
                $error = "รหัสประจำตัวนักเรียนซ้ำกันในระบบ";
            } else {
                $stmt = $pdo->prepare("INSERT INTO students (student_code, prefix, first_name, last_name, dob, class_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_code, $prefix, $first_name, $last_name, $dob, $class_id]);
                $message = "เพิ่มข้อมูลนักเรียนสำเร็จ";
            }
        } elseif ($action === 'edit') {
            $id = $_POST['id'] ?? 0;
            $student_code = $_POST['student_code'] ?? '';
            $prefix = $_POST['prefix'] ?? '';
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $dob = $_POST['dob'] ?? '';
            $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;

            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ? AND id != ?");
            $checkStmt->execute([$student_code, $id]);
            if ($checkStmt->fetch()) {
                $error = "รหัสประจำตัวนักเรียนซ้ำกันในระบบ";
            } else {
                $stmt = $pdo->prepare("UPDATE students SET student_code=?, prefix=?, first_name=?, last_name=?, dob=?, class_id=? WHERE id=?");
                $stmt->execute([$student_code, $prefix, $first_name, $last_name, $dob, $class_id, $id]);
                $message = "แก้ไขข้อมูลนักเรียนสำเร็จ";
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
            $stmt->execute([$id]);
            $message = "ลบข้อมูลนักเรียนสำเร็จ";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Handle GET Requests (Search, Sort, Pagination)
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'student_code';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] === 'desc' ? 'DESC' : 'ASC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Allowed sort columns
$allowed_sort_columns = ['id', 'student_code', 'first_name', 'last_name', 'class_name'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'student_code';
}

// Build Query
$where_sql = "1=1";
$params = [];
if ($search) {
    $where_sql .= " AND (s.student_code LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR c.class_name LIKE ?)";
    $like_search = "%$search%";
    $params = [$like_search, $like_search, $like_search, $like_search];
}

// Count total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Data
$query = "SELECT s.*, c.class_name 
          FROM students s 
          LEFT JOIN classes c ON s.class_id = c.id 
          WHERE $where_sql 
          ORDER BY $sort_by $sort_order 
          LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch classes for dropdowns
$class_stmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $class_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for sort links
function getSortLink($column, $current_sort, $current_order, $search) {
    $order = ($current_sort === $column && $current_order === 'ASC') ? 'desc' : 'asc';
    $icon = '';
    if ($current_sort === $column) {
        $icon = $current_order === 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort text-muted ms-1 opacity-50"></i>';
    }
    $url = "?sort_by=$column&sort_order=$order" . ($search ? "&search=" . urlencode($search) : "");
    return "<a href='$url' class='text-vintage-dark text-decoration-none'>$icon</a>";
}
?>
<?php include APP_ROOT . '/includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-vintage-heading mb-0"><i class="fas fa-user-graduate me-2 text-gold"></i> ข้อมูลนักเรียน</h3>
    <button class="btn btn-vintage rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-2"></i>เพิ่มนักเรียน
    </button>
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

<div class="card vintage-card border-0 mb-4">
    <div class="card-body p-4">
        <!-- Search Form -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" placeholder="ค้นหารหัสนักเรียน, ชื่อ, นามสกุล..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-vintage" type="submit">ค้นหา</button>
                    <?php if($search): ?>
                        <a href="students.php" class="btn btn-outline-secondary">ล้าง</a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Preserve Sort Params -->
            <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
            <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
        </form>

        <!-- Data Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle vintage-table">
                <thead>
                    <tr>
                        <th scope="col" width="15%">รหัสประจำตัว <?php echo getSortLink('student_code', $sort_by, $sort_order, $search); ?></th>
                        <th scope="col" width="30%">ชื่อ-สกุล <?php echo getSortLink('first_name', $sort_by, $sort_order, $search); ?></th>
                        <th scope="col" width="15%">วันเกิด</th>
                        <th scope="col" width="20%">ระดับชั้น <?php echo getSortLink('class_name', $sort_by, $sort_order, $search); ?></th>
                        <th scope="col" width="20%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['student_code']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-custom-gold text-dark rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width:35px; height:35px;">
                                            <?php echo mb_substr($s['first_name'], 0, 1); ?>
                                        </div>
                                        <?php echo htmlspecialchars($s['prefix'] . ' ' . $s['first_name'] . ' ' . $s['last_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo $s['dob'] ? date('d/m/Y', strtotime($s['dob'])) : '-'; ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['class_name'] ?? 'ไม่มีข้อมูล'); ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $s['id']; ?>" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $s['id']; ?>" title="ลบ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Modal for this row -->
                            <div class="modal fade flex-modal" id="editModal<?php echo $s['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-vintage-dark text-white">
                                            <h5 class="modal-title"><i class="fas fa-edit me-2 text-gold"></i> แก้ไขข้อมูลนักเรียน</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="students.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-12">
                                                        <label class="form-label text-muted small">รหัสประจำตัวนักเรียน</label>
                                                        <input type="text" class="form-control" name="student_code" value="<?php echo htmlspecialchars($s['student_code']); ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-muted small">คำนำหน้า</label>
                                                        <input type="text" class="form-control" name="prefix" value="<?php echo htmlspecialchars($s['prefix']); ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-muted small">ชื่อ</label>
                                                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($s['first_name']); ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-muted small">นามสกุล</label>
                                                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($s['last_name']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-muted small">วันเกิด</label>
                                                        <input type="date" class="form-control" name="dob" value="<?php echo htmlspecialchars($s['dob']); ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-muted small">ระดับชั้น</label>
                                                        <select class="form-select" name="class_id">
                                                            <option value="">- เลือกระดับชั้น -</option>
                                                            <?php foreach($classes as $c): ?>
                                                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $s['class_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-top-0">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-vintage">บันทึกการแก้ไข</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Modal for this row -->
                            <div class="modal fade flex-modal" id="deleteModal<?php echo $s['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content border-0 shadow-lg text-center">
                                        <div class="modal-body p-4">
                                            <i class="fas fa-exclamation-circle text-danger fa-3x mb-3"></i>
                                            <h5>ยืนยันการลบ?</h5>
                                            <p class="text-muted mb-4">คุณต้องการลบ <strong><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></strong> ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้</p>
                                            <form method="POST" action="students.php">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-danger">ลบข้อมูล</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center vintage-pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>">ก่อนหน้า</a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>">ถัดไป</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>

<!-- Add Modal -->
<div class="modal fade flex-modal" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-vintage-dark text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-gold"></i> เพิ่มข้อมูลนักเรียนใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="students.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-muted small">รหัสประจำตัวนักเรียน</label>
                            <input type="text" class="form-control" name="student_code" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">คำนำหน้า</label>
                            <input type="text" class="form-control" name="prefix" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">ชื่อ</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">นามสกุล</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">วันเกิด</label>
                            <input type="date" class="form-control" name="dob">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">ระดับชั้น</label>
                            <select class="form-select" name="class_id">
                                <option value="">- เลือกระดับชั้น -</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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

<style>
.vintage-table th {
    background-color: var(--vintage-bg);
    color: var(--vintage-dark);
    font-family: 'Playfair Display', serif;
    font-weight: 600;
    border-bottom: 2px solid #e1dfd8;
}
.vintage-table td {
    color: #4a4743;
    border-color: #edebe4;
}
.vintage-pagination .page-item.active .page-link {
    background-color: var(--vintage-primary);
    border-color: var(--vintage-primary);
    color: white;
}
.vintage-pagination .page-link {
    color: var(--vintage-dark);
}
.btn-outline-vintage {
    color: var(--vintage-primary);
    border-color: var(--vintage-primary);
}
.btn-outline-vintage:hover {
    background-color: var(--vintage-primary);
    color: white;
}
.flex-modal .modal-dialog {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
}
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>
