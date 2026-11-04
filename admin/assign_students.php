<?php
session_start();

// Ipinaalam sa IDE kung saan galing ang $conn para iwas red line
/** @var mysqli $conn */
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = isset($_SESSION['full_name']) ? htmlspecialchars((string)$_SESSION['full_name'], ENT_QUOTES) : 'Administrator';
$current_page = basename($_SERVER['PHP_SELF']);
$swal_script = ''; 

$selected_subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$assigned_stmt = null; 
$available_stmt = null;

// ASSIGN STUDENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_student'])) {
    $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
    $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;

    if ($student_id <= 0 || $subject_id <= 0) {
        header("Location: assign_students.php?subject_id={$subject_id}&msg=invalid");
        exit;
    }

    $check_stmt = mysqli_prepare($conn, "SELECT student_subject_id FROM student_subjects WHERE student_id = ? AND subject_id = ? LIMIT 1");

    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $subject_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            mysqli_stmt_close($check_stmt);
            header("Location: assign_students.php?subject_id={$subject_id}&msg=duplicate");
            exit;
        }
        mysqli_stmt_close($check_stmt);
    }

    $insert_stmt = mysqli_prepare($conn, "INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");

    if ($insert_stmt) {
        mysqli_stmt_bind_param($insert_stmt, "ii", $student_id, $subject_id);

        if (mysqli_stmt_execute($insert_stmt)) {
            mysqli_stmt_close($insert_stmt);
            header("Location: assign_students.php?subject_id={$subject_id}&msg=assigned");
            exit;
        }
        mysqli_stmt_close($insert_stmt);
    }

    header("Location: assign_students.php?subject_id={$subject_id}&msg=error");
    exit;
}

// REMOVE ASSIGNED STUDENT
if (isset($_GET['remove'])) {
    $remove_id = (int) $_GET['remove'];
    $subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;

    if ($remove_id > 0 && $subject_id > 0) {
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM student_subjects WHERE student_subject_id = ?");

        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "i", $remove_id);

            if (mysqli_stmt_execute($delete_stmt)) {
                mysqli_stmt_close($delete_stmt);
                header("Location: assign_students.php?subject_id={$subject_id}&msg=removed");
                exit;
            }
            mysqli_stmt_close($delete_stmt);
        }
    }
    header("Location: assign_students.php?subject_id={$subject_id}&msg=error");
    exit;
}

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'assigned') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Assigned!', text: 'Student assigned to subject successfully.', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'removed') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Removed!', text: 'Assigned student removed successfully.', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'duplicate') {
        $swal_script = "Swal.fire({ icon: 'warning', title: 'Duplicate!', text: 'This student is already assigned to this subject.', customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'invalid') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Invalid!', text: 'Please select a valid student and subject.', customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'error') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong. Please try again.', customClass: { popup: 'rounded-20' } });";
    }
}

// SUBJECTS (For Dropdown)
$subjects = mysqli_query($conn, "SELECT subject_id, subject_code, subject_name, year_level, semester FROM subjects ORDER BY subject_name ASC");

$subject_row = null;
$assigned_students = false;
$available_students = false;
$total_enrolled = 0;

// KUNG MAY NA-SELECT NA SUBJECT
if ($selected_subject_id > 0) {
    // 1. Get Subject details
    $subject_stmt = mysqli_prepare($conn, "SELECT subject_id, subject_code, subject_name, year_level, semester FROM subjects WHERE subject_id = ? LIMIT 1");
    if ($subject_stmt) {
        mysqli_stmt_bind_param($subject_stmt, "i", $selected_subject_id);
        mysqli_stmt_execute($subject_stmt);
        $subject_result = mysqli_stmt_get_result($subject_stmt);
        if ($subject_result) {
            $subject_row = mysqli_fetch_assoc($subject_result);
        }
        mysqli_stmt_close($subject_stmt);
    }

    // 2. Get Assigned Students
    $assigned_stmt = mysqli_prepare(
        $conn,
        "SELECT ss.student_subject_id, s.student_id, s.student_number, s.full_name, s.year_level, s.semester 
         FROM student_subjects ss 
         INNER JOIN students s ON ss.student_id = s.student_id 
         WHERE ss.subject_id = ? 
         ORDER BY s.full_name ASC"
    );
    if ($assigned_stmt) {
        mysqli_stmt_bind_param($assigned_stmt, "i", $selected_subject_id);
        mysqli_stmt_execute($assigned_stmt);
        $assigned_students = mysqli_stmt_get_result($assigned_stmt);
        $total_enrolled = mysqli_num_rows($assigned_students); // Set total count
    }

    // 3. ENHANCEMENT: Get AVAILABLE students ONLY (yung mga hindi pa enrolled sa subject na ito)
    $available_stmt = mysqli_prepare(
        $conn,
        "SELECT student_id, student_number, full_name, year_level, semester 
         FROM students 
         WHERE student_id NOT IN (SELECT student_id FROM student_subjects WHERE subject_id = ?) 
         ORDER BY full_name ASC"
    );
    if ($available_stmt) {
        mysqli_stmt_bind_param($available_stmt, "i", $selected_subject_id);
        mysqli_stmt_execute($available_stmt);
        $available_students = mysqli_stmt_get_result($available_stmt);
    }
}

