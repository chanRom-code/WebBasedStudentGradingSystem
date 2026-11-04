<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit;
}

$professor_id = (int) $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Professor');

$swal_script = ''; 

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

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'request_sent') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Request Sent!', text: 'Unlock request submitted successfully to the Admin.', showConfirmButton: false, timer: 2500 });";
    } elseif ($_GET['msg'] === 'access_denied') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Access Denied', text: 'You are not allowed to access that grade record.' });";
    } elseif ($_GET['msg'] === 'not_locked') {
        $swal_script = "Swal.fire({ icon: 'info', title: 'Already Open', text: 'This grade is not locked, an unlock request is not needed.' });";
    } elseif ($_GET['msg'] === 'invalid_grade') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Invalid', text: 'Invalid grade record selected.' });";
    }
}

// VIEW LOGIC (Check kung may piniling Subject at Period)
$allowed_periods = ['Prelim', 'Midterm', 'Prefinal', 'Final'];
$selected_period = isset($_GET['period']) && in_array($_GET['period'], $allowed_periods) ? $_GET['period'] : null;
$selected_subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : null;

$result = false;
$subject_tree = [];
$active_subject_name = "";

if ($selected_period && $selected_subject_id) {
    // ================= FETCH GRADES FOR TABLE VIEW =================
    // Kukunin muna ang subject name para sa header/breadcrumb
    $sub_query = "SELECT subject_code, subject_name FROM subjects WHERE subject_id = ?";
    if ($s_stmt = mysqli_prepare($conn, $sub_query)) {
        mysqli_stmt_bind_param($s_stmt, "i", $selected_subject_id);
        mysqli_stmt_execute($s_stmt);
        $s_res = mysqli_stmt_get_result($s_stmt);
        if ($s_row = mysqli_fetch_assoc($s_res)) {
            $active_subject_name = $s_row['subject_code'];
        }
        mysqli_stmt_close($s_stmt);
    }

    $sql = "
        SELECT 
            g.grade_id, s.student_number, s.full_name AS student_name,
            sub.subject_code, sub.subject_name, g.grading_period,
            g.behavior, g.attendance, g.participation1, g.participation2, g.participation3,
            g.quiz1, g.quiz2, g.quiz3, g.quiz4, g.activity1, g.activity2, g.activity3, g.project, g.exam,
            g.behavior_eq, g.attendance_eq, g.participation_eq, g.quiz_eq, g.activity_eq, g.project_eq, g.exam_eq,
            g.computed_grade, g.remarks, g.is_submitted, g.is_locked,
            (
                SELECT ur.request_status
                FROM unlock_requests ur
                WHERE ur.grade_id = g.grade_id
                ORDER BY ur.request_id DESC
                LIMIT 1
            ) AS request_status
        FROM grades g
        JOIN students s ON g.student_id = s.student_id
        JOIN subjects sub ON g.subject_id = sub.subject_id
        WHERE g.professor_id = ? AND g.is_submitted = 1 AND g.subject_id = ? AND g.grading_period = ?
        ORDER BY s.full_name ASC
    ";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iis", $professor_id, $selected_subject_id, $selected_period);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    }
} else {
    // ================= FETCH FOLDERS GROUPED BY SEMESTER & SUBJECT =================
    // Note: In-assume ko na may 'semester' column sa 'subjects' table.
    $count_sql = "
        SELECT 
            sub.semester,
            sub.subject_id,
            sub.subject_code,
            sub.subject_name,
            g.grading_period,
            COUNT(*) as total
        FROM grades g
        JOIN subjects sub ON g.subject_id = sub.subject_id
        WHERE g.professor_id = ? AND g.is_submitted = 1
        GROUP BY sub.semester, sub.subject_id, sub.subject_code, sub.subject_name, g.grading_period
        ORDER BY sub.semester ASC, sub.subject_code ASC
    ";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    if ($count_stmt) {
        mysqli_stmt_bind_param($count_stmt, "i", $professor_id);
        mysqli_stmt_execute($count_stmt);
        $count_res = mysqli_stmt_get_result($count_stmt);
        
        while($c_row = mysqli_fetch_assoc($count_res)) {
            $sem = !empty($c_row['semester']) ? $c_row['semester'] : 'Unassigned Semester';
            $sid = $c_row['subject_id'];
            
            if(!isset($subject_tree[$sem])) {
                $subject_tree[$sem] = [];
            }
            if(!isset($subject_tree[$sem][$sid])) {
                $subject_tree[$sem][$sid] = [
                    'code' => $c_row['subject_code'],
                    'name' => $c_row['subject_name'],
                    'periods' => ['Prelim' => 0, 'Midterm' => 0, 'Prefinal' => 0, 'Final' => 0]
                ];
            }
            $subject_tree[$sem][$sid]['periods'][$c_row['grading_period']] = $c_row['total'];
        }
        mysqli_stmt_close($count_stmt);
    }
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }

    /* FOLDER UI STYLES */
    .sem-header { font-size: 14px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #E2E8F0; padding-bottom: 8px; margin-top: 30px; margin-bottom: 20px; }
    .subject-card { background: #ffffff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .subject-card:hover { border-color: #CBD5E1; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .subject-icon-box { width: 48px; height: 48px; background: #EEF2FF; color: #4F46E5; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    
    .period-btn { padding: 10px 15px; border-radius: 8px; border: 1px solid #E2E8F0; background: #F8FAFC; color: #334155; text-decoration: none; font-weight: 600; font-size: 13px; display: flex; align-items: center; transition: 0.2s; min-width: 130px; justify-content: space-between; }
    .period-btn:hover { background: #F1F5F9; color: #0F172A; border-color: #CBD5E1; }
    .period-btn.active-folder { background: #FFFBEB; border-color: #FDE68A; color: #B45309; }
    .period-btn.active-folder:hover { background: #FEF3C7; }
    .period-btn .badge-count { background: #E2E8F0; color: #475569; padding: 3px 8px; border-radius: 20px; font-size: 11px; }
    .period-btn.active-folder .badge-count { background: #FDE68A; color: #92400E; }

    /* CARDS & TABLES */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; }
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 320px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }

    .table-responsive { max-height: 65vh; overflow-y: auto; overflow-x: auto; border-radius: 8px; border: 1px solid #E2E8F0; }
    .excel-table { border-collapse: separate; border-spacing: 0; min-width: 2000px; width: 100%; }
    .excel-table th { background-color: #F8FAFC; color: #64748B; font-weight: 600; white-space: nowrap; text-align: center; vertical-align: middle; font-size: 11.5px; letter-spacing: 0.5px; text-transform: uppercase; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #E2E8F0; border-right: 1px solid #E2E8F0; padding: 10px; }
    .excel-table td { white-space: nowrap; vertical-align: middle; font-size: 13px; color: #334155; font-weight: 500; border-bottom: 1px solid #F1F5F9; border-right: 1px solid #F1F5F9; padding: 8px 12px; text-align: center; }

    .sticky-col-1 { position: sticky; left: 0; background: #ffffff; z-index: 5; min-width: 100px; max-width: 100px; border-right: 1px solid #E2E8F0 !important; }
    .sticky-col-2 { position: sticky; left: 100px; background: #ffffff; z-index: 5; min-width: 200px; max-width: 200px; border-right: 2px solid #E2E8F0 !important; box-shadow: 2px 0 5px rgba(0,0,0,0.03); text-align: left !important; padding-left: 15px !important;}
    .excel-table th.sticky-col-1, .excel-table th.sticky-col-2 { z-index: 15; background-color: #F8FAFC; }

    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; }
    .badge-success-soft { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-warning-soft { background-color: #FFFBEB; color: #F59E0B; border: 1px solid #FDE68A; }
    .badge-danger-soft { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; }
    .badge-info-soft { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #BFDBFE; }

    .btn-unlock { background-color: #FFFBEB; color: #F59E0B; border: 1px solid #FDE68A; border-radius: 6px; font-weight: 600; font-size: 12px; padding: 6px 12px; transition: 0.2s; text-decoration: none; display: inline-block;}
    .btn-unlock:hover { background-color: #F59E0B; color: white; border-color: #F59E0B; }
    .btn-back { background-color: #ffffff; color: #475569; border: 1px solid #CBD5E1; border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 16px; transition: 0.2s; text-decoration: none; }
    .btn-back:hover { background-color: #F1F5F9; color: #0F172A; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">

        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">

                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> Grades <span class="mx-1">></span> 
                        <?php if($selected_period && $selected_subject_id): ?>
                            <a href="?" class="text-decoration-none text-muted">Submitted</a> <span class="mx-1">></span> 
                            <span class="text-muted"><?= htmlspecialchars($active_subject_name) ?></span> <span class="mx-1">></span> 
                            <strong class="text-dark"><?= $selected_period ?></strong>
                        <?php else: ?>
                            <strong class="text-dark">Submitted Grades</strong>
                        <?php endif; ?>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Submitted Grades</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">View officially submitted grades organized by Semester and Subject.</p>
                    </div>
                    <?php if($selected_period && $selected_subject_id): ?>
                        <div>
                            <a href="?" class="btn-back shadow-sm"><i class="fas fa-arrow-left me-2"></i>Back to Folders</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if(!$selected_period || !$selected_subject_id): ?>
                    <!-- ================= HIERARCHY / FOLDER VIEW ================= -->
                    
                    <?php if(empty($subject_tree)): ?>
                        <div class="saas-card text-center py-5">
                            <i class="fas fa-folder-open text-muted mb-3" style="font-size: 40px; opacity: 0.5;"></i>
                            <h5 class="text-dark fw-bold">No Submitted Grades Yet</h5>
                            <p class="text-muted">You haven't submitted any grade records yet.</p>
                        </div>
                    <?php else: ?>
                        
                        <?php foreach($subject_tree as $semester => $subjects): ?>
                            <div class="sem-header"><i class="fas fa-calendar-alt me-2"></i> <?= htmlspecialchars($semester) ?></div>
                            
                            <div class="row g-4">
                                <?php foreach($subjects as $subj_id => $data): ?>
                                    <div class="col-xl-6">
                                        <div class="subject-card">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="subject-icon-box me-3">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold text-dark fs-5"><?= htmlspecialchars($data['code']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($data['name']) ?></small>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach($allowed_periods as $period): ?>
                                                    <?php $count = $data['periods'][$period]; ?>
                                                    <?php if($count > 0): ?>
                                                        <a href="?subject_id=<?= $subj_id ?>&period=<?= $period ?>" class="period-btn active-folder">
                                                            <span><i class="fas fa-folder-open me-2 text-warning"></i><?= $period ?></span>
                                                            <span class="badge-count"><?= $count ?></span>
                                                        </a>
                                                    <?php else: ?>
                                                        <div class="period-btn" style="opacity: 0.6; cursor: not-allowed;" title="No submissions yet">
                                                            <span><i class="fas fa-folder me-2 text-muted"></i><?= $period ?></span>
                                                            <span class="badge-count">0</span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                    <?php endif; ?>

                <?php else: ?>
                    <!-- ================= TABLE VIEW ================= -->
                    <div class="saas-card p-0 overflow-hidden">
                        <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-3 border-bottom border-light">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">
                                    <i class="fas fa-folder-open text-warning me-2"></i> <?= htmlspecialchars($active_subject_name) ?> - <?= $selected_period ?> Records
                                </h6>
                            </div>
                            
                            <div class="search-wrapper m-0">
                                <i class="fas fa-search text-muted"></i>
                                <input type="text" id="searchGrades" placeholder="Search student name or number...">
                            </div>
                        </div>

                        <div class="table-responsive m-0 border-0">
                            <table class="table-bordered excel-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="sticky-col-1 border-bottom-0">Student No.</th>
                                        <th rowspan="2" class="sticky-col-2 border-bottom-0" style="padding-left: 15px;">Student Name</th>

                                        <th colspan="2" style="background-color: #F1F5F9;">Behavior</th>
                                        <th colspan="2">Attendance</th>
                                        <th colspan="4" style="background-color: #F1F5F9;">Participation</th>
                                        <th colspan="5">Quizzes</th>
                                        <th colspan="4" style="background-color: #F1F5F9;">Activities</th>
                                        <th colspan="2">Project</th>
                                        <th colspan="2" style="background-color: #F1F5F9;">Exam</th>

                                        <th rowspan="2" style="background-color: #0F172A; color: #fff; border-bottom: none;">Grade</th>
                                        <th rowspan="2" style="background-color: #0F172A; color: #fff; border-bottom: none;">Remarks</th>
                                        <th rowspan="2">Lock</th>
                                        <th rowspan="2">Request Status</th>
                                        <th rowspan="2">Action</th>
                                    </tr>
                                    <tr>
                                        <th style="background-color: #F8FAFC;">Raw</th><th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        <th>Raw</th><th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #F8FAFC;">P1</th><th style="background-color: #F8FAFC;">P2</th><th style="background-color: #F8FAFC;">P3</th><th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #F8FAFC;">A1</th><th style="background-color: #F8FAFC;">A2</th><th style="background-color: #F8FAFC;">A3</th><th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                        <th>Raw</th><th style="background-color: #F8FAFC; color: #0F172A;">EQ</th>
                                        <th style="background-color: #F8FAFC;">Raw</th><th style="background-color: #E2E8F0; color: #0F172A;">EQ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                        <?php 
                                        while ($row = mysqli_fetch_assoc($result)): 
                                            $safe_num = htmlspecialchars($row['student_number']);
                                            $safe_name = htmlspecialchars($row['student_name']);
                                            $search_data = strtolower($safe_num . ' ' . $safe_name);
                                        ?>
                                            <tr class="grade-row" data-search="<?= $search_data ?>">
                                                <td class="sticky-col-1 text-muted" style="font-family: monospace; font-size: 12.5px;"><?= $safe_num; ?></td>
                                                <td class="sticky-col-2 fw-bold text-dark text-truncate" style="padding-left: 15px;"><?= $safe_name; ?></td>

                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['behavior']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['behavior_eq']); ?></td>

                                                <td><?= htmlspecialchars((string) $row['attendance']); ?></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['attendance_eq']); ?></td>

                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['participation1']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['participation2']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['participation3']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['participation_eq']); ?></td>

                                                <td><?= htmlspecialchars((string) $row['quiz1']); ?></td>
                                                <td><?= htmlspecialchars((string) $row['quiz2']); ?></td>
                                                <td><?= htmlspecialchars((string) $row['quiz3']); ?></td>
                                                <td><?= htmlspecialchars((string) $row['quiz4']); ?></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['quiz_eq']); ?></td>

                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['activity1']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['activity2']); ?></td>
                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['activity3']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['activity_eq']); ?></td>

                                                <td><?= htmlspecialchars((string) $row['project']); ?></td>
                                                <td style="background-color: #F8FAFC; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['project_eq']); ?></td>

                                                <td style="background-color: #F8FAFC;"><?= htmlspecialchars((string) $row['exam']); ?></td>
                                                <td style="background-color: #F1F5F9; font-weight: 700; color: #475569;"><?= htmlspecialchars((string) $row['exam_eq']); ?></td>

                                                <td style="background-color: #0F172A; color: #fff; font-size: 14.5px; font-weight: 700; border-color: #334155;"><?= htmlspecialchars((string) $row['computed_grade']); ?></td>
                                                <td style="background-color: #0F172A; border-color: #334155;">
                                                    <?php if (($row['remarks'] ?? '') === 'PASSED'): ?>
                                                        <span class="badge bg-success" style="padding: 5px 8px; border-radius: 4px;">PASSED</span>
                                                    <?php elseif (($row['remarks'] ?? '') === 'FAILED'): ?>
                                                        <span class="badge bg-danger" style="padding: 5px 8px; border-radius: 4px;">FAILED</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars((string) $row['remarks']); ?></span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ((int) $row['is_locked'] === 1): ?>
                                                        <i class="fas fa-lock text-danger" title="Locked"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-lock-open text-secondary" title="Open"></i>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($row['request_status'] === 'Pending'): ?>
                                                        <span class="badge-soft badge-warning-soft"><i class="fas fa-hourglass-half me-1"></i> Pending</span>
                                                    <?php elseif ($row['request_status'] === 'Approved'): ?>
                                                        <span class="badge-soft badge-success-soft"><i class="fas fa-check-circle me-1"></i> Approved</span>
                                                    <?php elseif ($row['request_status'] === 'Rejected'): ?>
                                                        <span class="badge-soft badge-danger-soft"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="font-size: 12px; font-style: italic;">No Request</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ((int) $row['is_locked'] === 1 && empty($row['request_status']) && !empty($row['grade_id'])): ?>
                                                        <a href="request_unlock.php?grade_id=<?= (int) $row['grade_id']; ?>" class="btn-unlock shadow-sm">
                                                            <i class="fas fa-key me-1"></i> Request Unlock
                                                        </a>
                                                    <?php elseif ($row['request_status'] === 'Pending'): ?>
                                                        <span class="text-muted" style="font-size: 12.5px;"><i class="fas fa-spinner fa-spin me-1"></i> Waiting</span>
                                                    <?php elseif ($row['request_status'] === 'Approved'): ?>
                                                        <span class="text-success fw-bold" style="font-size: 12.5px;"><i class="fas fa-check me-1"></i> Unlocked</span>
                                                    <?php elseif ($row['request_status'] === 'Rejected'): ?>
                                                        <span class="text-danger fw-bold" style="font-size: 12.5px;"><i class="fas fa-times me-1"></i> Denied</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="31" class="text-center text-muted py-5">
                                                <i class="fas fa-folder-open text-secondary mb-3" style="font-size: 32px; opacity: 0.5;"></i><br>
                                                No submitted grade records found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr id="noLogsMessage" style="display: none;">
                                        <td colspan="31" class="text-center py-5 text-muted">
                                            <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                            No records match your search.
                                        </td>
                                    </tr>
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
    // Trigger Success/Error Messages from backend
    <?= $swal_script; ?>

    // LIVE SEARCH LOGIC (Only runs when table is visible)
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchGrades");
        const rows = document.querySelectorAll(".grade-row");
        const noLogsMessage = document.getElementById("noLogsMessage");

        if (searchInput) {
            searchInput.addEventListener("input", function() {
                const query = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                rows.forEach(row => {
                    const searchData = row.getAttribute("data-search");
                    if (searchData.includes(query)) {
                        row.style.display = "";
                        visibleCount++;
                    } else {
                        row.style.display = "none";
                    }
                });

                if (noLogsMessage) {
                    noLogsMessage.style.display = (visibleCount === 0) ? "" : "none";
                }
            });
        }
    });
</script>

<?php
if (isset($stmt) && $stmt) {
    mysqli_stmt_close($stmt);
}
include '../includes/footer.php';
?>