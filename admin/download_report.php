<?php
session_start();

require_once '../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// DOMPDF OPTIONS
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// LOGO AS BASE64
$logo_html = '';
$logo_path = $_SERVER['DOCUMENT_ROOT'] . '/grading_system/assets/SFICS-LOGO.jpg';

if (file_exists($logo_path)) {
    $logo_ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));

    $mime_type = 'image/jpeg';
    if ($logo_ext === 'png') {
        $mime_type = 'image/png';
    } elseif ($logo_ext === 'gif') {
        $mime_type = 'image/gif';
    } elseif ($logo_ext === 'jpg' || $logo_ext === 'jpeg') {
        $mime_type = 'image/jpeg';
    }

    $logo_base64 = base64_encode(file_get_contents($logo_path));
    $logo_data = 'data:' . $mime_type . ';base64,' . $logo_base64;

    $logo_html = '<img src="' . $logo_data . '" width="80" style="display:block; margin:0 auto 10px auto;">';
}

// FILTERS
$subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$grading_period = trim($_GET['grading_period'] ?? '');

$allowed_periods = ['Prelim', 'Midterm', 'Prefinal', 'Final'];
if (!in_array($grading_period, $allowed_periods, true)) {
    $grading_period = '';
}

$sql = "
    SELECT 
        s.student_number,
        s.full_name AS student_name,
        sub.subject_code,
        sub.subject_name,
        g.grading_period,

        g.behavior,
        g.attendance,
        g.participation1,
        g.participation2,
        g.participation3,
        g.quiz1,
        g.quiz2,
        g.quiz3,
        g.quiz4,
        g.activity1,
        g.activity2,
        g.activity3,
        g.project,
        g.exam,

        g.behavior_eq,
        g.attendance_eq,
        g.participation_eq,
        g.quiz_eq,
        g.activity_eq,
        g.project_eq,
        g.exam_eq,

        g.computed_grade,
        g.remarks,
        g.is_submitted,
        g.is_locked
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN subjects sub ON g.subject_id = sub.subject_id
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($subject_id > 0) {
    $sql .= " AND g.subject_id = ?";
    $params[] = $subject_id;
    $types .= 'i';
}

if ($grading_period !== '') {
    $sql .= " AND g.grading_period = ?";
    $params[] = $grading_period;
    $types .= 's';
}

$sql .= " ORDER BY sub.subject_name ASC, s.full_name ASC";

$stmt = mysqli_prepare($conn, $sql);
$result = false;

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$filter_text = [];

