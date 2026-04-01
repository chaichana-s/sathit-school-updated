<?php
require_once __DIR__ . '/../../includes/auth.php';

// Require login to change password
require_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($new_password) < 8) {
        $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } else {
        // Verify current password first
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Update password using secure hashing
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            if ($updateStmt->execute(['hash' => $new_hash, 'id' => $_SESSION['user_id']])) {
                $message = "เปลี่ยนรหัสผ่านสำเร็จ";
            } else {
                $error = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน โปรดลองอีกครั้ง";
            }
        } else {
            $error = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        }
    }
}
?>

<?php include APP_ROOT . '/includes/header.php'; ?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card vintage-card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom px-4 pt-4 pb-3">
                 <h5 class="mb-0 text-vintage-heading"><i class="fas fa-key me-2 text-gold"></i> เปลี่ยนรหัสผ่าน (Change Password)</h5>
            </div>
            <div class="card-body p-4">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="change_password.php">
                    <div class="mb-3">
                        <label for="current_password" class="form-label text-muted">รหัสผ่านปัจจุบัน</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label text-muted">รหัสผ่านใหม่ (อักขระ 8 ตัวขึ้นไป)</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label text-muted">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= get_base_url() ?>/modules/dashboard/" class="btn btn-outline-secondary">ยกเลิก</a>
                        <button type="submit" class="btn btn-vintage"><i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
