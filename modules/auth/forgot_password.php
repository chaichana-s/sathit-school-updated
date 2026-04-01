<?php
require_once __DIR__ . '/../../includes/auth.php';

$base_url = get_base_url();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = $_POST['email_or_username'] ?? '';
    
    if (empty($email_or_username)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $message = "ระบบได้ส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปยังอีเมลที่เชื่อมโยงกับบัญชีของคุณแล้ว (หากมีข้อมูลในระบบ)";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน | สาธิตวิทยา (Forgot Password)</title>
    
    <link rel="icon" type="image/png" href="<?= $base_url ?>/images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --vintage-bg: #f4f7fb;
            --vintage-dark: #0f172a;
            --vintage-primary: #4f46e5;
            --gold: #0284c7;
        }
        
        body {
            background-color: var(--vintage-bg);
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            border: 1px solid #edebe4;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            text-align: center;
        }

        .school-crest img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        h3 {
            font-family: 'Playfair Display', serif;
            color: var(--vintage-dark);
            margin-bottom: 30px;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 0.25rem rgba(2, 132, 199, 0.25);
        }

        .btn-vintage {
            background: linear-gradient(135deg, var(--vintage-primary) 0%, #6366f1 100%);
            color: #fff;
            border: none;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        }

        .btn-vintage:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            color: #fff;
        }

        .text-gold { color: var(--gold); }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="auth-card">
            
            <div class="school-crest">
                <img src="<?= $base_url ?>/images/favicon.png" alt="Satitwittaya Crest">
            </div>
            
            <h3>ลืมรหัสผ่าน<br><span style="font-size: 1rem; color: #757368; font-family: 'Inter', sans-serif;">Reset Password</span></h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <a href="login.php" class="btn btn-outline-secondary w-100 rounded-pill mt-3">กลับสู่หน้าเข้าสู่ระบบ</a>
            <?php else: ?>
                <p class="text-muted small mb-4">กรุณากรอกชื่อผู้ใช้งาน หรืออีเมลที่ลงทะเบียนไว้ ระบบจะส่งคำแนะนำในการตั้งรหัสผ่านใหม่ให้ท่าน</p>
                
                <form method="POST" action="">
                    <div class="form-floating mb-4 text-start">
                        <input type="text" class="form-control" id="email_or_username" name="email_or_username" placeholder="Username or Email" required autofocus>
                        <label for="email_or_username"><i class="fas fa-user text-gold me-2"></i> รหัสประจำตัว / อีเมล</label>
                    </div>

                    <button class="btn btn-vintage w-100 rounded-pill mb-3" type="submit">
                        <i class="fas fa-paper-plane me-2"></i> ส่งลิงก์ตั้งรหัสผ่านใหม่
                    </button>
                    
                    <a href="login.php" class="text-muted small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> กลับสู่หน้าเข้าสู่ระบบ</a>
                </form>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
