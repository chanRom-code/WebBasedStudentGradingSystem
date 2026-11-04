<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = '';

// KUNIN ANG STUDENT ID MULA SA URL
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($edit_id <= 0) {
    header("Location: students.php?msg=error");
    exit;
}

// FETCH CURRENT STUDENT DATA
$stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE student_id = ?");
mysqli_stmt_bind_param($stmt, "i", $edit_id);
mysqli_stmt_execute($stmt);
$student_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$student_data) {
    header("Location: students.php?msg=error");
    exit;
}

// UPDATE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $student_number = trim($_POST['student_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $allowed_year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    $allowed_semesters = ['1st Semester', '2nd Semester'];

    if ($student_number === '' || $full_name === '' || $gender === '' || $course === '' || $year_level === '' || $semester === '') {
        $message = 'Student Number, Full Name, Gender, Course, Year Level, and Semester are required.';
        $message_type = 'danger';
    } elseif (!in_array($year_level, $allowed_year_levels, true)) {
        $message = 'Invalid year level selected.';
        $message_type = 'danger';
    } elseif (!in_array($semester, $allowed_semesters, true)) {
        $message = 'Invalid semester selected.';
        $message_type = 'danger';
    } else {
        // Check duplicate student number (EXCEPT the current student being edited)
        $check_stmt = mysqli_prepare($conn, "SELECT student_id FROM students WHERE student_number = ? AND student_id != ? LIMIT 1");

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "si", $student_number, $edit_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $message = 'Student number is already taken by another student.';
                $message_type = 'warning';
            } else {
                // Update Query
                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE students SET student_number = ?, full_name = ?, gender = ?, course = ?, year_level = ?, semester = ?, phone = ?, address = ? WHERE student_id = ?"
                );

                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "ssssssssi", $student_number, $full_name, $gender, $course, $year_level, $semester, $phone, $address, $edit_id);

                    if (mysqli_stmt_execute($update_stmt)) {
                        mysqli_stmt_close($update_stmt);
                        mysqli_stmt_close($check_stmt);
                        header("Location: students.php?msg=edited");
                        exit;
                    } else {
                        $message = 'Failed to update student record.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Failed to prepare update query.';
                    $message_type = 'danger';
                }
            }
            mysqli_stmt_close($check_stmt);
        }
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* CONTENT & TOP NAVBAR (SAAS STYLE) */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }

    /* CARDS */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; }
    
    /* FORMS */
    .form-label-custom { font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-control-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; }
    .form-control-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    .form-select-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; cursor: pointer;}
    .form-select-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    
    /* BUTTONS */
    .btn-save { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 24px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); display: inline-flex; align-items: center; justify-content: center; }
    .btn-save:hover { background-color: #0036cc; color: white; transform: translateY(-2px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    
    .btn-cancel { background-color: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 600; font-size: 14px; padding: 10px 24px; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;}
    .btn-cancel:hover { background-color: #E2E8F0; color: #0F172A; }

    .alert-custom { border-radius: 12px; font-size: 14px; font-weight: 500; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
      
     <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> User Management <span class="mx-1">></span> Student Records <span class="mx-1">></span> <strong class="text-dark">Edit Student</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Edit Student Details</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Update information for <strong class="text-dark"><?= htmlspecialchars($student_data['full_name']) ?></strong>.</p>
                    </div>
                    <a href="students.php" class="btn-cancel shadow-sm"><i class="fas fa-arrow-left me-2"></i> Back to Students</a>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= htmlspecialchars($message_type); ?> alert-custom shadow-sm border-0 border-start border-4 border-<?= htmlspecialchars($message_type); ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="saas-card">
                    <h6 class="fw-bold text-dark mb-4"><i class="fas fa-user-edit text-primary me-2"></i> Update Information</h6>
                    
                    <form method="POST" id="editStudentForm">
                        <div class="row g-4">
                            
                            <div class="col-md-4">
                                <label class="form-label-custom">Student Number <span class="text-danger">*</span></label>
                                <input type="text" name="student_number" class="form-control-custom w-100" value="<?= htmlspecialchars($student_data['student_number']); ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control-custom w-100" value="<?= htmlspecialchars($student_data['full_name']); ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select-custom w-100" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male" <?= ($student_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?= ($student_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?= ($student_data['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other / Prefer not to say</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Course <span class="text-danger">*</span></label>
                                <select name="course" class="form-select-custom w-100" required>
                                    <option value="">-- Select Course --</option>
                                    <option value="BS Information Technology" <?= ($student_data['course'] ?? '') === 'BS Information Technology' ? 'selected' : ''; ?>>BS Information Technology</option>
                                    <option value="BS Computer Science" <?= ($student_data['course'] ?? '') === 'BS Computer Science' ? 'selected' : ''; ?>>BS Computer Science</option>
                                    <option value="BS Information Systems" <?= ($student_data['course'] ?? '') === 'BS Information Systems' ? 'selected' : ''; ?>>BS Information Systems</option>
                                    <option value="BS Business Administration" <?= ($student_data['course'] ?? '') === 'BS Business Administration' ? 'selected' : ''; ?>>BS Business Administration</option>
                                    <option value="BS Hospitality Management" <?= ($student_data['course'] ?? '') === 'BS Hospitality Management' ? 'selected' : ''; ?>>BS Hospitality Management</option>
                                    <option value="BS Tourism Management" <?= ($student_data['course'] ?? '') === 'BS Tourism Management' ? 'selected' : ''; ?>>BS Tourism Management</option>
                                    <option value="Associate in Computer Technology" <?= ($student_data['course'] ?? '') === 'Associate in Computer Technology' ? 'selected' : ''; ?>>Associate in Computer Technology</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Year Level <span class="text-danger">*</span></label>
                                <select name="year_level" class="form-select-custom w-100" required>
                                    <option value="">-- Select Year Level --</option>
                                    <option value="1st Year" <?= $student_data['year_level'] === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?= $student_data['year_level'] === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?= $student_data['year_level'] === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?= $student_data['year_level'] === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Semester <span class="text-danger">*</span></label>
                                <select name="semester" class="form-select-custom w-100" required>
                                    <option value="">-- Select Semester --</option>
                                    <option value="1st Semester" <?= $student_data['semester'] === '1st Semester' ? 'selected' : ''; ?>>1st Semester</option>
                                    <option value="2nd Semester" <?= $student_data['semester'] === '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Phone Number <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Optional)</span></label>
                                <input type="text" name="phone" class="form-control-custom w-100" value="<?= htmlspecialchars($student_data['phone'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Home Address <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Optional)</span></label>
                                <input type="text" name="address" class="form-control-custom w-100" value="<?= htmlspecialchars($student_data['address'] ?? ''); ?>">
                            </div>
                        </div>

                        <hr class="my-4" style="border-color: #E2E8F0;">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="students.php" class="btn-cancel">Cancel</a>
                            <button type="submit" name="submit" id="saveStudentBtn" class="btn-save">
                                <i class="fas fa-save me-2" id="saveStudentIcon"></i> Update Student
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("editStudentForm");
    const saveBtn = document.getElementById("saveStudentBtn");
    const saveIcon = document.getElementById("saveStudentIcon");

    // Loading effect upon submission
    form.addEventListener("submit", function() {
        saveBtn.classList.add("disabled");
        saveIcon.classList.remove("fa-save");
        saveIcon.classList.add("fa-spinner", "fa-spin");
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
    });
});
</script>

<?php include '../includes/footer.php'; ?>