<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('Admin');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $class_name = $_POST['class_name'] ?? '';

            $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE class_name = ?");
            $checkStmt->execute([$class_name]);
            if ($checkStmt->fetch()) {
                $error = "ระดับชั้นซ้ำกันในระบบ";
            } else {
                $stmt = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
                $stmt->execute([$class_name]);
                $message = "เพิ่มข้อมูลระดับชั้นสำเร็จ";
            }
        } elseif ($action === 'edit') {
            $id = $_POST['id'] ?? 0;
            $class_name = $_POST['class_name'] ?? '';

            $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE class_name = ? AND id != ?");
            $checkStmt->execute([$class_name, $id]);
            if ($checkStmt->fetch()) {
                $error = "ระดับชั้นซ้ำกันในระบบ";
            } else {
                $stmt = $pdo->prepare("UPDATE classes SET class_name=? WHERE id=?");
                $stmt->execute([$class_name, $id]);
                $message = "แก้ไขข้อมูลระดับชั้นสำเร็จ";
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM classes WHERE id=?");
            $stmt->execute([$id]);
            $message = "ลบข้อมูลระดับชั้นสำเร็จ";
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Handle GET Requests
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'class_name';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] === 'desc' ? 'DESC' : 'ASC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$allowed_sort_columns = ['id', 'class_name'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'class_name';
}

$where_sql = "1=1";
$params = [];
if ($search) {
    $where_sql .= " AND (class_name LIKE ?)";
    $like_search = "%$search%";
    $params = [$like_search];
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "SELECT * FROM classes WHERE $where_sql ORDER BY $sort_by $sort_order LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <h3 class="text-vintage-heading mb-0"><i class="fas fa-layer-group me-2 text-gold"></i> ระดับชั้นเรียน</h3>
    <button class="btn btn-vintage rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-2"></i>เพิ่มระดับชั้น
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
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" placeholder="ค้นหาชื่อระดับชั้น..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-vintage" type="submit">ค้นหา</button>
                    <?php if($search): ?>
                        <a href="classes.php" class="btn btn-outline-secondary">ล้าง</a>
                    <?php endif; ?>
                </div>
            </div>
            <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
            <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle vintage-table w-100">
                <thead>
                    <tr>
                        <th scope="col" width="15%">รหัส <?php echo getSortLink('id', $sort_by, $sort_order, $search); ?></th>
                        <th scope="col" width="60%">ชื่อระดับชั้น <?php echo getSortLink('class_name', $sort_by, $sort_order, $search); ?></th>
                        <th scope="col" width="25%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($classes) > 0): ?>
                        <?php foreach ($classes as $c): ?>
                            <tr>
                                <td><span class="fw-bold">C<?php echo str_pad($c['id'], 3, '0', STR_PAD_LEFT); ?></span></td>
                                <td><?php echo htmlspecialchars($c['class_name']); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $c['id']; ?>" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $c['id']; ?>" title="ลบ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade flex-modal" id="editModal<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-vintage-dark text-white">
                                            <h5 class="modal-title"><i class="fas fa-edit me-2 text-gold"></i> แก้ไขระดับชั้น</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="classes.php">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted small">ชื่อระดับชั้น</label>
                                                    <input type="text" class="form-control" name="class_name" value="<?php echo htmlspecialchars($c['class_name']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-top-0">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-vintage">บันทึก</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Modal -->
                            <div class="modal fade flex-modal" id="deleteModal<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content border-0 shadow-lg text-center">
                                        <div class="modal-body p-4">
                                            <i class="fas fa-exclamation-circle text-danger fa-3x mb-3"></i>
                                            <h5>ยืนยันการลบ?</h5>
                                            <p class="text-muted mb-4">คุณต้องการลบ <strong><?php echo htmlspecialchars($c['class_name']); ?></strong> ใช่หรือไม่?</p>
                                            <form method="POST" action="classes.php">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">ยกเลิก</button>
                                                <button type="submit" class="btn btn-danger">ลบข้อมูล</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">ไม่พบข้อมูล</td></tr>
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
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-vintage-dark text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-gold"></i> เพิ่มระดับชั้นใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="classes.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label text-muted small">ชื่อระดับชั้น</label>
                        <input type="text" class="form-control" name="class_name" required>
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
.vintage-table th { background-color: var(--vintage-bg); color: var(--vintage-dark); font-family: 'Playfair Display', serif; font-weight: 600; border-bottom: 2px solid #e1dfd8; }
.vintage-table td { color: #4a4743; border-color: #edebe4; }
.vintage-pagination .page-item.active .page-link { background-color: var(--vintage-primary); border-color: var(--vintage-primary); color: white; }
.vintage-pagination .page-link { color: var(--vintage-dark); }
.btn-outline-vintage { color: var(--vintage-primary); border-color: var(--vintage-primary); }
.btn-outline-vintage:hover { background-color: var(--vintage-primary); color: white; }
.flex-modal .modal-dialog { display: flex; align-items: center; min-height: calc(100% - 1rem); }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>
