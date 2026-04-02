<?php
require_once __DIR__ . '/config.php';

echo "<h2>Database Setup & Mock Data Generation</h2>";

try {
    // 1. Create DataBase if it doesn't exist (Only works if we caught 1049 and connected without dbname in config.php)
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db_name}`");
    echo "Database `{$db_name}` created and selected.<br>";

    // 2. Create Tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_name VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS classrooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_code VARCHAR(20) NOT NULL,
            room_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_code VARCHAR(20) NOT NULL,
            subject_name VARCHAR(100) NOT NULL,
            credits DECIMAL(3,1) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prefix VARCHAR(20),
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            department_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_code VARCHAR(20) UNIQUE NOT NULL,
            prefix VARCHAR(20),
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            dob DATE,
            class_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('Admin', 'Teacher', 'Student') NOT NULL,
            reference_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            teacher_id INT NOT NULL,
            classroom_id INT NOT NULL,
            day_of_week VARCHAR(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            schedule_id INT NOT NULL,
            student_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            status ENUM('Present', 'Absent', 'Late', 'Leave') NOT NULL DEFAULT 'Present',
            recorded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance (schedule_id, student_id, attendance_date)
        )",
        "CREATE TABLE IF NOT EXISTS grades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            class_id INT NOT NULL,
            teacher_id INT NOT NULL,
            raw_score DECIMAL(5,2) DEFAULT NULL,
            grade DECIMAL(3,2) DEFAULT NULL,
            academic_year VARCHAR(4) NOT NULL DEFAULT '2569',
            term VARCHAR(1) NOT NULL DEFAULT '1',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_grade (student_id, subject_id, class_id, academic_year, term)
        )",
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value VARCHAR(255) NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    echo "Tables created successfully.<br>";

    // 3. Clear existing data to prevent duplicates on re-run
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE settings");
    $pdo->exec("TRUNCATE TABLE grades");
    $pdo->exec("TRUNCATE TABLE attendance");
    $pdo->exec("TRUNCATE TABLE schedules");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("TRUNCATE TABLE students");
    $pdo->exec("TRUNCATE TABLE teachers");
    $pdo->exec("TRUNCATE TABLE classes");
    $pdo->exec("TRUNCATE TABLE classrooms");
    $pdo->exec("TRUNCATE TABLE subjects");
    $pdo->exec("TRUNCATE TABLE departments");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Cleaned old data.<br>";

    // 4. Generate Classes (5 classes)
    $classNames = ['ม.1/1', 'ม.2/1', 'ม.3/1', 'ม.4/1', 'ม.5/1', 'ม.6/1'];
    $stmtClass = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
    foreach ($classNames as $cName) {
        $stmtClass->execute([$cName]);
    }
    echo "Generated 5 classes.<br>";

    // 4.1 Generate Classrooms
    $roomData = [
        ['R401', 'ห้องเรียน 401'],
        ['R402', 'ห้องเรียน 402'],
        ['S101', 'วิทยาศาสตร์ 1'],
        ['C201', 'คอมพิวเตอร์ 1'],
        ['A301', 'ศิลปะ 1']
    ];
    $stmtRoom = $pdo->prepare("INSERT INTO classrooms (room_code, room_name) VALUES (?, ?)");
    foreach ($roomData as $r) {
        $stmtRoom->execute($r);
    }
    echo "Generated 5 classrooms.<br>";

    // 5. Generate Departments and Subjects for Context
    $pdo->exec("INSERT INTO departments (department_name) VALUES ('หมวดวิชาวิทยาศาสตร์'), ('หมวดวิชาคณิตศาสตร์'), ('หมวดวิชาภาษาไทย')");
    $pdo->exec("INSERT INTO subjects (subject_code, subject_name, credits) VALUES ('ว21101', 'วิทยาศาสตร์พื้นฐาน', 1.5), ('ค21101', 'คณิตศาสตร์พื้นฐาน', 1.5)");
    
    // Default Password Hash for mock users ('password123')
    $defaultPasswordHash = password_hash('password123', PASSWORD_DEFAULT);

    // 6. Generate Admin User
    $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'Admin')");
    $stmtUser->execute(['admin', $defaultPasswordHash]);

    // 7. Generate 10 Teachers & their user accounts
    $firstNames = ['สมชาย', 'สมศรี', 'วิชัย', 'มานะ', 'ปิติ', 'ชูใจ', 'วีระ', 'เพ็ญศรี', 'จินตนา', 'สุชาติ'];
    $lastNames = ['รักดี', 'ตั้งใจเรียน', 'มีทรัพย์', 'ใจบุญ', 'พากเพียร', 'สว่างวงศ์', 'ทองดี', 'เจริญผล', 'สุดสวาท', 'กล้าหาญ'];
    
    $stmtTeacher = $pdo->prepare("INSERT INTO teachers (prefix, first_name, last_name, phone, department_id) VALUES (?, ?, ?, ?, ?)");
    $stmtUserRef = $pdo->prepare("INSERT INTO users (username, password_hash, role, reference_id) VALUES (?, ?, ?, ?)");
    
    for ($i = 0; $i < 10; $i++) {
        $fname = $firstNames[array_rand($firstNames)];
        $lname = $lastNames[array_rand($lastNames)];
        $phone = '08' . rand(10000000, 99999999);
        $deptId = rand(1, 3);
        $tPrefixes = ['นาย', 'นาง', 'นางสาว'];
        $tPrefix = $tPrefixes[array_rand($tPrefixes)];
        
        $stmtTeacher->execute([$tPrefix, $fname, $lname, $phone, $deptId]);
        $teacherId = $pdo->lastInsertId();
        
        // Generate User Account for Teacher
        $username = 'teacher' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        $stmtUserRef->execute([$username, $defaultPasswordHash, 'Teacher', $teacherId]);
    }
    echo "Generated 10 Teachers and User accounts ('teacher01' - 'teacher10').<br>";

    // 8. Generate 100 Students & their user accounts
    $stmtStudent = $pdo->prepare("INSERT INTO students (student_code, prefix, first_name, last_name, dob, class_id) VALUES (?, ?, ?, ?, ?, ?)");
    
    for ($i = 0; $i < 100; $i++) {
        $studentCode = '69' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
        $fname = $firstNames[array_rand($firstNames)];
        $lname = $lastNames[array_rand($lastNames)];
        
        $startTimestamp = strtotime("2008-01-01");
        $endTimestamp = strtotime("2012-12-31");
        $randomTimestamp = mt_rand($startTimestamp, $endTimestamp);
        $dob = date("Y-m-d", $randomTimestamp);
        
        $classId = rand(1, 6); // 6 classes available
        $sPrefixes = ['ด.ช.', 'ด.ญ.', 'นาย', 'นางสาว'];
        $sPrefix = $sPrefixes[array_rand($sPrefixes)];
        
        $stmtStudent->execute([$studentCode, $sPrefix, $fname, $lname, $dob, $classId]);
        $studentId = $pdo->lastInsertId();
        
        // Generate User Account for Student
        $username = 'std' . $studentCode;
        $stmtUserRef->execute([$username, $defaultPasswordHash, 'Student', $studentId]);
    }
    echo "Generated 100 Students and User accounts (e.g., 'std690001').<br>";

    // 9. Generate Mock Schedules
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $stmtSchedule = $pdo->prepare("INSERT INTO schedules (class_id, subject_id, teacher_id, classroom_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Assign some schedules for class_id = 1 (ม.1/1) as an example
    // Subject 1: ว21101 (id 1) by Teacher 1 in Classroom 1 (R401)
    $stmtSchedule->execute([1, 1, 1, 1, 'Monday', '08:30:00', '09:20:00']);
    $stmtSchedule->execute([1, 1, 1, 1, 'Monday', '09:20:00', '10:10:00']);
    
    // Subject 2: ค21101 (id 2) by Teacher 2 in Classroom 2 (R402)
    $stmtSchedule->execute([1, 2, 2, 2, 'Tuesday', '10:30:00', '11:20:00']);
    $stmtSchedule->execute([1, 2, 2, 2, 'Tuesday', '11:20:00', '12:10:00']);
    
    echo "Generated mock schedule data.<br>";

    // 10. Generate Mock Attendance
    $stmtAttendance = $pdo->prepare("INSERT INTO attendance (schedule_id, student_id, attendance_date, status, recorded_by) VALUES (?, ?, ?, ?, ?)");
    
    // For Class 1 (ม.1/1) Students, let's look up some IDs (assuming IDs 1-20 are in Class 1)
    $stmtStudentsClass1 = $pdo->query("SELECT id FROM students WHERE class_id = 1 LIMIT 20");
    $class1Students = $stmtStudentsClass1->fetchAll(PDO::FETCH_COLUMN);

    if ($class1Students) {
        $recordedByUserId = 2; 

        for ($i = 0; $i < 7; $i++) {
            $currentDate = date('Y-m-d', strtotime("-$i days"));
            $dayOfWeek = date('w', strtotime($currentDate));
            if ($dayOfWeek == 0 || $dayOfWeek == 6) continue; // Skip weekends
            
            foreach ($class1Students as $student_id) {
                $statuses = ['Present', 'Present', 'Present', 'Present', 'Late', 'Absent', 'Leave'];
                $randStatus = $statuses[array_rand($statuses)];
                $stmtAttendance->execute([1, $student_id, $currentDate, $randStatus, $recordedByUserId]);
                $stmtAttendance->execute([2, $student_id, $currentDate, $randStatus, $recordedByUserId]); // another period
            }
        }
        echo "Generated mock attendance data for the past 7 days.<br>";
    }

    // 11. Generate Mock Grades
    $stmtGrade = $pdo->prepare("INSERT INTO grades (student_id, subject_id, class_id, teacher_id, raw_score, grade, academic_year, term) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // For Class 1, let's grade them in Subject 1 (Science) by Teacher 1
    if ($class1Students) {
        foreach ($class1Students as $student_id) {
            $raw_score = rand(40, 100);
            $grade = 0;
            if ($raw_score >= 80) $grade = 4.0;
            elseif ($raw_score >= 70) $grade = 3.0;
            elseif ($raw_score >= 60) $grade = 2.0;
            elseif ($raw_score >= 50) $grade = 1.0;
            else $grade = 0.0;
            
            $stmtGrade->execute([$student_id, 1, 1, 1, $raw_score, $grade, '2569', '1']);
        }
        echo "Generated mock grades for Class 1 in Subject 1.<br>";
    }

    // 12. Generate Default Settings
    $settings = [
        ['academic_year', '2569', 'ปีการศึกษาปัจจุบัน'],
        ['academic_term', '1', 'ภาคเรียนปัจจุบัน'],
        ['school_name', 'โรงเรียนสาธิตวิทยา', 'ชื่อโรงเรียน']
    ];
    $stmtSettings = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    foreach ($settings as $setting) {
        $stmtSettings->execute($setting);
    }
    echo "Generated default system settings.<br>";

    echo "<h3>Setup Complete! Default passwords for all mock accounts is 'password123'. Admin username is 'admin'.</h3>";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
