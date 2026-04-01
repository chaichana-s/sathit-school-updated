<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$allowed_types = ['students', 'teachers', 'attendance', 'grades'];
$allowed_formats = ['csv', 'json'];

if (!in_array($type, $allowed_types) || !in_array($format, $allowed_formats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report type or format']);
    exit;
}

$date = date('Y-m-d');
$filename = "{$type}-{$date}";

try {
    if ($type === 'students') {
        $rows = $pdo->query("
            SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name, s.dob, c.class_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            ORDER BY c.class_name, s.student_code
        ")->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['ID', 'รหัสนักเรียน', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'วันเกิด', 'ชั้นเรียน'];
        $keys = ['id', 'student_code', 'prefix', 'first_name', 'last_name', 'dob', 'class_name'];

    } elseif ($type === 'teachers') {
        $rows = $pdo->query("
            SELECT t.id, t.prefix, t.first_name, t.last_name, t.phone, COALESCE(d.department_name, '-') as department_name
            FROM teachers t
            LEFT JOIN departments d ON t.department_id = d.id
            ORDER BY t.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['ID', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เบอร์โทร', 'แผนก'];
        $keys = ['id', 'prefix', 'first_name', 'last_name', 'phone', 'department_name'];

    } elseif ($type === 'attendance') {
        $rows = $pdo->query("
            SELECT 
                a.attendance_date,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                COUNT(*) as total,
                ROUND(SUM(CASE WHEN a.status IN ('Present','Late') THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as rate
            FROM attendance a
            GROUP BY a.attendance_date
            ORDER BY a.attendance_date DESC
            LIMIT 30
        ")->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['วันที่', 'มาเรียน', 'สาย', 'ขาด', 'ทั้งหมด', 'อัตราเข้าเรียน (%)'];
        $keys = ['attendance_date', 'present', 'late', 'absent', 'total', 'rate'];

    } elseif ($type === 'grades') {
        $rows = $pdo->query("
            SELECT s.student_code, s.first_name, s.last_name, c.class_name, sub.subject_name, g.score, g.grade
            FROM grades g
            JOIN students s ON g.student_id = s.id
            JOIN classes c ON s.class_id = c.id
            JOIN subjects sub ON g.subject_id = sub.id
            ORDER BY c.class_name, s.student_code, sub.subject_name
        ")->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['รหัสนักเรียน', 'ชื่อ', 'นามสกุล', 'ชั้นเรียน', 'วิชา', 'คะแนน', 'เกรด'];
        $keys = ['student_code', 'first_name', 'last_name', 'class_name', 'subject_name', 'score', 'grade'];
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel Thai support
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                $line[] = $row[$k] ?? '';
            }
            fputcsv($output, $line);
        }
        fclose($output);

    } else {
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $reportNames = [
            'students' => 'รายชื่อนักเรียน',
            'teachers' => 'รายชื่อบุคลากร',
            'attendance' => 'สรุปเวลาเรียน',
            'grades' => 'สรุปผลการเรียน'
        ];

        echo json_encode([
            'report' => $reportNames[$type],
            'generated' => $date,
            'total_records' => count($rows),
            'data' => $rows
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