include '../includes/header.php';
?>

<!-- JQUERY (Kailangan para sa Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- SELECT2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* MATCHING SAAS DASHBOARD STYLES */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    
    /* TOP NAVBAR */
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    
    /* CARDS & TABLES */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .table-saas { width: 100%; border-collapse: collapse; }
    .table-saas th { color: #64748B; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px; border-bottom: 1px solid #E2E8F0; background-color: #F8FAFC; }
    .table-saas th:first-child { border-top-left-radius: 8px; }
    .table-saas th:last-child { border-top-right-radius: 8px; }
    .table-saas td { padding: 15px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; font-size: 14px; color: #334155; font-weight: 500; }
    .table-saas tbody tr { transition: 0.2s; }
    .table-saas tbody tr:hover { background-color: #F8FAFC; }

    /* AVATARS & BADGES */
    .user-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }
    .student-number-badge { font-family: 'Courier New', monospace; font-size: 13px; background-color: #F8FAFC; border: 1px solid #CBD5E1; color: #475569; padding: 4px 8px; border-radius: 6px; font-weight: 600; }

    /* SUBJECT INFO BOX */
    .subject-info-box { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; border-left: 5px solid #0047FF; }

    /* BUTTONS */
    .btn-create { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 8px 20px; border: none; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); }
    .btn-create:hover { background-color: #0036cc; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    .btn-success-custom { background-color: #10B981; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 8px 20px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
    .btn-success-custom:hover { background-color: #059669; color: white; transform: translateY(-1px); }
    .btn-delete { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; }
    .btn-delete:hover { background-color: #EF4444; color: white; }

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
    
    /* CUSTOM SELECT2 STYLING PARA BUMAGAY SA SAAS THEME */
    .select2-container--default .select2-selection--single {
        height: 44px;
        border-radius: 8px;
        border: 1px solid #E2E8F0;
        background-color: #fff;
        display: flex;
        align-items: center;
        padding: 0 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155;
        font-size: 14px;
        line-height: normal;
        padding-left: 5px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 10px;
    }
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #0047FF;
        box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1);
    }
    .select2-dropdown {
        border-color: #E2E8F0;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .select2-results__option { font-size: 14px; color: #334155; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #0047FF;
        color: white;
    }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        
        /* Pinanatiling visible ang search bar at isinasaayos ang layout sa table header */
        .search-wrapper { width: 100%; margin-top: 10px; }
        .table-header-container { flex-direction: column; align-items: flex-start !important; }
        
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        
        /* Adjust info box layout for smaller screens */
        .subject-info-box .border-start { border-left: none !important; padding-left: 0 !important; margin-top: 15px; border-top: 2px solid #E2E8F0; padding-top: 10px;}
        
        .btn-create, .btn-success-custom { font-size: 13px; padding: 6px 14px; }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
      
     <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10 w-100-mobile">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <!-- Mobile Hamburger Button -->
                        <button class="btn btn-light d-md-none me-2 border shadow-sm" onclick="toggleMobileSidebar()" style="border-radius: 8px;">
                            <i class="fas fa-bars text-dark"></i>
                        </button>
                        <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                            Home <span class="mx-1">></span> Subject Mgmt <span class="mx-1">></span> <strong class="text-dark">Assign Students</strong>
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

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Assign Students</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Enroll students into their respective subjects.</p>
                    </div>
                </div>

                <div class="saas-card mb-4">
                    <form method="GET" id="loadSubjectForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-bold text-dark" style="font-size: 13px;"><i class="fas fa-search text-primary me-1"></i> Select Subject to Manage</label>
                                <!-- Ginawang select2-searchable ang class -->
                                <select name="subject_id" class="form-control select2-searchable w-100" required>
                                    <option value="">-- Type to search subject... --</option>
                                    <?php if ($subjects && mysqli_num_rows($subjects) > 0): ?>
                                        <?php while ($subject = mysqli_fetch_assoc($subjects)): ?>
                                            <option value="<?= (int) $subject['subject_id']; ?>" <?= $selected_subject_id === (int) $subject['subject_id'] ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'], ENT_QUOTES); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" id="loadBtn" class="btn-create w-100">
                                    <i class="fas fa-spinner me-1" id="loadIcon" style="display: none;"></i> Load Subject
                                </button>
                                <a href="assign_students.php" class="btn btn-light border w-50 text-center text-decoration-none text-dark d-flex align-items-center justify-content-center" style="border-radius: 8px; font-size: 14px; font-weight: 500;">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($selected_subject_id > 0 && $subject_row): ?>
                    
                    <div class="saas-card mb-4">
                        <h6 class="mb-3 fw-bold text-dark"><i class="fas fa-info-circle text-primary me-2"></i> Subject Overview</h6>
                        <div class="subject-info-box">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="text-muted me-2" style="font-size: 13px; width: 60px;">Code:</span> 
                                        <span class="subject-code-badge bg-white fs-6 border-0 text-dark p-0"><?= htmlspecialchars((string)$subject_row['subject_code'], ENT_QUOTES); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="text-muted me-2" style="font-size: 13px; width: 60px;">Name:</span> 
                                        <strong class="text-dark fs-6"><?= htmlspecialchars((string)$subject_row['subject_name'], ENT_QUOTES); ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6 border-start border-2 ps-4">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="text-muted me-2" style="font-size: 13px; width: 90px;">Year Level:</span> 
                                        <span class="badge-soft"><?= htmlspecialchars((string)$subject_row['year_level'], ENT_QUOTES); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="text-muted me-2" style="font-size: 13px; width: 90px;">Semester:</span> 
                                        <span class="badge-soft"><?= htmlspecialchars((string)$subject_row['semester'], ENT_QUOTES); ?></span>
                                    </div>
                                    <!-- ENHANCEMENT: Total Enrolled Counter -->
                                    <div class="d-flex align-items-center mt-2">
                                        <span class="text-muted me-2" style="font-size: 13px; width: 90px;">Total Enrolled:</span> 
                                        <span class="badge bg-primary text-white px-2 py-1" style="border-radius: 6px; font-size: 12px;"><?= $total_enrolled; ?> Student(s)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="saas-card mb-4 border-start border-4 border-success">
                        <h6 class="mb-3 fw-bold text-dark"><i class="fas fa-user-plus text-success me-2"></i> Enroll a Student</h6>
                        <form method="POST" id="assignForm">
                            <input type="hidden" name="subject_id" value="<?= (int) $selected_subject_id; ?>">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <!-- Ginawang select2-searchable ang class at Unassigned Students lang ang ipapakita -->
                                    <select name="student_id" class="form-control select2-searchable w-100" required>
                                        <option value="">-- Type to search student... --</option>
                                        <?php if ($available_students && mysqli_num_rows($available_students) > 0): ?>
                                            <?php while ($student = mysqli_fetch_assoc($available_students)): ?>
                                                <option value="<?= (int) $student['student_id']; ?>">
                                                    <?= htmlspecialchars($student['student_number'] . ' - ' . $student['full_name'], ENT_QUOTES); ?> 
                                                    (<?= htmlspecialchars((string)$student['year_level'], ENT_QUOTES); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <option value="" disabled>All students are currently enrolled in this subject.</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="assign_student" id="assignBtn" class="btn-success-custom w-100" <?= ($available_students && mysqli_num_rows($available_students) == 0) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check me-1" id="assignIcon"></i> Enroll Student
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="saas-card">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 table-header-container">
                            <h6 class="fw-bold mb-0 text-dark w-100 w-md-auto"><i class="fas fa-users text-primary me-2"></i> Enrolled Class List</h6>
                            
                            <div class="search-wrapper">
                                <i class="fas fa-search text-muted"></i>
                                <input type="text" id="searchAssigned" placeholder="Search student name or number...">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table-saas">
                                <thead>
                                    <tr>
                                        <th style="width: 5%; text-align: center;">#</th>
                                        <th style="width: 35%;">Student Profile</th>
                                        <th style="width: 20%;">Student Number</th>
                                        <th style="width: 13%;">Year Level</th>
                                        <th style="width: 12%;">Semester</th>
                                        <th style="width: 15%; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($assigned_students && mysqli_num_rows($assigned_students) > 0): ?>
                                        <?php 
                                        $count = 1; 
                                        $avatar_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];

                                        while ($row = mysqli_fetch_assoc($assigned_students)): 
                                            $safe_name = htmlspecialchars((string)$row['full_name'], ENT_QUOTES);
                                            $safe_num = htmlspecialchars((string)$row['student_number'], ENT_QUOTES);
                                            $safe_year = htmlspecialchars((string)$row['year_level'], ENT_QUOTES);
                                            $safe_sem = htmlspecialchars((string)$row['semester'], ENT_QUOTES);
                                            $initial = strtoupper(substr($safe_name, 0, 1));
                                            
                                            // Random color assignment
                                            $color = $avatar_colors[$row['student_id'] % count($avatar_colors)];
                                            
                                            $search_data = strtolower($safe_name . ' ' . $safe_num);
                                        ?>
                                            <tr class="assigned-row" data-search="<?= $search_data ?>">
                                                <td class="text-muted text-center"><?= $count++; ?></td>
                                                
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="user-avatar" style="background-color: <?= $color ?>;">
                                                            <?= $initial ?>
                                                        </div>
                                                        <div class="fw-bold text-dark" style="font-size: 14px;">
                                                            <?= $safe_name; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <span class="student-number-badge">
                                                        <?= $safe_num; ?>
                                                    </span>
                                                </td>
                                                
                                                <td><span class="badge-soft"><?= $safe_year; ?></span></td>
                                                <td><span class="badge-soft"><?= $safe_sem; ?></span></td>
                                                
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-delete shadow-sm" 
                                                            onclick="confirmRemoval(<?= (int) $row['student_subject_id']; ?>, '<?= addslashes($safe_name); ?>', <?= (int) $selected_subject_id; ?>)">
                                                        <i class="fas fa-user-minus"></i> Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="fas fa-folder-open text-secondary mb-3" style="font-size: 32px;"></i><br>
                                                No students enrolled in this subject yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <tr id="noLogsMessage" style="display: none;">
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                            No enrolled student matches your search.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($selected_subject_id > 0): ?>
                    <div class="alert alert-warning shadow-sm mt-4 border-0 border-start border-4 border-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Selected subject was not found in the database.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Select2 (Searchable Dropdown)
    $(document).ready(function() {
        $('.select2-searchable').select2({
            width: '100%',
            placeholder: function(){
                $(this).data('placeholder');
            }
        });
    });

    // Trigger Success/Error Messages from PHP
    <?php if ($swal_script !== ''): ?>
        <?= $swal_script; ?>
    <?php endif; ?>

    // Professional Remove Confirmation
    function confirmRemoval(removeId, studentName, subjectId) {
        Swal.fire({
            title: 'Drop Student?',
            text: "Are you sure you want to drop " + studentName + " from this class? This will also affect their grades if any.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444', 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: '<i class="fas fa-user-minus"></i> Yes, drop student',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: { popup: 'rounded-20' }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                window.location.href = 'assign_students.php?subject_id=' + subjectId + '&remove=' + removeId;
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        
        // --- 1. LIVE SEARCH FOR ASSIGNED STUDENTS TABLE ---
        const searchInput = document.getElementById("searchAssigned");
        const rows = document.querySelectorAll(".assigned-row");
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

        // --- 2. LOADING EFFECT PARA SA LOAD SUBJECT BUTTON ---
        const loadForm = document.getElementById("loadSubjectForm");
        const loadBtn = document.getElementById("loadBtn");
        const loadIcon = document.getElementById("loadIcon");

        if (loadForm && loadBtn && loadIcon) {
            loadForm.addEventListener("submit", function() {
                loadBtn.classList.add("disabled"); 
                loadIcon.style.display = "inline-block";
                loadIcon.classList.add("fa-spin"); 
                loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
            });
        }

        // --- 3. LOADING EFFECT PARA SA ASSIGN STUDENT BUTTON ---
        const assignForm = document.getElementById("assignForm");
        const assignBtn = document.getElementById("assignBtn");
        const assignIcon = document.getElementById("assignIcon");

        if (assignForm && assignBtn && assignIcon) {
            assignForm.addEventListener("submit", function() {
                assignBtn.classList.add("disabled"); 
                assignIcon.classList.remove("fa-check"); 
                assignIcon.classList.add("fa-spinner", "fa-spin"); 
                assignBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Enrolling...';
            });
        }
    });
</script>

<?php
if ($assigned_stmt !== null) {
    mysqli_stmt_close($assigned_stmt);
}
if ($available_stmt !== null) {
    mysqli_stmt_close($available_stmt);
}
include '../includes/footer.php';
?>