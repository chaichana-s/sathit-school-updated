<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('Admin'); // Only Admins can access user management

$error = '';
$success = '';

// Handle CRUD Operations for Users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_user') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'Student';
                $reference_id = $_POST['reference_id'] !== '' ? $_POST['reference_id'] : null;

                if (empty($username) || empty($password)) {
                    throw new Exception("ชื่อผู้ใช้และรหัสผ่านไม่สามารถเว้นว่างได้ (Username and password cannot be empty)");
                }
                
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, reference_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $role, $reference_id]);
                $success = "เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว (User added successfully)";

            } elseif ($_POST['action'] === 'edit_user') {
                $id = $_POST['user_id'];
                $username = $_POST['username'];
                $role = $_POST['role'];
                $reference_id = $_POST['reference_id'] !== '' ? $_POST['reference_id'] : null;
                $new_password = $_POST['new_password'] ?? '';

                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, password_hash=?, role=?, reference_id=? WHERE id=?");
                    $stmt->execute([$username, $hash, $role, $reference_id, $id]);
                    $success = "แก้ไขข้อมูลและรหัสผ่านเรียบร้อยแล้ว (User and password updated)";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, reference_id=? WHERE id=?");
                    $stmt->execute([$username, $role, $reference_id, $id]);
                    $success = "แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว (User updated successfully)";
                }

            } elseif ($_POST['action'] === 'delete_user') {
                $id = $_POST['user_id'];
                // Prevent self-deletion
                if ($id == $_SESSION['user_id']) {
                    throw new Exception("ไม่สามารถลบบัญชีของตนเองได้ (Cannot delete your own account)");
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);
                $success = "ลบผู้ใช้งานเรียบร้อยแล้ว (User deleted successfully)";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Pagination logic
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search Logic
$search = $_GET['search'] ?? '';
$whereClause = "";
$params = [];

if ($search) {
    $whereClause = "WHERE username LIKE ? OR role LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch total records for pagination
$countQuery = "SELECT COUNT(*) FROM users " . $whereClause;
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$total_records = $stmtCount->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch data
$query = "SELECT u.*, 
                 t.first_name as t_fn, t.last_name as t_ln,
                 s.first_name as s_fn, s.last_name as s_ln
          FROM users u
          LEFT JOIN teachers t ON u.role = 'Teacher' AND u.reference_id = t.id
          LEFT JOIN students s ON u.role = 'Student' AND u.reference_id = s.id
          " . str_replace("WHERE", "WHERE u.", $whereClause) . "
          ORDER BY u.role, u.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include APP_ROOT . '/includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-vintage-heading mb-0"><i class="fas fa-users-cog me-2 text-gold"></i> จัดการผู้ใช้ & สิทธิ์ (RBAC)</h3>
    <button class="btn btn-vintage rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus me-2"></i> เพิ่มผู้ใช้งาน
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card vintage-card border-0 mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
        <div class="search-box w-50">
            <form method="GET" action="rbac.php" class="input-group">
                <span class="input-group-text bg-white border-end-0 text-gold"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0 focus-vintage" placeholder="ค้นหาด้วยชื่อผู้ใช้ หรือ สิทธิ์..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary" type="submit">ค้นหา</button>
            </form>
        </div>
        <div class="badge bg-light text-dark p-2 border">รวม <?php echo $total_records; ?> บัญชี</div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 vintage-table">
                <thead class="bg-light text-muted">
                    <tr>
                        <th scope="col" class="ps-4">ID</th>
                        <th scope="col">ชื่อผู้ใช้งาน (Username)</th>
                        <th scope="col">สิทธิ์ (Role)</th>
                        <th scope="col">รหัสอ้างอิง (Ref ID)</th>
                        <th scope="col">วันที่สร้าง (Created)</th>
                        <th scope="col" class="text-end pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="ps-4 text-muted">#<?php echo $u['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                        <td>
                            <?php if ($u['role'] === 'Admin'): ?>
                                <span class="badge bg-danger rounded-pill px-3">Admin</span>
                            <?php elseif ($u['role'] === 'Teacher'): ?>
                                <span class="badge bg-primary rounded-pill px-3" style="background-color: #3b5998 !important;">Teacher</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3">Student</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?php 
                            if ($u['role'] === 'Admin') {
                                echo '-';
                            } elseif ($u['role'] === 'Teacher' && $u['t_fn']) {
                                echo htmlspecialchars($u['t_fn'] . ' ' . $u['t_ln']);
                            } elseif ($u['role'] === 'Student' && $u['s_fn']) {
                                echo htmlspecialchars($u['s_fn'] . ' ' . $u['s_ln']);
                            } else {
                                echo 'ID: ' . htmlspecialchars($u['reference_id'] ?? '-'); 
                            }
                            ?>
                        </td>
                        <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-secondary rounded-circle me-1" 
                                    data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>"
                                    title="แก้ไข/รีเซ็ตรหัสผ่าน">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="rbac.php" class="d-inline" onsubmit="return confirm('ยืนยันการลบบัญชีผู้ใช้นี้? (Confirm deletion)');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="ลบ">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editUserModal<?php echo $u['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-vintage-dark text-white border-0">
                                    <h5 class="modal-title font-playfair"><i class="fas fa-user-edit me-2 text-gold"></i>แก้ไขผู้ใช้งาน: <?php echo htmlspecialchars($u['username']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="rbac.php">
                                    <div class="modal-body p-4 bg-vintage-bg">
                                        <input type="hidden" name="action" value="edit_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-bold">ชื่อผู้ใช้งาน (Username)</label>
                                            <input type="text" class="form-control focus-vintage" name="username" value="<?php echo htmlspecialchars($u['username']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-bold">รีเซ็ตรหัสผ่าน (New Password) <span class="text-danger fw-normal">เว้นว่างไว้หากไม่ต้องการเปลี่ยน</span></label>
                                            <input type="password" class="form-control focus-vintage border-warning" name="new_password" placeholder="พิมพ์รหัสผ่านใหม่...">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted small fw-bold">ระดับสิทธิ์ (Role)</label>
                                                <select class="form-select focus-vintage" name="role">
                                                    <option value="Admin" <?php echo ($u['role'] == 'Admin') ? 'selected' : ''; ?>>Admin (ผู้ดูแลระบบ)</option>
                                                    <option value="Teacher" <?php echo ($u['role'] == 'Teacher') ? 'selected' : ''; ?>>Teacher (ครู)</option>
                                                    <option value="Student" <?php echo ($u['role'] == 'Student') ? 'selected' : ''; ?>>Student (นักเรียน)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label text-muted small fw-bold">Ref ID <span class="text-muted fw-normal">(ครู/นักเรียน ID)</span></label>
                                                <input type="number" class="form-control focus-vintage" name="reference_id" value="<?php echo htmlspecialchars($u['reference_id'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-top-0 bg-light">
                                        <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" class="btn btn-vintage px-4">บันทึก</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white p-3 border-top">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.$search : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-vintage-dark text-white border-0">
                <h5 class="modal-title font-playfair"><i class="fas fa-user-plus me-2 text-gold"></i>เพิ่มบัชญีผู้ใช้งานใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="rbac.php">
                <div class="modal-body p-4 bg-vintage-bg">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">ชื่อผู้ใช้งาน (Username) *</label>
                        <input type="text" class="form-control focus-vintage" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">รหัสผ่านเริ่มต้น (Password) *</label>
                        <input type="password" class="form-control focus-vintage" name="password" required value="password123">
                        <small class="text-muted">ระบบตั้งค่าเริ่มต้นเป็น: `password123`</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold">ระดับสิทธิ์ (Role)</label>
                            <select class="form-select focus-vintage" name="role">
                                <option value="Student">Student (นักเรียน)</option>
                                <option value="Teacher">Teacher (ครู)</option>
                                <option value="Admin">Admin (ผู้ดูแลระบบ)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold">Ref ID <span class="text-muted fw-normal">(ถ้ามี)</span></label>
                            <input type="number" class="form-control focus-vintage" name="reference_id" placeholder="ID อ้างอิงตารางหลัก">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-vintage px-4">สร้างบัญชี</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.focus-vintage:focus { border-color: var(--gold); box-shadow: 0 0 0 0.25rem rgba(179, 139, 74, 0.25); }
.vintage-table th { font-family: 'Playfair Display', serif; font-weight: 600; padding-top: 15px; padding-bottom: 15px; }
.pagination .page-item.active .page-link { background-color: var(--vintage-primary); border-color: var(--vintage-primary); color: white; }
.pagination .page-link { color: var(--vintage-dark); }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>
