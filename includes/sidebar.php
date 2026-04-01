<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$master_data_pages = ['teachers.php', 'students.php', 'subjects.php', 'classes.php', 'classrooms.php'];
$is_master_data_active = in_array($current_page, $master_data_pages) && $current_dir === 'master_data';
$base_url = get_base_url();
?>
<!-- Sidebar -->
<nav id="sidebar" class="sidebar-vintage">
    <div class="sidebar-header text-center">
        <!-- Modern Logo -->
        <div class="school-crest mb-3 d-flex justify-content-center">
            <div class="rounded-circle bg-white p-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                <img src="<?= $base_url ?>/images/favicon.png" alt="Satitwittaya Crest" style="width: 50px; height: 50px; border-radius: 50%;">
            </div>
        </div>
        <h3 class="brand-title">สาธิตวิทยา</h3>
        <p class="brand-subtitle mb-0">Satitwittaya School</p>
        <p class="est-text mt-1 text-uppercase">Est. 1924</p>
    </div>

    <ul class="list-unstyled components px-3">
        <p class="menu-label">Main Navigation</p>
        <li class="<?= ($current_page == 'index.php' && $current_dir == 'dashboard') ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/modules/dashboard/"><i class="fas fa-tachometer-alt fw-fw"></i> แดชบอร์ด (Dashboard)</a>
        </li>
        <li class="<?= $is_master_data_active ? 'active' : '' ?>">
            <a href="#masterDataMenu" data-bs-toggle="collapse" aria-expanded="<?= $is_master_data_active ? 'true' : 'false' ?>" class="<?= $is_master_data_active ? '' : 'collapsed' ?>">
                <i class="fas fa-database fw-fw"></i> ข้อมูลพื้นฐาน (Master Data)
            </a>
            <ul class="collapse list-unstyled <?= $is_master_data_active ? 'show' : '' ?>" id="masterDataMenu">
                <li><a href="<?= $base_url ?>/modules/master_data/teachers.php" class="<?= ($current_page == 'teachers.php') ? 'text-gold fw-bold' : '' ?>"><i class="fas fa-chalkboard-teacher fw-fw"></i> บุคลากรครู</a></li>
                <li><a href="<?= $base_url ?>/modules/master_data/students.php" class="<?= ($current_page == 'students.php') ? 'text-gold fw-bold' : '' ?>"><i class="fas fa-user-graduate fw-fw"></i> ข้อมูลนักเรียน</a></li>
                <li><a href="<?= $base_url ?>/modules/master_data/subjects.php" class="<?= ($current_page == 'subjects.php') ? 'text-gold fw-bold' : '' ?>"><i class="fas fa-book fw-fw"></i> ข้อมูลรายวิชา</a></li>
                <li><a href="<?= $base_url ?>/modules/master_data/classes.php" class="<?= ($current_page == 'classes.php') ? 'text-gold fw-bold' : '' ?>"><i class="fas fa-layer-group fw-fw"></i> ระดับชั้นเรียน</a></li>
                <li><a href="<?= $base_url ?>/modules/master_data/classrooms.php" class="<?= ($current_page == 'classrooms.php') ? 'text-gold fw-bold' : '' ?>"><i class="fas fa-door-open fw-fw"></i> ห้องเรียน</a></li>
            </ul>
        </li>
        <li class="<?= ($current_page == 'schedule.php') ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/modules/schedule/schedule.php"><i class="fas fa-calendar-alt fw-fw"></i> จัดการตารางเรียน (Schedule)</a>
        </li>
        <li class="<?= ($current_page == 'attendance.php') ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/modules/attendance/attendance.php"><i class="fas fa-clipboard-check fw-fw"></i> เวลาเรียน (Attendance)</a>
        </li>
        <li class="<?= ($current_page == 'grading.php') ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/modules/grading/grading.php"><i class="fas fa-star-half-alt fw-fw"></i> บันทึกคะแนน (Grading)</a>
        </li>
        <p class="menu-label mt-4">System Settings</p>
        <li class="<?= ($current_page == 'rbac.php') ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/modules/rbac/rbac.php"><i class="fas fa-users-cog fw-fw"></i> จัดการผู้ใช้ & สิทธิ์ (RBAC)</a>
        </li>
    </ul>

    <div class="sidebar-footer p-4 text-center">
        <span class="text-muted small">&copy; 1924-2026<br>Satitwittaya Archive</span>
    </div>
</nav>
