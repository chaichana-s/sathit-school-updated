<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(); // Ensure only logged-in users can access API
header('Content-Type: application/json');

try {
    // Fetch live statistics
    $stats = [
        'teachers' => $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
        'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'subjects' => $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
        'attendance_rate' => 100.0 // Default
    ];

    // Calculate attendance rate (Present+Late / Total * 100)
    $attTotals = $pdo->query("
        SELECT SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_count,
               COUNT(*) as total_count
        FROM attendance
        WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ")->fetch(PDO::FETCH_ASSOC);

    if ($attTotals && $attTotals['total_count'] > 0) {
        $stats['attendance_rate'] = round(($attTotals['present_count'] / $attTotals['total_count']) * 100, 1);
    }

    // Prepare Chart Data (Last 5 days attendance)
    $chartQuery = $pdo->query("
        SELECT attendance_date,
               SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_count,
               COUNT(*) as total_count
        FROM attendance
        GROUP BY attendance_date
        ORDER BY attendance_date DESC
        LIMIT 5
    ");
    $chartData = array_reverse($chartQuery->fetchAll(PDO::FETCH_ASSOC));

    $attLabels = [];
    $attRates = [];
    foreach($chartData as $row) {
        // English to Thai days
        $daysTh = ['Sun'=>'อา.', 'Mon'=>'จ.', 'Tue'=>'อ.', 'Wed'=>'พ.', 'Thu'=>'พฤ.', 'Fri'=>'ศ.', 'Sat'=>'ส.'];
        $day = date('D', strtotime($row['attendance_date']));
        $dateFmt = date('d/m', strtotime($row['attendance_date']));
        $attLabels[] = $daysTh[$day] . ' ' . $dateFmt;
        $attRates[] = $row['total_count'] > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
    }

    // Grade Distribution Data 
    $gradeQuery = $pdo->query("
        SELECT grade, COUNT(*) as count 
        FROM grades 
        GROUP BY grade 
        ORDER BY grade DESC
    ");
    $gradeDist = $gradeQuery->fetchAll(PDO::FETCH_ASSOC);
    $gradeLabels = [];
    $gradeCounts = [];
    
    // We expect grades 4, 3, 2, 1, 0
    $gradeMap = ['4.00' => 0, '3.00' => 0, '2.00' => 0, '1.00' => 0, '0.00' => 0];
    foreach($gradeDist as $row) {
        $g = number_format($row['grade'], 2);
        if(isset($gradeMap[$g])) {
            $gradeMap[$g] = (int)$row['count'];
        }
    }
    
    foreach($gradeMap as $gLabel => $gCount) {
        $gradeLabels[] = 'Grade ' . rtrim(rtrim($gLabel, '0'), '.');
        $gradeCounts[] = $gCount;
    }

    // Today's attendance summary
    $today = date('Y-m-d');
    $todaySummaryQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance WHERE attendance_date = ?
    ");
    $todaySummaryQuery->execute([$today]);
    $todayAtt = $todaySummaryQuery->fetch(PDO::FETCH_ASSOC);

    // Recent activities
    $attRecent = $pdo->query("
        SELECT a.attendance_date, a.status, a.recorded_by,
               s.first_name as student_fname, s.last_name as student_lname,
               c.class_name, sub.subject_name,
               u.username as recorder_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN schedules sch ON a.schedule_id = sch.id
        JOIN classes c ON sch.class_id = c.id
        JOIN subjects sub ON sch.subject_id = sub.id
        LEFT JOIN users u ON a.recorded_by = u.id
        ORDER BY a.updated_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentActivities = [];
    foreach ($attRecent as $act) {
        $statusTh = ['Present'=>'มาเรียน','Absent'=>'ขาดเรียน','Late'=>'สาย','Leave'=>'ลา'];
        $statusClass = ['Present'=>'success','Absent'=>'danger','Late'=>'warning','Leave'=>'info'];
        $statusIcon = ['Present'=>'check-circle','Absent'=>'times-circle','Late'=>'clock','Leave'=>'envelope-open-text'];
        $st = $act['status'];
        $recentActivities[] = [
            'icon' => $statusIcon[$st] ?? 'info-circle',
            'color' => $statusClass[$st] ?? 'primary',
            'text' => "บันทึกเวลาเรียน {$act['student_fname']} {$act['student_lname']} ({$act['class_name']}) วิชา{$act['subject_name']} - {$statusTh[$st]}",
            'time' => date('d/m/Y', strtotime($act['attendance_date'])),
            'recorder' => $act['recorder_name'] ?? 'ระบบ'
        ];
    }

    echo json_encode([
        'success' => true,
        'today_summary' => [
            'total' => (int)($todayAtt['total'] ?? 0),
            'present' => (int)($todayAtt['present'] ?? 0),
            'absent' => (int)($todayAtt['absent'] ?? 0),
            'late' => (int)($todayAtt['late'] ?? 0)
        ],
        'recent_activities' => $recentActivities,
        'stats' => [
            'teachers' => number_format($stats['teachers']),
            'students' => number_format($stats['students']),
            'subjects' => number_format($stats['subjects']),
            'attendance_rate' => $stats['attendance_rate']
        ],
        'charts' => [
            'attendance' => [
                'labels' => $attLabels,
                'data' => $attRates
            ],
            'grades' => [
                'labels' => $gradeLabels,
                'data' => $gradeCounts
            ]
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
