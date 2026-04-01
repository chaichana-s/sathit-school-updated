<?php
/**
 * Migration: Add more subjects and spread schedules across all classes/days.
 * Run this ONCE.  Safe to re-run (uses INSERT IGNORE / checks).
 */
require_once __DIR__ . '/config.php';

echo "<h2>Migration: Expand Subjects & Schedules</h2>";

try {
    // ─── 1. Add more subjects ───────────────────────────────────────
    $subjects = [
        ['ท21101', 'ภาษาไทยพื้นฐาน', 1.5],
        ['อ21101', 'ภาษาอังกฤษพื้นฐาน', 1.5],
        ['ส21101', 'สังคมศึกษา', 1.0],
        ['พ21101', 'สุขศึกษาและพลศึกษา', 1.0],
        ['ศ21101', 'ศิลปะ', 0.5],
        ['ง21101', 'การงานอาชีพ', 0.5],
    ];
    $stmtSubject = $pdo->prepare("INSERT IGNORE INTO subjects (subject_code, subject_name, credits) VALUES (?, ?, ?)");
    foreach ($subjects as $s) {
        $stmtSubject->execute($s);
    }
    echo "Added " . count($subjects) . " new subjects.<br>";

    // ─── 2. Fetch reference IDs ─────────────────────────────────────
    $allSubjects  = $pdo->query("SELECT id FROM subjects ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $allClasses   = $pdo->query("SELECT id FROM classes ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $allTeachers  = $pdo->query("SELECT id FROM teachers ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $allRooms     = $pdo->query("SELECT id FROM classrooms ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // Time slots for a realistic school day
    $timeSlots = [
        ['08:30:00', '09:20:00'],
        ['09:20:00', '10:10:00'],
        ['10:30:00', '11:20:00'],
        ['11:20:00', '12:10:00'],
        ['13:00:00', '13:50:00'],
        ['13:50:00', '14:40:00'],
    ];

    // ─── 3. Build schedules for every class, every day ──────────────
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE class_id = ? AND day_of_week = ? AND start_time = ?");
    $stmtSchedule = $pdo->prepare("INSERT INTO schedules (class_id, subject_id, teacher_id, classroom_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $inserted = 0;
    foreach ($allClasses as $classId) {
        foreach ($days as $day) {
            // Pick 4 random slots per day per class
            $daySlots = $timeSlots;
            shuffle($daySlots);
            $daySlots = array_slice($daySlots, 0, 4);

            foreach ($daySlots as $slot) {
                // Check if already exists
                $stmtCheck->execute([$classId, $day, $slot[0]]);
                if ($stmtCheck->fetchColumn() > 0) continue;

                $subjectId = $allSubjects[array_rand($allSubjects)];
                $teacherId = $allTeachers[array_rand($allTeachers)];
                $roomId    = $allRooms[array_rand($allRooms)];

                $stmtSchedule->execute([$classId, $subjectId, $teacherId, $roomId, $day, $slot[0], $slot[1]]);
                $inserted++;
            }
        }
    }
    echo "Inserted $inserted new schedule slots.<br>";

    // ─── 4. Seed attendance for today across all new schedules ───────
    $today = date('Y-m-d');
    $dayOfWeek = date('l');

    $todaySchedules = $pdo->prepare("SELECT s.id as schedule_id, s.class_id FROM schedules s WHERE s.day_of_week = ?");
    $todaySchedules->execute([$dayOfWeek]);
    $schedRows = $todaySchedules->fetchAll(PDO::FETCH_ASSOC);

    $stmtAtt = $pdo->prepare("INSERT IGNORE INTO attendance (schedule_id, student_id, attendance_date, status, recorded_by) VALUES (?, ?, ?, ?, ?)");
    $statuses = ['Present', 'Present', 'Present', 'Late', 'Absent', 'Leave'];
    $attInserted = 0;

    // Get admin user id
    $adminUserId = $pdo->query("SELECT id FROM users WHERE role = 'Admin' LIMIT 1")->fetchColumn() ?: 1;

    foreach ($schedRows as $sched) {
        $students = $pdo->prepare("SELECT id FROM students WHERE class_id = ?");
        $students->execute([$sched['class_id']]);
        $studentIds = $students->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($studentIds as $sid) {
            $status = $statuses[array_rand($statuses)];
            $stmtAtt->execute([$sched['schedule_id'], $sid, $today, $status, $adminUserId]);
            $attInserted++;
        }
    }
    echo "Seeded $attInserted attendance records for today ($today, $dayOfWeek).<br>";

    echo "<h3>Migration complete!</h3>";
    echo "<a href='../modules/attendance/attendance.php'>Go to Attendance →</a>";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
