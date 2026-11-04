<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) or !isset($_SESSION['role']) or $_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit;
}

$professor_id = (int) $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Professor');

// FETCH PENDING UNLOCK REQUESTS PARA SA SIDEBAR BADGE
$pending_requests = 0;
$pr_query = "SELECT COUNT(*) AS total FROM unlock_requests ur JOIN grades g ON ur.grade_id = g.grade_id WHERE g.professor_id = ? AND ur.request_status = 'Pending'";
if ($pr_stmt = mysqli_prepare($conn, $pr_query)) {
    mysqli_stmt_bind_param($pr_stmt, "i", $professor_id);
    mysqli_stmt_execute($pr_stmt);
    $pr_res = mysqli_stmt_get_result($pr_stmt);
    if ($pr_row = mysqli_fetch_assoc($pr_res)) {
        $pending_requests = (int) $pr_row['total'];
    }
    mysqli_stmt_close($pr_stmt);
}

$subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$grading_period = trim($_GET['period'] ?? 'Prelim');

$allowed_periods = ['Prelim', 'Midterm', 'Prefinal', 'Final'];
if (!in_array($grading_period, $allowed_periods, true)) {
    $grading_period = 'Prelim';
}

if ($subject_id <= 0) {
    header("Location: subjects.php");
    exit;
}

$swal_script = ''; 
$clear_local_storage = false; // FLAG PARA BURAHIN ANG RECOVERY PAG NA-SAVE NA

// SUBJECT VALIDATION
$subject_stmt = mysqli_prepare($conn, "
    SELECT subject_id, subject_code, subject_name, year_level, semester
    FROM subjects
    WHERE subject_id = ? AND professor_id = ?
    LIMIT 1
");

$subject = null;
if ($subject_stmt) {
    mysqli_stmt_bind_param($subject_stmt, "ii", $subject_id, $professor_id);
    mysqli_stmt_execute($subject_stmt);
    $subject_result = mysqli_stmt_get_result($subject_stmt);
    $subject = mysqli_fetch_assoc($subject_result);
    mysqli_stmt_close($subject_stmt);
}

if (!$subject) {
    include '../includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> Invalid subject or access denied.</div><a href="subjects.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Subjects</a></div>';
    include '../includes/footer.php';
    exit;
}

// SEQUENTIAL SUBMISSION CHECK (Only for Locking)
$can_submit = true;
$previous_period = '';
if ($grading_period === 'Midterm') $previous_period = 'Prelim';
if ($grading_period === 'Prefinal') $previous_period = 'Midterm';
if ($grading_period === 'Final') $previous_period = 'Prefinal';

if ($previous_period !== '') {
    $check_stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM grades
        WHERE subject_id = ?
        AND grading_period = ?
        AND is_submitted = 1
    ");

    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "is", $subject_id, $previous_period);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_row = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);

        // If the previous period has no submitted grades, disable the Submit & Lock
        if ((int)($check_row['total'] ?? 0) === 0) {
            $can_submit = false;
        }
    }
}

function normalizeScore($value): float {
    if ($value === '' || $value === null || !is_numeric($value)) return 0;
    $num = (float)$value;
    if ($num < 0) $num = 0;
    if ($num > 100) $num = 100;
    return $num;
}

