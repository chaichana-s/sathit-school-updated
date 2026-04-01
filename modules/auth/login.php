<?php
require_once __DIR__ . '/../../includes/auth.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . get_base_url() . "/modules/dashboard/");
    exit();
}

$base_url = get_base_url();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน (Please enter username and password)';
    } else {
        if (attempt_login($pdo, $username, $password)) {
            header("Location: " . get_base_url() . "/modules/dashboard/");
            exit();
        } else {
            $error = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง (Invalid username or password)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | สาธิตวิทยา (Login - Satitwittaya)</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= $base_url ?>/images/favicon.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
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

        .login-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            border: 1px solid #edebe4;
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
        }

        .login-brand-panel {
            background-color: var(--vintage-dark);
            color: #fff;
            padding: 40px;
            width: 45%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }
        
        .login-brand-panel::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.4) 0%, rgba(15, 23, 42, 0.9) 100%);
            z-index: 1;
        }

        .login-brand-content {
            z-index: 2;
        }

        .login-brand-panel .school-crest img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(79, 70, 229, 0.3);
            margin-bottom: 20px;
        }

        .login-brand-panel h2 {
            font-family: 'Playfair Display', serif;
            color: var(--gold);
            font-weight: 700;
        }

        .login-form-panel {
            padding: 50px;
            width: 55%;
        }

        .login-form-panel h3 {
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
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.35);
        }

        .form-floating > label {
            color: #757368;
        }

        .text-gold { color: var(--gold); }
        .text-vintage-primary { color: var(--vintage-primary); }
        
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                margin: 20px;
            }
            .login-brand-panel, .login-form-panel {
                width: 100%;
            }
            .login-brand-panel {
                padding: 30px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="login-card">
            
            <!-- Left Branding Panel -->
            <div class="login-brand-panel d-none d-md-flex">
                <div class="login-brand-content">
                    <div class="school-crest">
                        <img src="<?= $base_url ?>/images/favicon.png" alt="Satitwittaya Crest">
                    </div>
                    <h2>สาธิตวิทยา</h2>
                    <p class="mb-0 text-white-50 mt-2" style="letter-spacing: 2px;">EST. 1924</p>
                    <p class="mt-4 px-3" style="font-size: 0.9rem; color: #c9c5ba;">
                        "ระบบการจัดการข้อมูลสถานศึกษาที่สืบทอด<br>เจตนารมณ์แห่งการเรียนรู้กว่าศตวรรษ"
                    </p>
                </div>
            </div>

            <!-- Right Login Form Panel -->
            <div class="login-form-panel">
                <h3 class="text-center">เข้าสู่ระบบ<br><span style="font-size: 1rem; color: #757368; font-family: 'Inter', sans-serif;">Account Login</span></h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-floating mb-4">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                        <label for="username"><i class="fas fa-user text-gold me-2"></i> รหัสประจำตัว / ชื่อผู้ใช้</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock text-gold me-2"></i> รหัสผ่าน</label>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="rememberMe">
                            <label class="form-check-label text-muted small" for="rememberMe">
                                จำการเข้าระบบ
                            </label>
                        </div>
                        <a href="forgot_password.php" class="text-vintage-primary small text-decoration-none">ลืมรหัสผ่าน?</a>
                    </div>

                    <button class="btn btn-vintage w-100 rounded-pill mb-4" type="submit">
                        <i class="fas fa-sign-in-alt me-2"></i> เข้าสู่ระบบ
                    </button>
                    
                    <div class="text-center mt-3">
                        <p class="text-muted small mb-0">มีปัญหาในการเข้าใช้งาน? <br>กรุณาติดต่อ <a href="#" class="text-gold text-decoration-none">ฝ่ายสารสนเทศโรงเรียน</a></p>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
