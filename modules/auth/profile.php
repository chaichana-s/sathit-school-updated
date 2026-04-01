<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$ref_id = $_SESSION['reference_id'] ?? null;

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        try {
            $pdo->beginTransaction();
            
            // Update users table (username)
            $new_username = trim($_POST['username'] ?? '');
            if (!empty($new_username)) {
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmtCheck->execute([$new_username, $user_id]);
                if ($stmtCheck->rowCount() > 0) {
                    throw new Exception("ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว");
                }
                
                $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$new_username, $user_id]);
                $_SESSION['username'] = $new_username; // update session
            }
            
            // Update reference tables based on role
            if ($role === 'Teacher' && $ref_id) {
                $prefix = trim($_POST['prefix'] ?? '');
                $fname = trim($_POST['first_name'] ?? '');
                $lname = trim($_POST['last_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                $pdo->prepare("UPDATE teachers SET prefix = ?, first_name = ?, last_name = ?, phone = ? WHERE id = ?")
                    ->execute([$prefix, $fname, $lname, $phone, $ref_id]);
            } elseif ($role === 'Student' && $ref_id) {
                $prefix = trim($_POST['prefix'] ?? '');
                $fname = trim($_POST['first_name'] ?? '');
                $lname = trim($_POST['last_name'] ?? '');
                $dob = trim($_POST['dob'] ?? '');
                
                $pdo->prepare("UPDATE students SET prefix = ?, first_name = ?, last_name = ?, dob = ? WHERE id = ?")
                    ->execute([$prefix, $fname, $lname, $dob, $ref_id]);
            }
            
            $pdo->commit();
            $message = "อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch current user data
$userData = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$userData->execute([$user_id]);
$user = $userData->fetch(PDO::FETCH_ASSOC);

$refData = null;
if ($role === 'Teacher' && $ref_id) {
    $refDataStmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $refDataStmt->execute([$ref_id]);
    $refData = $refDataStmt->fetch(PDO::FETCH_ASSOC);
} elseif ($role === 'Student' && $ref_id) {
    $refDataStmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $refDataStmt->execute([$ref_id]);
    $refData = $refDataStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include APP_ROOT . '/includes/header.php'; ?>

<div class="row justify-content-center mt-5 mb-5">
    <div class="col-md-8 col-lg-6">
        <div class="card vintage-card shadow border-0">
            <div class="card-header bg-transparent border-bottom px-4 pt-4 pb-3 text-center">
                 <div class="mb-3 mt-2">
                     <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&background=8b4513&color=fff&rounded=true&size=80" alt="Profile" class="rounded-circle shadow-sm border border-2 border-white">
                 </div>
                 <h4 class="mb-0 text-vintage-heading fw-bold">จัดการโปรไฟล์ (Edit Profile)</h4>
                 <p class="text-muted small mb-0 mt-1"><span class="badge bg-custom-gold text-dark"><i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($user['role']) ?></span></p>
            </div>
            
            <div class="card-body p-4 p-md-5">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success stat-animated" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <h6 class="text-vintage-primary fw-bold mb-3 border-bottom pb-2"><i class="fas fa-user-circle me-2"></i> บัญชีผู้ใช้ระบบ</h6>
                    <div class="mb-4">
                        <label for="username" class="form-label text-muted fw-bold">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" class="form-control focus-vintage bg-light" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    
                    <?php if ($role === 'Teacher' && $refData): ?>
                        <h6 class="text-vintage-primary fw-bold mb-3 border-bottom pb-2 mt-4"><i class="fas fa-address-card me-2"></i> ข้อมูลส่วนตัว (Teacher Profile)</h6>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="prefix" class="form-label text-muted fw-bold">คำนำหน้า</label>
                                <input type="text" class="form-control focus-vintage" id="prefix" name="prefix" value="<?= htmlspecialchars($refData['prefix'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="first_name" class="form-label text-muted fw-bold">ชื่อ</label>
                                <input type="text" class="form-control focus-vintage" id="first_name" name="first_name" value="<?= htmlspecialchars($refData['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="last_name" class="form-label text-muted fw-bold">นามสกุล</label>
                                <input type="text" class="form-control focus-vintage" id="last_name" name="last_name" value="<?= htmlspecialchars($refData['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="phone" class="form-label text-muted fw-bold">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" class="form-control focus-vintage" id="phone" name="phone" value="<?= htmlspecialchars($refData['phone'] ?? '') ?>">
                        </div>
                        
                    <?php elseif ($role === 'Student' && $refData): ?>
                        <h6 class="text-vintage-primary fw-bold mb-3 border-bottom pb-2 mt-4"><i class="fas fa-address-card me-2"></i> ข้อมูลส่วนตัว (Student Profile)</h6>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">รหัสนักเรียน (ห้ามแก้ไข)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($refData['student_code'] ?? '') ?>" disabled>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="prefix" class="form-label text-muted fw-bold">คำนำหน้า</label>
                                <input type="text" class="form-control focus-vintage" id="prefix" name="prefix" value="<?= htmlspecialchars($refData['prefix'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="first_name" class="form-label text-muted fw-bold">ชื่อ</label>
                                <input type="text" class="form-control focus-vintage" id="first_name" name="first_name" value="<?= htmlspecialchars($refData['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="last_name" class="form-label text-muted fw-bold">นามสกุล</label>
                                <input type="text" class="form-control focus-vintage" id="last_name" name="last_name" value="<?= htmlspecialchars($refData['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="dob" class="form-label text-muted fw-bold">วันเกิด</label>
                            <input type="date" class="form-control focus-vintage" id="dob" name="dob" value="<?= htmlspecialchars($refData['dob'] ?? '') ?>" required>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                        <a href="<?= get_base_url() ?>/modules/dashboard/" class="btn btn-outline-secondary px-4 py-2">กลับไปหน้าหลัก</a>
                        <button type="submit" class="btn btn-vintage px-4 py-2"><i class="fas fa-save me-2"></i> บันทึกการแก้ไข (Save)</button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
</div>

<style>
.focus-vintage:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 0.25rem rgba(179, 139, 74, 0.25);
}
.stat-animated { animation: statPulse 0.3s ease; }
@keyframes statPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>