function computePeriodGrade(array $d): array {
    $behavior_eq = round($d['behavior'] * 0.05, 2);
    $attendance_eq = round($d['attendance'] * 0.05, 2);
    $participation_avg = round(($d['participation1'] + $d['participation2'] + $d['participation3']) / 3, 2);
    $participation_eq = round($participation_avg * 0.15, 2);
    $quiz_avg = round(($d['quiz1'] + $d['quiz2'] + $d['quiz3'] + $d['quiz4']) / 4, 2);
    $quiz_eq = round($quiz_avg * 0.20, 2);
    $activity_avg = round(($d['activity1'] + $d['activity2'] + $d['activity3']) / 3, 2);
    $activity_eq = round($activity_avg * 0.10, 2);
    $project_eq = round($d['project'] * 0.05, 2);
    $exam_eq = round($d['exam'] * 0.40, 2);

    $computed_grade = round($behavior_eq + $attendance_eq + $participation_eq + $quiz_eq + $activity_eq + $project_eq + $exam_eq, 2);
    $remarks = $computed_grade >= 75 ? 'PASSED' : 'FAILED';

    return [
        'behavior_eq' => $behavior_eq, 'attendance_eq' => $attendance_eq, 'participation_eq' => $participation_eq,
        'quiz_eq' => $quiz_eq, 'activity_eq' => $activity_eq, 'project_eq' => $project_eq, 'exam_eq' => $exam_eq,
        'computed_grade' => $computed_grade, 'remarks' => $remarks
    ];
}