if ($subject_id > 0) {
    $subject_stmt = mysqli_prepare($conn, "
        SELECT subject_code, subject_name
        FROM subjects
        WHERE subject_id = ?
        LIMIT 1
    ");

    if ($subject_stmt) {
        mysqli_stmt_bind_param($subject_stmt, "i", $subject_id);
        mysqli_stmt_execute($subject_stmt);
        $subject_result = mysqli_stmt_get_result($subject_stmt);
        $subject_row = mysqli_fetch_assoc($subject_result);

        if ($subject_row) {
            $filter_text[] = 'Subject: ' . $subject_row['subject_code'] . ' - ' . $subject_row['subject_name'];
        }

        mysqli_stmt_close($subject_stmt);
    }
}

if ($grading_period !== '') {
    $filter_text[] = 'Grading Period: ' . $grading_period;
}

// HTML
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        .header {
            text-align: center;
            margin-bottom: 14px;
        }

        .header h2 {
            margin: 0;
            font-size: 18px;
        }

        .header h3 {
            margin: 4px 0;
            font-size: 13px;
            font-weight: normal;
        }

        .header p {
            margin: 2px 0;
            font-size: 10px;
        }

        .meta {
            margin-bottom: 12px;
            font-size: 10px;
        }

        .meta p {
            margin: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        th, td {
            border: 1px solid #444;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            font-size: 8px;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .left {
            text-align: left;
        }

        .footer {
            margin-top: 12px;
            text-align: right;
            font-size: 9px;
        }
    </style>
</head>
<body>

    <div class="header">
        ' . $logo_html . '
        <h2>Saint Francis Institute of Computer Studies</h2>
        <h3>Web-Based Student Grading System</h3>
        <p><strong>Student Grade Report</strong></p>
    </div>

    <div class="meta">
        <p><strong>Generated by:</strong> ' . htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') . '</p>
        <p><strong>Date Generated:</strong> ' . date('F d, Y h:i A') . '</p>';

if (!empty($filter_text)) {
    foreach ($filter_text as $filter) {
        $html .= '<p><strong>Filter:</strong> ' . htmlspecialchars($filter) . '</p>';
    }
} else {
    $html .= '<p><strong>Filter:</strong> All records</p>';
}

$html .= '
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Student No.</th>
                <th rowspan="2">Student Name</th>
                <th rowspan="2">Subject</th>
                <th rowspan="2">Period</th>

                <th colspan="2">Behavior</th>
                <th colspan="2">Attendance</th>
                <th colspan="4">Participation</th>
                <th colspan="5">Quizzes</th>
                <th colspan="4">Activities</th>
                <th colspan="2">Project</th>
                <th colspan="2">Exam</th>

                <th rowspan="2">Grade</th>
                <th rowspan="2">Remarks</th>
                <th rowspan="2">Submitted</th>
                <th rowspan="2">Lock</th>
            </tr>
            <tr>
                <th>Raw</th><th>EQ</th>
                <th>Raw</th><th>EQ</th>
                <th>P1</th><th>P2</th><th>P3</th><th>EQ</th>
                <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>EQ</th>
                <th>A1</th><th>A2</th><th>A3</th><th>EQ</th>
                <th>Raw</th><th>EQ</th>
                <th>Raw</th><th>EQ</th>
            </tr>
        </thead>
        <tbody>
';

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars((string) $row['student_number']) . '</td>
                <td class="left">' . htmlspecialchars((string) $row['student_name']) . '</td>
                <td class="left">' . htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']) . '</td>
                <td>' . htmlspecialchars((string) $row['grading_period']) . '</td>

                <td>' . htmlspecialchars((string) $row['behavior']) . '</td>
                <td>' . htmlspecialchars((string) $row['behavior_eq']) . '</td>

                <td>' . htmlspecialchars((string) $row['attendance']) . '</td>
                <td>' . htmlspecialchars((string) $row['attendance_eq']) . '</td>

                <td>' . htmlspecialchars((string) $row['participation1']) . '</td>
                <td>' . htmlspecialchars((string) $row['participation2']) . '</td>
                <td>' . htmlspecialchars((string) $row['participation3']) . '</td>
                <td>' . htmlspecialchars((string) $row['participation_eq']) . '</td>

                <td>' . htmlspecialchars((string) $row['quiz1']) . '</td>
                <td>' . htmlspecialchars((string) $row['quiz2']) . '</td>
                <td>' . htmlspecialchars((string) $row['quiz3']) . '</td>
                <td>' . htmlspecialchars((string) $row['quiz4']) . '</td>
                <td>' . htmlspecialchars((string) $row['quiz_eq']) . '</td>

                <td>' . htmlspecialchars((string) $row['activity1']) . '</td>
                <td>' . htmlspecialchars((string) $row['activity2']) . '</td>
                <td>' . htmlspecialchars((string) $row['activity3']) . '</td>
                <td>' . htmlspecialchars((string) $row['activity_eq']) . '</td>

                <td>' . htmlspecialchars((string) $row['project']) . '</td>
                <td>' . htmlspecialchars((string) $row['project_eq']) . '</td>

                <td>' . htmlspecialchars((string) $row['exam']) . '</td>
                <td>' . htmlspecialchars((string) $row['exam_eq']) . '</td>

                <td><strong>' . htmlspecialchars((string) $row['computed_grade']) . '</strong></td>
                <td>' . htmlspecialchars((string) $row['remarks']) . '</td>
                <td>' . ((int) $row['is_submitted'] === 1 ? 'Yes' : 'No') . '</td>
                <td>' . ((int) $row['is_locked'] === 1 ? 'Locked' : 'Open') . '</td>
            </tr>
        ';
    }
} else {
    $html .= '
        <tr>
            <td colspan="30">No excel-style report records found.</td>
        </tr>
    ';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>End of Report</p>
    </div>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('student_grade_report.pdf', ['Attachment' => true]);

if ($stmt) {
    mysqli_stmt_close($stmt);
}
?>