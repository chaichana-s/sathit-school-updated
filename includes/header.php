<?php $base_url = get_base_url(); ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สาธิตวิทยา - ระบบจัดการโรงเรียน (Satitwittaya School Management)</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= $base_url ?>/images/favicon.png">
    
    <!-- Google Fonts: Outfit for sleek modern headings, Inter for clean modern UI body -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css">
</head>
<body>

<!-- Inject base URL for JavaScript -->
<script>var APP_BASE = '<?= $base_url ?>';</script>

<div class="wrapper">
    <!-- Sidebar Included Here by PHP -->
    <?php include APP_ROOT . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div id="content" class="content-wrapper">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg top-navbar mb-4">
            <div class="container-fluid">
                <!-- Sidebar Toggle Button -->
                <button type="button" id="sidebarCollapse" class="btn btn-vintage">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- School Name / Brand in header -> hidden on small screens -->
                <a class="navbar-brand ms-3 d-none d-md-block" href="#">
                    <h4 class="mb-0 text-vintage-primary"><i class="fas fa-university me-2"></i> ระบบจัดการโรงเรียน สาธิตวิทยา</h4>
                </a>

                <div class="collapse navbar-collapse d-flex justify-content-end" id="navbarSupportedContent">
                    <ul class="nav navbar-nav ms-auto text-end">
                        <li class="nav-item me-3 d-flex align-items-center">
                            <span class="badge bg-custom-gold text-dark rounded-pill px-3 py-2">
                                <i class="fas fa-user-shield me-1"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Guest'); ?>
                            </span>
                        </li>
                        <li class="nav-item dropdown dropdown-profile flex-shrink-0">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php $displayName = $_SESSION['username'] ?? 'User'; ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($displayName); ?>&background=8b4513&color=fff&rounded=true" alt="Profile" class="profile-img">
                                <span class="ms-2 fw-medium text-dark d-none d-sm-inline"><?php echo htmlspecialchars($displayName); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?= $base_url ?>/modules/auth/profile.php"><i class="fas fa-user-circle me-2"></i> โปรไฟล์</a></li>
                                <li><a class="dropdown-item" href="<?= $base_url ?>/modules/auth/change_password.php"><i class="fas fa-key me-2"></i> เปลี่ยนรหัสผ่าน</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= $base_url ?>/modules/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Main Content Inner -->
        <div class="container-fluid px-md-4 main-inner">