// UNIFIED SAVE & SUBMIT LOGIC
$is_saving = isset($_POST['save_grades']) || (isset($_POST['submit_grades']) && $_POST['submit_grades'] === '1');
$is_submitting = (isset($_POST['submit_grades']) && $_POST['submit_grades'] === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_saving) {
    $student_ids = $_POST['student_id'] ?? [];
    $saved = 0;

    // 1. SAVE DRAFT LOOP
    foreach ($student_ids as $index => $student_id_raw) {
        $student_id = (int)$student_id_raw;
        if ($student_id <= 0) continue;

        $data = [
            'behavior' => normalizeScore($_POST['behavior'][$index] ?? 0), 'attendance' => normalizeScore($_POST['attendance'][$index] ?? 0),
            'participation1' => normalizeScore($_POST['participation1'][$index] ?? 0), 'participation2' => normalizeScore($_POST['participation2'][$index] ?? 0), 'participation3' => normalizeScore($_POST['participation3'][$index] ?? 0),
            'quiz1' => normalizeScore($_POST['quiz1'][$index] ?? 0), 'quiz2' => normalizeScore($_POST['quiz2'][$index] ?? 0), 'quiz3' => normalizeScore($_POST['quiz3'][$index] ?? 0), 'quiz4' => normalizeScore($_POST['quiz4'][$index] ?? 0),
            'activity1' => normalizeScore($_POST['activity1'][$index] ?? 0), 'activity2' => normalizeScore($_POST['activity2'][$index] ?? 0), 'activity3' => normalizeScore($_POST['activity3'][$index] ?? 0),
            'project' => normalizeScore($_POST['project'][$index] ?? 0), 'exam' => normalizeScore($_POST['exam'][$index] ?? 0)
        ];

        $computed = computePeriodGrade($data);
        $check_stmt = mysqli_prepare($conn, "SELECT grade_id, is_locked FROM grades WHERE student_id = ? AND subject_id = ? AND professor_id = ? AND grading_period = ? LIMIT 1");

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "iiis", $student_id, $subject_id, $professor_id, $grading_period);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $existing = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);

            // Skip if the specific grade is already locked
            if ($existing && (int)$existing['is_locked'] === 1) continue;

            if ($existing) {
                $update_stmt = mysqli_prepare($conn, "UPDATE grades SET behavior = ?, attendance = ?, participation1 = ?, participation2 = ?, participation3 = ?, quiz1 = ?, quiz2 = ?, quiz3 = ?, quiz4 = ?, activity1 = ?, activity2 = ?, activity3 = ?, project = ?, exam = ?, behavior_eq = ?, attendance_eq = ?, participation_eq = ?, quiz_eq = ?, activity_eq = ?, project_eq = ?, exam_eq = ?, computed_grade = ?, remarks = ? WHERE grade_id = ?");
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "ddddddddddddddddddddddsi", $data['behavior'], $data['attendance'], $data['participation1'], $data['participation2'], $data['participation3'], $data['quiz1'], $data['quiz2'], $data['quiz3'], $data['quiz4'], $data['activity1'], $data['activity2'], $data['activity3'], $data['project'], $data['exam'], $computed['behavior_eq'], $computed['attendance_eq'], $computed['participation_eq'], $computed['quiz_eq'], $computed['activity_eq'], $computed['project_eq'], $computed['exam_eq'], $computed['computed_grade'], $computed['remarks'], $existing['grade_id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    $saved++;
                }
            } else {
                $insert_stmt = mysqli_prepare($conn, "INSERT INTO grades (student_id, subject_id, professor_id, grading_period, behavior, attendance, participation1, participation2, participation3, quiz1, quiz2, quiz3, quiz4, activity1, activity2, activity3, project, exam, behavior_eq, attendance_eq, participation_eq, quiz_eq, activity_eq, project_eq, exam_eq, computed_grade, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($insert_stmt) {
                    mysqli_stmt_bind_param($insert_stmt, "iiisdddddddddddddddddddddds", $student_id, $subject_id, $professor_id, $grading_period, $data['behavior'], $data['attendance'], $data['participation1'], $data['participation2'], $data['participation3'], $data['quiz1'], $data['quiz2'], $data['quiz3'], $data['quiz4'], $data['activity1'], $data['activity2'], $data['activity3'], $data['project'], $data['exam'], $computed['behavior_eq'], $computed['attendance_eq'], $computed['participation_eq'], $computed['quiz_eq'], $computed['activity_eq'], $computed['project_eq'], $computed['exam_eq'], $computed['computed_grade'], $computed['remarks']);
                    mysqli_stmt_execute($insert_stmt);
                    mysqli_stmt_close($insert_stmt);
                    $saved++;
                }
            }
        }
    }

    // 2. LOCK LOGIC (If user clicked Submit & Lock)
    if ($is_submitting) {
        if ($can_submit) {
            $submit_stmt = mysqli_prepare($conn, "UPDATE grades SET is_submitted = 1, is_locked = 1, submitted_at = NOW() WHERE subject_id = ? AND professor_id = ? AND grading_period = ? AND (is_locked = 0 OR is_locked IS NULL)");
            if ($submit_stmt) {
                mysqli_stmt_bind_param($submit_stmt, "iis", $subject_id, $professor_id, $grading_period);
                mysqli_stmt_execute($submit_stmt);
                $affected = mysqli_stmt_affected_rows($submit_stmt);
                mysqli_stmt_close($submit_stmt);
                
                if ($affected > 0 || $saved > 0) {
                    $clear_local_storage = true; // Clear session recovery
                    $swal_script = "Swal.fire({ icon: 'success', title: 'Submitted & Locked!', text: 'Grades have been saved and officially locked.', showConfirmButton: false, timer: 2500 });";
                } else {
                    $swal_script = "Swal.fire({ icon: 'warning', title: 'No Records', text: 'No unlocked records found to submit.' });";
                }
            }
        } else {
            // Safety fallback if they bypass JS
            $swal_script = "Swal.fire({ icon: 'error', title: 'Action Denied', text: 'You must submit and lock the {$previous_period} period first.' });";
        }
    } else {
        // Simple Save Draft Response
        if ($saved > 0) {
            $clear_local_storage = true; // Clear session recovery
            $swal_script = "Swal.fire({ icon: 'success', title: 'Draft Saved!', text: 'Grades have been saved successfully. You can still edit them later.', showConfirmButton: false, timer: 2000 });";
        } else {
            $swal_script = "Swal.fire({ icon: 'info', title: 'No Changes', text: 'No new grades were saved.' });";
        }
    }
}

