<?php
session_start();

/** @var mysqli $conn */
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = isset($_SESSION['full_name']) ? htmlspecialchars((string)$_SESSION['full_name'], ENT_QUOTES) : 'Administrator';

// ----------------------------------------------------------------------
// GET PARAMETERS
// ----------------------------------------------------------------------
$prof_id = isset($_GET['prof_id']) ? (int) $_GET['prof_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$grading_period = isset($_GET['grading_period']) ? trim((string)$_GET['grading_period']) : '';

$allowed_periods = ['Prelim', 'Midterm', 'Prefinal', 'Final'];
if (!in_array($grading_period, $allowed_periods, true)) {
    $grading_period = '';
}

// ----------------------------------------------------------------------
// STEP 1 DATA: GET THE PROFESSORS
// ----------------------------------------------------------------------
$professors_result = false;
if ($prof_id === 0) {
    $prof_sql = "
        SELECT 
            u.user_id AS prof_id, 
            u.full_name, 
            u.email,
            COUNT(DISTINCT sub.subject_id) AS total_subjects,
            COUNT(DISTINCT g.student_id) AS total_students
        FROM users u
        LEFT JOIN subjects sub ON u.user_id = sub.professor_id
        LEFT JOIN grades g ON sub.subject_id = g.subject_id
        WHERE u.role = 'professor'
        GROUP BY u.user_id, u.full_name, u.email
        ORDER BY u.full_name ASC
    ";
    $professors_result = mysqli_query($conn, $prof_sql);
}

// ----------------------------------------------------------------------
// STEP 2 DATA: GET PROFESSOR DETAILS AND STUDENTS LIST (NO REPORT CARD YET)
// ----------------------------------------------------------------------
$selected_prof = null;
$students_result = false;

if ($prof_id > 0 && $student_id === 0) {
    // 1. Professor Details
    $stmt_prof = mysqli_prepare($conn, "SELECT user_id, full_name, email FROM users WHERE user_id = ? AND role = 'professor'");
    if ($stmt_prof) {
        mysqli_stmt_bind_param($stmt_prof, "i", $prof_id);
        mysqli_stmt_execute($stmt_prof);
        $res_p = mysqli_stmt_get_result($stmt_prof);
        $selected_prof = mysqli_fetch_assoc($res_p);
    }

    // 2. Get unique students who have grades under this professor's subjects
    $stmt_studs = mysqli_prepare($conn, "
        SELECT DISTINCT s.student_id, s.student_number, s.full_name 
        FROM students s
        JOIN grades g ON s.student_id = g.student_id
        JOIN subjects sub ON g.subject_id = sub.subject_id
        WHERE sub.professor_id = ?
        ORDER BY s.full_name ASC
    ");
    if ($stmt_studs) {
        mysqli_stmt_bind_param($stmt_studs, "i", $prof_id);
        mysqli_stmt_execute($stmt_studs);
        $students_result = mysqli_stmt_get_result($stmt_studs);
    }
}

// ----------------------------------------------------------------------
// STEP 3 DATA: GET REPORT CARD FOR THE SELECTED STUDENT
// ----------------------------------------------------------------------
$selected_student = null;
$subjects_result = false;
$grades_result = false;

if ($prof_id > 0 && $student_id > 0) {
    // 1. Professor Details
    $stmt_prof = mysqli_prepare($conn, "SELECT user_id, full_name, email FROM users WHERE user_id = ? AND role = 'professor'");
    if ($stmt_prof) {
        mysqli_stmt_bind_param($stmt_prof, "i", $prof_id);
        mysqli_stmt_execute($stmt_prof);
        $res_p = mysqli_stmt_get_result($stmt_prof);
        $selected_prof = mysqli_fetch_assoc($res_p);
    }

    // 2. Student Details
    $stmt_stud = mysqli_prepare($conn, "SELECT student_id, student_number, full_name FROM students WHERE student_id = ?");
    if ($stmt_stud) {
        mysqli_stmt_bind_param($stmt_stud, "i", $student_id);
        mysqli_stmt_execute($stmt_stud);
        $res_s = mysqli_stmt_get_result($stmt_stud);
        $selected_student = mysqli_fetch_assoc($res_s);
    }

    // 3. Subjects handled by the Professor (for filter dropdown)
    $stmt_subj = mysqli_prepare($conn, "
        SELECT subject_id, subject_code, subject_name 
        FROM subjects 
        WHERE professor_id = ? 
        ORDER BY subject_name ASC
    ");
    if ($stmt_subj) {
        mysqli_stmt_bind_param($stmt_subj, "i", $prof_id);
        mysqli_stmt_execute($stmt_subj);
        $subjects_result = mysqli_stmt_get_result($stmt_subj);
    }

    // 4. Grade Report Query for the specific Student under this Professor
    $sql = "
        SELECT 
            g.grade_id,
            s.student_number,
            s.full_name AS student_name,
            sub.subject_code,
            sub.subject_name,
            g.grading_period,

            g.behavior, g.attendance,
            g.participation1, g.participation2, g.participation3,
            g.quiz1, g.quiz2, g.quiz3, g.quiz4,
            g.activity1, g.activity2, g.activity3,
            g.project, g.exam,

            g.behavior_eq, g.attendance_eq, g.participation_eq,
            g.quiz_eq, g.activity_eq, g.project_eq, g.exam_eq,

            g.computed_grade, g.remarks, g.is_submitted
        FROM subjects sub
        JOIN grades g ON sub.subject_id = g.subject_id
        JOIN students s ON g.student_id = s.student_id
        WHERE sub.professor_id = ? AND s.student_id = ?
    ";

    $params = [$prof_id, $student_id];
    $types = 'ii';

    if ($subject_id > 0) {
        $sql .= " AND sub.subject_id = ?";
        $params[] = $subject_id;
        $types .= 'i';
    }

    if ($grading_period !== '') {
        $sql .= " AND g.grading_period = ?";
        $params[] = $grading_period;
        $types .= 's';
    }

    $sql .= " ORDER BY sub.subject_name ASC, g.grading_period ASC";

    $stmt_grades = mysqli_prepare($conn, $sql);
    if ($stmt_grades) {
        mysqli_stmt_bind_param($stmt_grades, $types, ...$params);
        mysqli_stmt_execute($stmt_grades);
        $grades_result = mysqli_stmt_get_result($stmt_grades);
    }
}

// SMART FORMATTER
function fmt($val) {
    if (!is_numeric($val)) return htmlspecialchars((string)$val, ENT_QUOTES);
    $floatVal = (float)$val;
    return ($floatVal == (int)$floatVal) ? (string)(int)$floatVal : number_format($floatVal, 2);
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
    
    .prof-card, .student-card-item { transition: all 0.25s ease-in-out; border: 1px solid #E2E8F0; border-radius: 16px; background: #ffffff; cursor: pointer; text-decoration: none !important; }
    .prof-card:hover, .student-card-item:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -10px rgba(0, 71, 255, 0.15); border-color: #0047FF; }

    .form-select-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 6px 15px; font-size: 13px; color: #334155; background-color: #F8FAFC; transition: 0.2s; cursor: pointer; height: 35px;}
    .form-select-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    .btn-filter { height: 35px; padding: 0 15px; font-size: 13px; font-weight: 500; border-radius: 8px; display: flex; align-items: center; }

    .btn-print { background-color: #F8FAFC; color: #10B981; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 16px; transition: 0.2s; }
    .btn-print:hover { background-color: #10B981; color: white; border-color: #10B981; }
    .btn-pdf { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 16px; transition: 0.2s; }
    .btn-pdf:hover { background-color: #EF4444; color: white; }

    .table-responsive { max-height: 65vh; overflow-y: auto; overflow-x: auto; border-radius: 8px; border: 1px solid #E2E8F0; }
    .excel-table { border-collapse: collapse; width: 100%; table-layout: auto; }
    .excel-table th, .excel-table td { border: 1px solid #E2E8F0; text-align: center; vertical-align: middle; white-space: nowrap; padding: 6px 4px; }
    .excel-table th { background-color: #F8FAFC; color: #475569; font-weight: 700; font-size: 9px; letter-spacing: 0.2px; text-transform: uppercase; position: sticky; z-index: 10; }
    .excel-table thead tr:nth-child(1) th { top: 0; }
    .excel-table thead tr:nth-child(2) th { top: 31px; border-top: none; }
    .excel-table td { font-size: 10.5px; color: #1E293B; }

    .name-cell { text-align: left !important; font-weight: 600; padding-left: 8px !important; white-space: normal !important; min-width: 80px; max-width: 160px; word-wrap: break-word; }
    .subj-cell { text-align: left !important; font-size: 9.5px !important; padding-left: 8px !important; color: #475569 !important; white-space: normal !important; min-width: 100px; max-width: 220px; word-wrap: break-word; }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        .search-wrapper { width: 100% !important; margin-top: 5px; }
        
        /* Filter form adjustments for mobile */
        #reportFilterForm { flex-direction: column; align-items: stretch !important; gap: 15px !important; }
        #reportFilterForm > div { width: 100% !important; }
        #reportFilterForm .form-select-custom { width: 100% !important; min-width: unset !important; }
        #reportFilterForm .d-flex.align-items-end { width: 100% !important; justify-content: space-between; margin-top: 0 !important; }
        #reportFilterForm button[type="submit"], #reportFilterForm a.btn-light { flex: 1; justify-content: center; }
    }

    @media print {
        @page { size: landscape; margin: 5mm; }
        .sidebar, .top-navbar, .filter-card, .no-print, .top-header-section { display: none !important; }
        body, .container-fluid, .row, .col-md-10, .content-wrapper, .saas-card { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; background: #ffffff !important; box-shadow: none !important; border: none !important; }
        .table-responsive { max-height: none !important; overflow: visible !important; border: none !important; }
        .excel-table { width: 100% !important; table-layout: auto !important; }
        .excel-table th, .excel-table td { border: 1px solid #000 !important; font-size: 8px !important; padding: 3px 2px !important; line-height: 1.1 !important; }
        .excel-table th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10 w-100-mobile">
            <div class="content-wrapper">
                
                <!-- TOP NAVBAR -->
                <div class="top-navbar d-flex justify-content-between align-items-center top-header-section">
                    <div class="d-flex align-items-center">
                        <!-- Mobile Hamburger Button -->
                        <button class="btn btn-light d-md-none me-2 border shadow-sm" onclick="toggleMobileSidebar()" style="border-radius: 8px;">
                            <i class="fas fa-bars text-dark"></i>
                        </button>
                        <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                            Home <span class="mx-1">></span> Reports <span class="mx-1">></span> 
                            <?php if ($prof_id > 0): ?>
                                <a href="reports.php" class="text-decoration-none text-muted">Professors</a> <span class="mx-1">></span>
                                <a href="reports.php?prof_id=<?= $prof_id ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($selected_prof['full_name'] ?? 'Professor', ENT_QUOTES) ?></a>
                                <?php if ($student_id > 0): ?>
                                    <span class="mx-1">></span> <strong class="text-dark"><?= htmlspecialchars($selected_student['full_name'] ?? 'Student Report', ENT_QUOTES) ?></strong>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong class="text-dark">Professors List</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name_session ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Administrator</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name_session, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <!-- STEP 1: PROFESSORS SELECTION -->
                <?php if ($prof_id === 0): ?>

                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <div>
                            <h3 class="fw-bold mb-1" style="color: #0F172A;">Faculty Grade Reports</h3>
                            <p class="text-muted mb-0" style="font-size: 14px;">Select a Professor to view their student list.</p>
                        </div>
                        <div class="search-wrapper m-0 w-100 w-md-auto">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchProfInput" placeholder="Search professor name or email...">
                        </div>
                    </div>

                    <div class="row g-3" id="professorsGrid">
                        <?php if ($professors_result && mysqli_num_rows($professors_result) > 0): ?>
                            <?php while ($prof = mysqli_fetch_assoc($professors_result)): ?>
                                <?php 
                                    $p_id = (int)$prof['prof_id'];
                                    $p_name = htmlspecialchars($prof['full_name'], ENT_QUOTES);
                                    $p_email = htmlspecialchars($prof['email'] ?? 'No Email', ENT_QUOTES);
                                    $p_subjs = (int)$prof['total_subjects'];
                                    $p_studs = (int)$prof['total_students'];
                                    $search_tag = strtolower($p_name . ' ' . $p_email);
                                ?>
                                <div class="col-md-4 col-sm-6 prof-card-item" data-search="<?= $search_tag ?>">
                                    <a href="reports.php?prof_id=<?= $p_id ?>" class="prof-card p-4 d-block h-100">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 48px; height: 48px; font-size: 18px;">
                                                <?= strtoupper(substr($p_name, 0, 1)) ?>
                                            </div>
                                            <div class="overflow-hidden">
                                                <h6 class="fw-bold mb-0 text-dark text-truncate" style="font-size: 15px;"><?= $p_name ?></h6>
                                                <small class="text-muted text-truncate d-block" style="font-size: 12px;"><?= $p_email ?></small>
                                            </div>
                                        </div>

                                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                                            <div class="d-flex gap-2 flex-wrap">
                                                <span class="badge bg-light text-secondary border px-2 py-1" style="font-weight: 500; font-size: 11px;">
                                                    <i class="fas fa-book me-1 text-primary"></i> <?= $p_subjs ?> Subject<?= $p_subjs !== 1 ? 's' : '' ?>
                                                </span>
                                                <span class="badge bg-light text-secondary border px-2 py-1" style="font-weight: 500; font-size: 11px;">
                                                    <i class="fas fa-user-graduate me-1 text-success"></i> <?= $p_studs ?> Students
                                                </span>
                                            </div>
                                            <i class="fas fa-chevron-right text-muted" style="font-size: 12px;"></i>
                                        </div>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="saas-card text-center py-5">
                                    <i class="fas fa-user-slash text-muted mb-3" style="font-size: 36px; opacity: 0.5;"></i>
                                    <h6 class="fw-bold text-dark mb-1">No Professor Found</h6>
                                    <p class="text-muted small mb-0">There are no Professors registered in the system yet.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12" id="noProfFoundMsg" style="display: none;">
                            <div class="saas-card text-center py-5">
                                <i class="fas fa-search text-muted mb-2" style="font-size: 28px;"></i>
                                <h6 class="fw-bold text-dark mb-1">No match found for your search.</h6>
                            </div>
                        </div>
                    </div>

                <!-- STEP 2: STUDENTS LIST (NO REPORT CARD YET) -->
                <?php elseif ($student_id === 0): ?>

                    <div class="mb-3">
                        <a href="reports.php" class="btn btn-sm btn-light border fw-bold text-secondary px-3 py-2 shadow-sm" style="border-radius: 8px;">
                            <i class="fas fa-arrow-left me-2"></i> Back to Professors List
                        </a>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h3 class="fw-bold mb-0" style="color: #0F172A;"><?= htmlspecialchars($selected_prof['full_name'] ?? 'Professor', ENT_QUOTES) ?></h3>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1" style="font-size: 11px;">Instructor</span>
                            </div>
                            <p class="text-muted mb-0 mt-1" style="font-size: 13px;">Click a student below to view their official report card.</p>
                        </div>
                        <div class="search-wrapper m-0 w-100 w-md-auto">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchStudentList" placeholder="Search student name or number...">
                        </div>
                    </div>

                    <div class="row g-3" id="studentsGrid">
                        <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                            <?php while ($stud = mysqli_fetch_assoc($students_result)): ?>
                                <?php 
                                    $s_id = (int)$stud['student_id'];
                                    $s_num = htmlspecialchars($stud['student_number'], ENT_QUOTES);
                                    $s_name = htmlspecialchars($stud['full_name'], ENT_QUOTES);
                                    $search_tag = strtolower($s_num . ' ' . $s_name);
                                ?>
                                <div class="col-md-4 col-sm-6 student-card-item" data-search="<?= $search_tag ?>">
                                    <a href="reports.php?prof_id=<?= $prof_id ?>&student_id=<?= $s_id ?>" class="p-4 d-block h-100 text-decoration-none">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 48px; height: 48px; font-size: 18px;">
                                                <?= strtoupper(substr($s_name, 0, 1)) ?>
                                            </div>
                                            <div class="overflow-hidden">
                                                <h6 class="fw-bold mb-0 text-dark text-truncate" style="font-size: 15px;"><?= $s_name ?></h6>
                                                <small class="text-muted text-truncate d-block" style="font-size: 12px; font-family: monospace;">ID: <?= $s_num ?></small>
                                            </div>
                                        </div>
                                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="text-muted" style="font-size: 12px;"><i class="fas fa-id-card me-1"></i> View Report Card</span>
                                            <i class="fas fa-chevron-right text-muted" style="font-size: 12px;"></i>
                                        </div>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="saas-card text-center py-5">
                                    <i class="fas fa-user-graduate text-muted mb-3" style="font-size: 36px; opacity: 0.5;"></i>
                                    <h6 class="fw-bold text-dark mb-1">No Students Found</h6>
                                    <p class="text-muted small mb-0">There are no graded students recorded for this professor yet.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12" id="noStudentFoundMsg" style="display: none;">
                            <div class="saas-card text-center py-5">
                                <i class="fas fa-search text-muted mb-2" style="font-size: 28px;"></i>
                                <h6 class="fw-bold text-dark mb-1">No match found for your search.</h6>
                            </div>
                        </div>
                    </div>

                <!-- STEP 3: REPORT CARD & FILTERS VIEW -->
                <?php else: ?>

                    <div class="mb-3">
                        <a href="reports.php?prof_id=<?= $prof_id ?>" class="btn btn-sm btn-light border fw-bold text-secondary px-3 py-2 shadow-sm" style="border-radius: 8px;">
                            <i class="fas fa-arrow-left me-2"></i> Back to Students List
                        </a>
                    </div>

                    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3 top-header-section">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h3 class="fw-bold mb-0" style="color: #0F172A;"><?= htmlspecialchars($selected_student['full_name'] ?? 'Student', ENT_QUOTES) ?></h3>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 11px;">Student ID: <?= htmlspecialchars($selected_student['student_number'] ?? '', ENT_QUOTES) ?></span>
                            </div>
                            <p class="text-muted mb-0 mt-1" style="font-size: 13px;">Instructor: <strong><?= htmlspecialchars($selected_prof['full_name'] ?? '', ENT_QUOTES) ?></strong></p>
                        </div>
                        <div class="no-print d-flex gap-2 flex-wrap w-100 w-md-auto">
                            <button onclick="window.print()" class="btn-print shadow-sm flex-fill">
                                <i class="fas fa-print me-1"></i> Print Report
                            </button>
                            <a href="download_report.php?prof_id=<?= $prof_id ?>&student_id=<?= $student_id ?><?= ($subject_id > 0) ? '&subject_id=' . $subject_id : '' ?><?= ($grading_period !== '') ? '&grading_period=' . urlencode($grading_period) : '' ?>" class="btn-pdf shadow-sm text-decoration-none flex-fill text-center">
                                <i class="fas fa-file-pdf me-1"></i> Download PDF
                            </a>
                        </div>
                    </div>

                    <!-- FILTER CARD -->
                    <div class="saas-card mb-4 filter-card no-print">
                        <form method="GET" id="reportFilterForm" class="d-flex justify-content-between align-items-center flex-wrap gap-3 m-0">
                            <input type="hidden" name="prof_id" value="<?= $prof_id ?>">
                            <input type="hidden" name="student_id" value="<?= $student_id ?>">
                            
                            <div class="d-flex align-items-center gap-3 flex-wrap w-100">
                                <div class="flex-fill">
                                    <label class="text-muted mb-1 fw-bold" style="font-size: 11px; text-transform: uppercase;">Filter Subject</label>
                                    <select name="subject_id" class="form-select-custom w-100" style="min-width: 240px;">
                                        <option value="">-- All Subjects --</option>
                                        <?php if ($subjects_result && mysqli_num_rows($subjects_result) > 0): ?>
                                            <?php while ($sub = mysqli_fetch_assoc($subjects_result)): ?>
                                                <option value="<?= (int)$sub['subject_id'] ?>" <?= $subject_id === (int)$sub['subject_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_name'], ENT_QUOTES) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="flex-fill">
                                    <label class="text-muted mb-1 fw-bold" style="font-size: 11px; text-transform: uppercase;">Grading Period</label>
                                    <select name="grading_period" class="form-select-custom w-100" style="min-width: 150px;">
                                        <option value="">-- All Periods --</option>
                                        <option value="Prelim" <?= $grading_period === 'Prelim' ? 'selected' : ''; ?>>Prelim</option>
                                        <option value="Midterm" <?= $grading_period === 'Midterm' ? 'selected' : ''; ?>>Midterm</option>
                                        <option value="Prefinal" <?= $grading_period === 'Prefinal' ? 'selected' : ''; ?>>Prefinal</option>
                                        <option value="Final" <?= $grading_period === 'Final' ? 'selected' : ''; ?>>Final</option>
                                    </select>
                                </div>

                                <div class="d-flex align-items-end mt-4">
                                    <button type="submit" id="filterBtn" class="btn btn-primary btn-filter shadow-sm">
                                        <i class="fas fa-filter me-1" id="filterIcon"></i> Apply Filter
                                    </button>
                                    <a href="reports.php?prof_id=<?= $prof_id ?>&student_id=<?= $student_id ?>" class="btn btn-light border btn-filter ms-2 text-decoration-none text-muted" title="Reset Filters"><i class="fas fa-undo"></i></a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- REPORT CARD TABLE -->
                    <div class="saas-card p-0 overflow-hidden">
                        <div class="p-4 text-center border-bottom border-light">
                            <img src="../assets/SFICS-LOGO.jpg" width="55" class="mb-2 d-block mx-auto rounded-circle shadow-sm border border-2 border-success" onerror="this.src='../assets/default-logo.png'">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 17px;">Saint Francis Institute of Computer Studies</h5>
                            <small class="d-block text-secondary">Web-Based Student Grading System</small>
                            <strong class="d-block mt-2 text-dark" style="font-size: 14px;">Official Student Grade Report</strong>
                            
                            <div class="mt-2 d-flex justify-content-center gap-2 flex-wrap">
                                <span class="badge bg-light text-dark border px-3 py-1" style="font-weight: 500; font-size: 11px;">
                                    <strong>Student:</strong> <?= htmlspecialchars($selected_student['full_name'] ?? 'N/A', ENT_QUOTES) ?>
                                </span>
                                <span class="badge bg-light text-muted border px-3 py-1" style="font-weight: 500; font-size: 11px;">
                                    <strong>Instructor:</strong> <?= htmlspecialchars($selected_prof['full_name'] ?? 'N/A', ENT_QUOTES) ?>
                                </span>
                            </div>
                        </div>

                        <div class="table-responsive m-0 border-0 border-top">
                            <table class="table excel-table align-middle mb-0" id="gradeReportTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2">Subject</th>
                                        <th rowspan="2">Term</th>

                                        <th colspan="2" style="background-color: #F1F5F9;">BEH</th>
                                        <th colspan="2">ATT</th>
                                        <th colspan="4" style="background-color: #F1F5F9;">PART</th>
                                        <th colspan="5">QUIZ</th>
                                        <th colspan="4" style="background-color: #F1F5F9;">ACT</th>
                                        <th colspan="2">PROJ</th>
                                        <th colspan="2" style="background-color: #F1F5F9;">EXAM</th>

                                        <th rowspan="2">Final</th>
                                        <th rowspan="2">Rem</th>
                                        <th rowspan="2" title="Submitted">Sub</th>
                                    </tr>
                                    <tr>
                                        <th style="background-color: #E2E8F0;">R</th><th style="background-color: #CBD5E1; color: #0F172A;">EQ</th>
                                        <th>R</th><th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #E2E8F0;">P1</th><th style="background-color: #E2E8F0;">P2</th><th style="background-color: #E2E8F0;">P3</th><th style="background-color: #CBD5E1; color: #0F172A;">EQ</th>
                                        <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #E2E8F0;">A1</th><th style="background-color: #E2E8F0;">A2</th><th style="background-color: #E2E8F0;">A3</th><th style="background-color: #CBD5E1; color: #0F172A;">EQ</th>
                                        <th>R</th><th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #E2E8F0;">R</th><th style="background-color: #CBD5E1; color: #0F172A;">EQ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($grades_result && mysqli_num_rows($grades_result) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($grades_result)): ?>
                                            <tr class="student-row">
                                                <td class="subj-cell">
                                                    <?= htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name'], ENT_QUOTES); ?>
                                                </td>
                                                <td class="fw-bold text-primary" style="font-size: 9.5px;"><?= htmlspecialchars(substr((string)$row['grading_period'], 0, 3), ENT_QUOTES); ?></td>

                                                <!-- BEH -->
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['behavior']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= fmt($row['behavior_eq']); ?></td>

                                                <!-- ATT -->
                                                <td><?= fmt($row['attendance']); ?></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569;"><?= fmt($row['attendance_eq']); ?></td>

                                                <!-- PART -->
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['participation1']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['participation2']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['participation3']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= fmt($row['participation_eq']); ?></td>

                                                <!-- QUIZ -->
                                                <td><?= fmt($row['quiz1']); ?></td>
                                                <td><?= fmt($row['quiz2']); ?></td>
                                                <td><?= fmt($row['quiz3']); ?></td>
                                                <td><?= fmt($row['quiz4']); ?></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569;"><?= fmt($row['quiz_eq']); ?></td>

                                                <!-- ACT -->
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['activity1']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['activity2']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['activity3']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= fmt($row['activity_eq']); ?></td>

                                                <!-- PROJ -->
                                                <td><?= fmt($row['project']); ?></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569;"><?= fmt($row['project_eq']); ?></td>

                                                <!-- EXAM -->
                                                <td style="background-color: #F8FAFC;"><?= fmt($row['exam']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= fmt($row['exam_eq']); ?></td>

                                                <!-- FINAL / REMARKS / SUBMITTED -->
                                                <td class="fw-bold" style="color: #0047FF; background-color: #F8FAFC;"><?= fmt($row['computed_grade']); ?></td>
                                                
                                                <td>
                                                    <?php if(strtoupper($row['remarks'] ?? '') === 'PASSED'): ?>
                                                        <span class="text-success fw-bold" style="font-size:9px;">PASSED</span>
                                                    <?php else: ?>
                                                        <span class="text-danger fw-bold" style="font-size:9px;"><?= htmlspecialchars(strtoupper($row['remarks'] ?? ''), ENT_QUOTES) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($row['is_submitted'] == 1): ?>
                                                        <i class="fas fa-check-circle text-success" title="Submitted"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-clock text-warning" title="Draft"></i>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="26" class="text-center py-4 text-muted">
                                                <i class="fas fa-folder-open mb-2 text-secondary" style="font-size: 24px;"></i><br>
                                                No grade records found for the selected filter.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        // --- Search Logic for Professors List ---
        const searchProfInput = document.getElementById("searchProfInput");
        if (searchProfInput) {
            searchProfInput.addEventListener("keyup", function() {
                const term = this.value.toLowerCase();
                const cards = document.querySelectorAll(".prof-card-item");
                let count = 0;
                
                cards.forEach(card => {
                    if (card.getAttribute("data-search").includes(term)) {
                        card.style.display = "block";
                        count++;
                    } else {
                        card.style.display = "none";
                    }
                });

                const noMsg = document.getElementById("noProfFoundMsg");
                if (noMsg) {
                    noMsg.style.display = (count === 0 && cards.length > 0) ? "block" : "none";
                }
            });
        }

        // --- Search Logic for Students List ---
        const searchStudentList = document.getElementById("searchStudentList");
        if (searchStudentList) {
            searchStudentList.addEventListener("keyup", function() {
                const term = this.value.toLowerCase();
                const cards = document.querySelectorAll(".student-card-item");
                let count = 0;
                
                cards.forEach(card => {
                    if (card.getAttribute("data-search").includes(term)) {
                        card.style.display = "block";
                        count++;
                    } else {
                        card.style.display = "none";
                    }
                });

                const noMsg = document.getElementById("noStudentFoundMsg");
                if (noMsg) {
                    noMsg.style.display = (count === 0 && cards.length > 0) ? "block" : "none";
                }
            });
        }

        // --- Form Loading State ---
        const reportForm = document.getElementById("reportFilterForm");
        if(reportForm) {
            reportForm.addEventListener("submit", function() {
                const btn = document.getElementById("filterBtn");
                const icon = document.getElementById("filterIcon");
                btn.classList.add("disabled");
                btn.style.opacity = "0.7";
                icon.className = "fas fa-spinner fa-spin me-1";
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>