// LOAD STUDENTS
$students_stmt = mysqli_prepare($conn, "SELECT s.student_id, s.student_number, s.full_name, g.grade_id, g.behavior, g.attendance, g.participation1, g.participation2, g.participation3, g.quiz1, g.quiz2, g.quiz3, g.quiz4, g.activity1, g.activity2, g.activity3, g.project, g.exam, g.behavior_eq, g.attendance_eq, g.participation_eq, g.quiz_eq, g.activity_eq, g.project_eq, g.exam_eq, g.computed_grade, g.remarks, g.is_locked FROM student_subjects ss INNER JOIN students s ON ss.student_id = s.student_id LEFT JOIN grades g ON g.student_id = s.student_id AND g.subject_id = ? AND g.professor_id = ? AND g.grading_period = ? WHERE ss.subject_id = ? ORDER BY s.full_name ASC");
$students = false;
if ($students_stmt) {
    mysqli_stmt_bind_param($students_stmt, "iisi", $subject_id, $professor_id, $grading_period, $subject_id);
    mysqli_stmt_execute($students_stmt);
    $students = mysqli_stmt_get_result($students_stmt);
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* CONTENT & TOP NAVBAR (SAAS STYLE) */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }

    /* CARDS */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; }
    
    /* FORM DROPDOWNS */
    .form-select-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 10px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; cursor: pointer;}
    .form-select-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }

    /* EXCEL-LIKE TABLE W/ SAAS TWEAKS */
    .table-responsive { max-height: 60vh; overflow-y: auto; overflow-x: auto; border-radius: 8px; border: 1px solid #E2E8F0; }
    .excel-table { border-collapse: separate; border-spacing: 0; min-width: 1500px; width: 100%; }
    
    .excel-table th {
        background-color: #F8FAFC;
        color: #64748B;
        font-weight: 600;
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
        font-size: 11.5px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        position: sticky;
        top: 0;
        z-index: 10;
        border-bottom: 2px solid #E2E8F0;
        border-right: 1px solid #E2E8F0;
        padding: 10px;
    }

    .excel-table td {
        white-space: nowrap;
        vertical-align: middle;
        font-size: 13px;
        color: #334155;
        font-weight: 500;
        border-bottom: 1px solid #F1F5F9;
        border-right: 1px solid #F1F5F9;
        padding: 6px 12px;
    }

    /* THE STICKY COLUMNS */
    .sticky-col-1 { position: sticky; left: 0; background: #ffffff; z-index: 5; min-width: 40px; max-width: 40px; text-align: center; border-right: 1px solid #E2E8F0 !important; }
    .sticky-col-2 { position: sticky; left: 40px; background: #ffffff; z-index: 5; min-width: 100px; max-width: 100px; text-align: center; border-right: 1px solid #E2E8F0 !important; }
    .sticky-col-3 { position: sticky; left: 140px; background: #ffffff; z-index: 5; min-width: 250px; max-width: 250px; border-right: 2px solid #E2E8F0 !important; box-shadow: 2px 0 5px rgba(0,0,0,0.03); }
    .excel-table th.sticky-col-1, .excel-table th.sticky-col-2, .excel-table th.sticky-col-3 { z-index: 15; background-color: #F8FAFC; }

    /* INPUTS & CELLS */
    .score-input { width: 70px; min-width: 70px; text-align: center; font-size: 13px; padding: 6px; border: 1px solid #E2E8F0; border-radius: 6px; transition: 0.2s; background-color: #ffffff; color: #0F172A; }
    .score-input:focus { border-color: #0047FF; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    .score-input:disabled { background-color: #F1F5F9; color: #94A3B8; cursor: not-allowed; border-color: #E2E8F0; }
    
    .badge-modern { padding: 6px 10px; font-weight: 600; letter-spacing: 0.5px; border-radius: 6px; font-size: 11px; }
    
    /* BUTTONS */
    .btn-create { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 20px; border: none; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); display: flex; align-items: center; justify-content: center;}
    .btn-create:hover { background-color: #0036cc; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    
    .btn-save { background-color: #F8FAFC; color: #0047FF; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 600; font-size: 14px; padding: 8px 24px; transition: 0.2s; }
    .btn-save:hover { background-color: #EFF6FF; color: #0047FF; border-color: #0047FF; }
    
    .btn-submit { background-color: #10B981; color: white; border-radius: 8px; font-weight: 600; font-size: 14px; padding: 8px 24px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
    .btn-submit:hover { background-color: #059669; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(16, 185, 129, 0.3); }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> My Subjects <span class="mx-1">></span> <strong class="text-dark">Encode Grades</strong>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name; ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Professor</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Grade Encoding</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;"><i class="fas fa-book text-primary me-1"></i> <?= htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?></p>
                    </div>
                </div>

                <div class="saas-card p-3">
                    <form method="GET" id="loadPeriodForm" class="row g-3 align-items-end m-0">
                        <input type="hidden" name="subject_id" value="<?= (int)$subject_id; ?>">
                        <div class="col-md-4">
                            <label class="text-muted mb-1 fw-bold" style="font-size: 11px; text-transform: uppercase;">Grading Period</label>
                            <select name="period" class="form-select-custom w-100">
                                <option value="Prelim" <?= $grading_period === 'Prelim' ? 'selected' : ''; ?>>Prelim</option>
                                <option value="Midterm" <?= $grading_period === 'Midterm' ? 'selected' : ''; ?>>Midterm</option>
                                <option value="Prefinal" <?= $grading_period === 'Prefinal' ? 'selected' : ''; ?>>Prefinal</option>
                                <option value="Final" <?= $grading_period === 'Final' ? 'selected' : ''; ?>>Final</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" id="loadBtn" class="btn-create w-100">
                                <i class="fas fa-sync-alt me-2" id="loadIcon"></i> Load Period
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!$can_submit): ?>
                    <div class="alert alert-warning shadow-sm border-0 border-start border-4 border-warning p-3 mb-4" style="border-radius: 12px; background-color: #FFFBEB;">
                        <div class="d-flex">
                            <i class="fas fa-info-circle text-warning fs-4 me-3 mt-1"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Submit & Lock is Disabled</h6>
                                <span class="text-muted" style="font-size: 13.5px;">You can encode and <strong>Save Drafts</strong> for the <?= $grading_period ?> period right now, but you cannot <strong>Submit & Lock</strong> until the <strong><?= $previous_period ?></strong> period is fully submitted.</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="gradesForm">
                    <div class="saas-card p-0 overflow-hidden">
                        <div class="table-responsive m-0 border-0">
                            <table class="table-bordered excel-table align-middle mb-0" id="gradesTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="sticky-col-1 border-bottom-0">No.</th>
                                        <th rowspan="2" class="sticky-col-2 border-bottom-0">Student No.</th>
                                        <th rowspan="2" class="sticky-col-3 border-bottom-0" style="text-align: left !important; padding-left: 15px;">Student Name</th>
                                        <th colspan="19" style="background-color: #0F172A; color: #fff; letter-spacing: 2px;">
                                            <?= htmlspecialchars(strtoupper($grading_period)); ?> PERIOD
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="background-color: #F1F5F9;">Behavior</th>
                                        <th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        <th>Attendance</th>
                                        <th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #F1F5F9;">P1</th>
                                        <th style="background-color: #F1F5F9;">P2</th>
                                        <th style="background-color: #F1F5F9;">P3</th>
                                        <th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th>
                                        <th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #F1F5F9;">A1</th>
                                        <th style="background-color: #F1F5F9;">A2</th>
                                        <th style="background-color: #F1F5F9;">A3</th>
                                        <th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        <th>Project</th>
                                        <th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #F1F5F9;">Exam</th>
                                        <th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        
                                        <th style="background-color: #0F172A; color: #fff; border-bottom: none;">Grade</th>
                                        <th style="background-color: #0F172A; color: #fff; border-bottom: none;">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($students && mysqli_num_rows($students) > 0): ?>
                                        <?php $row_no = 1; while ($row = mysqli_fetch_assoc($students)): 
                                            $is_locked = ((int)($row['is_locked'] ?? 0) === 1);
                                        ?>
                                            <tr class="student-row">
                                                <td class="sticky-col-1 text-muted fw-bold"><?= $row_no++; ?></td>
                                                <td class="sticky-col-2" style="font-family: monospace; color: #64748B; font-size: 12.5px;"><?= htmlspecialchars($row['student_number']); ?></td>
                                                <td class="sticky-col-3 fw-bold text-dark text-truncate" style="padding-left: 15px;">
                                                    <?= htmlspecialchars($row['full_name']); ?>
                                                    <input type="hidden" name="student_id[]" class="student-id-input" value="<?= (int)$row['student_id']; ?>">
                                                </td>
                                                
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="behavior[]" class="score-input" value="<?= htmlspecialchars((string)($row['behavior'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['behavior_eq'] ?? '-')); ?></td>
                                                
                                                <td style="text-align: center;"><input type="number" step="0.01" name="attendance[]" class="score-input" value="<?= htmlspecialchars((string)($row['attendance'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['attendance_eq'] ?? '-')); ?></td>
                                                
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="participation1[]" class="score-input" value="<?= htmlspecialchars((string)($row['participation1'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="participation2[]" class="score-input" value="<?= htmlspecialchars((string)($row['participation2'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="participation3[]" class="score-input" value="<?= htmlspecialchars((string)($row['participation3'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['participation_eq'] ?? '-')); ?></td>
                                                
                                                <td style="text-align: center;"><input type="number" step="0.01" name="quiz1[]" class="score-input" value="<?= htmlspecialchars((string)($row['quiz1'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="text-align: center;"><input type="number" step="0.01" name="quiz2[]" class="score-input" value="<?= htmlspecialchars((string)($row['quiz2'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="text-align: center;"><input type="number" step="0.01" name="quiz3[]" class="score-input" value="<?= htmlspecialchars((string)($row['quiz3'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="text-align: center;"><input type="number" step="0.01" name="quiz4[]" class="score-input" value="<?= htmlspecialchars((string)($row['quiz4'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['quiz_eq'] ?? '-')); ?></td>
                                                
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="activity1[]" class="score-input" value="<?= htmlspecialchars((string)($row['activity1'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="activity2[]" class="score-input" value="<?= htmlspecialchars((string)($row['activity2'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="activity3[]" class="score-input" value="<?= htmlspecialchars((string)($row['activity3'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['activity_eq'] ?? '-')); ?></td>
                                                
                                                <td style="text-align: center;"><input type="number" step="0.01" name="project[]" class="score-input" value="<?= htmlspecialchars((string)($row['project'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['project_eq'] ?? '-')); ?></td>
                                                
                                                <td style="background-color: #F8FAFC; text-align: center;"><input type="number" step="0.01" name="exam[]" class="score-input" value="<?= htmlspecialchars((string)($row['exam'] ?? '')); ?>" <?= $is_locked ? 'disabled' : ''; ?>></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569; text-align: center;"><?= htmlspecialchars((string)($row['exam_eq'] ?? '-')); ?></td>
                                                
                                                <td style="background-color: #0F172A; color: #fff; font-size: 15px; font-weight: 700; text-align: center; border-color: #334155;"><?= htmlspecialchars((string)($row['computed_grade'] ?? '-')); ?></td>
                                                <td style="background-color: #0F172A; border-color: #334155; text-align: center;">
                                                    <?php if (($row['remarks'] ?? '') === 'PASSED'): ?>
                                                        <span class="badge bg-success badge-modern">PASSED</span>
                                                    <?php elseif (($row['remarks'] ?? '') === 'FAILED'): ?>
                                                        <span class="badge bg-danger badge-modern">FAILED</span>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="color: rgba(255,255,255,0.4) !important;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="27" class="text-center text-muted py-5"><i class="fas fa-users-slash fs-1 mb-3 opacity-50"></i><br>No assigned students found for this subject.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="p-4 bg-white border-top d-flex justify-content-end gap-3 align-items-center" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                            <?php if ($students && mysqli_num_rows($students) > 0): ?>
                                <span class="text-muted fw-medium me-auto" style="font-size: 13px;"><i class="fas fa-info-circle me-1 text-primary"></i> You can save drafts without locking the sheet.</span>
                            <?php endif; ?>
                            
                            <button type="submit" name="save_grades" id="saveBtn" class="btn-save" onclick="showSaving()">
                                <i class="fas fa-save me-1" id="saveIcon"></i> Save Draft
                            </button>
                            
                            <button type="button" class="btn-submit" onclick="confirmSubmitLock()">
                                <i class="fas fa-lock me-1"></i> Submit & Lock
                            </button>
                            
                            <input type="hidden" name="submit_grades" id="submitGradesInput" value="0">
                        </div>
                    </div>  
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Trigger Success/Error Messages from backend
    <?= $swal_script; ?>

    // ==========================================
    // AUTO-SAVE / SESSION RECOVERY LOGIC (SILENT RESTORE UPDATE)
    // ==========================================
    const storageKey = 'draft_grades_sub_<?= (int)$subject_id ?>_per_<?= $grading_period ?>';
    const metaKey = 'draft_meta_prof_<?= (int)$professor_id ?>';
    
    // Gagamitin natin ito para palatandaan na nag-respond na ang user sa popup
    const sessionFlag = 'prompted_' + storageKey;

    // Kung nag-save ng maayos, linisin ang local storage at sessionStorage flag
    <?php if ($clear_local_storage): ?>
        localStorage.removeItem(storageKey);
        localStorage.removeItem(metaKey);
        sessionStorage.removeItem(sessionFlag);
    <?php endif; ?>

    document.addEventListener("DOMContentLoaded", function() {
        const scoreInputs = document.querySelectorAll('.score-input:not([disabled])');
        const rows = document.querySelectorAll('.student-row');

        // Function para i-record sa localStorage habang nagta-type ang user
        function saveSession() {
            if (rows.length === 0) return;
            let sessionData = {};
            
            rows.forEach(row => {
                const studentIdInput = row.querySelector('.student-id-input');
                if (!studentIdInput) return;
                const sId = studentIdInput.value;
                
                sessionData[sId] = {};
                const inputs = row.querySelectorAll('.score-input:not([disabled])');
                inputs.forEach(inp => {
                    const nameKey = inp.name.replace('[]', '');
                    sessionData[sId][nameKey] = inp.value;
                });
            });
            
            localStorage.setItem(storageKey, JSON.stringify(sessionData));
            
            localStorage.setItem(metaKey, JSON.stringify({
                subject_id: '<?= (int)$subject_id ?>',
                period: '<?= $grading_period ?>',
                subject_code: '<?= htmlspecialchars($subject['subject_code']) ?>'
            }));
            
            // Kapag nag-type ang user, ide-delete natin yung session flag para matrack ulit as bagong draft
            sessionStorage.removeItem(sessionFlag);
        }

        scoreInputs.forEach(input => {
            input.addEventListener('input', saveSession);
        });

        <?php if (!$clear_local_storage): ?>
        const savedDraft = localStorage.getItem(storageKey);
        const hasPrompted = sessionStorage.getItem(sessionFlag);
        
        if (savedDraft && scoreInputs.length > 0) {
            
            // Kung nag-"Recover" na siya sa session na 'to tapos kinlick yung Load Period, SILENT RESTORE lang
            if (hasPrompted === 'recovered') {
                try {
                    const parsed = JSON.parse(savedDraft);
                    rows.forEach(row => {
                        const studentIdInput = row.querySelector('.student-id-input');
                        if (!studentIdInput) return;
                        const sId = studentIdInput.value;
                        
                        if (parsed[sId]) {
                            const inputs = row.querySelectorAll('.score-input:not([disabled])');
                            inputs.forEach(inp => {
                                const nameKey = inp.name.replace('[]', '');
                                if (parsed[sId][nameKey] !== undefined && parsed[sId][nameKey] !== '') {
                                    inp.value = parsed[sId][nameKey];
                                }
                            });
                        }
                    });
                } catch(e) { console.error("Error silently restoring data"); }
            
            } else {
                // KUNG FIRST TIME makita ang draft (halimbawa kaka-login, or nabuhay ang net) -> SHOW POPUP
                Swal.fire({
                    title: 'Session Recovery',
                    text: 'We found unsaved grades from your previous session. Would you like to recover them?',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#0047FF',
                    cancelButtonColor: '#94A3B8',
                    confirmButtonText: '<i class="fas fa-undo"></i> Recover Grades',
                    cancelButtonText: 'Discard',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        
                        // Palatandaan na nag-recover siya para hindi na umepal ang popup pag na-refresh
                        sessionStorage.setItem(sessionFlag, 'recovered');
                        
                        try {
                            const parsed = JSON.parse(savedDraft);
                            rows.forEach(row => {
                                const studentIdInput = row.querySelector('.student-id-input');
                                if (!studentIdInput) return;
                                const sId = studentIdInput.value;
                                
                                if (parsed[sId]) {
                                    const inputs = row.querySelectorAll('.score-input:not([disabled])');
                                    inputs.forEach(inp => {
                                        const nameKey = inp.name.replace('[]', '');
                                        if (parsed[sId][nameKey] !== undefined && parsed[sId][nameKey] !== '') {
                                            inp.value = parsed[sId][nameKey];
                                        }
                                    });
                                }
                            });
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Recovered!',
                                text: 'Your grades have been restored. Please review and click Save Draft.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } catch(e) {
                            console.error("Error parsing recovery data");
                        }
                    } else {
                        // Kung Discard, ide-delete ang drafts
                        localStorage.removeItem(storageKey);
                        localStorage.removeItem(metaKey);
                        sessionStorage.setItem(sessionFlag, 'discarded');
                    }
                });
            }
        }
        <?php endif; ?>
    });

    // ==========================================
    // UI Loading States & Submit Protection
    // ==========================================
    
    document.addEventListener("DOMContentLoaded", function() {
        const loadForm = document.getElementById("loadPeriodForm");
        const loadBtn = document.getElementById("loadBtn");
        const loadIcon = document.getElementById("loadIcon");

        if (loadForm) {
            loadForm.addEventListener("submit", function() {
                loadBtn.classList.add("disabled"); 
                loadIcon.classList.remove("fa-sync-alt"); 
                loadIcon.classList.add("fa-spinner", "fa-spin"); 
                loadBtn.innerHTML = 'Loading...';
            });
        }
    });

    function showSaving() {
        const saveBtn = document.getElementById("saveBtn");
        const saveIcon = document.getElementById("saveIcon");
        saveIcon.classList.remove("fa-save");
        saveIcon.classList.add("fa-spinner", "fa-spin");
        saveBtn.innerHTML = 'Saving...';
    }

    function confirmSubmitLock() {
        var canSubmit = <?= $can_submit ? 'true' : 'false' ?>;
        var prevPeriod = '<?= $previous_period ?>';

        if (!canSubmit) {
            Swal.fire({
                icon: 'error',
                title: 'Action Disabled',
                text: 'You must submit and lock the ' + prevPeriod + ' period first before you can lock this period.'
            });
            return;
        }

        Swal.fire({
            title: 'Submit & Lock Grades?',
            text: "Are you sure? Once submitted and locked, you will not be able to edit these grades unless you request an unlock from the Admin.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10B981',
            cancelButtonColor: '#94A3B8',
            confirmButtonText: '<i class="fas fa-lock"></i> Yes, lock it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true 
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Locking Grades...',
                    text: 'Please wait while the grades are being submitted.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                document.getElementById('submitGradesInput').value = '1';
                document.getElementById('gradesForm').submit();
            }
        });
    }
</script>

<?php if (isset($students_stmt) && $students_stmt) mysqli_stmt_close($students_stmt); include '../includes/footer.php'; ?>