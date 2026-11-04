<?php
session_start();

// Ipinaalam sa IDE kung saan galing ang $conn para iwas red line
/** @var mysqli $conn */
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = isset($_SESSION['full_name']) ? htmlspecialchars((string)$_SESSION['full_name'], ENT_QUOTES) : 'Administrator';
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = '';

$subject_code = '';
$subject_name = '';
$professor_id = 0;
$year_level = '';
$semester = '';

// Fetch professors for dropdown safely
$professors = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role = 'professor' ORDER BY full_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    // Strict fetching para sa IDE (iwas null coalescing warnings)
    $subject_code = isset($_POST['subject_code']) ? trim((string)$_POST['subject_code']) : '';
    $subject_name = isset($_POST['subject_name']) ? trim((string)$_POST['subject_name']) : '';
    $professor_id = isset($_POST['professor_id']) ? (int) $_POST['professor_id'] : 0;
    $year_level   = isset($_POST['year_level']) ? trim((string)$_POST['year_level']) : '';
    $semester     = isset($_POST['semester']) ? trim((string)$_POST['semester']) : '';

    $allowed_year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    $allowed_semesters = ['1st Semester', '2nd Semester'];

    if ($subject_code === '' || $subject_name === '' || $professor_id <= 0 || $year_level === '' || $semester === '') {
        $message = 'All fields are required.';
        $message_type = 'danger';
    } elseif (!in_array($year_level, $allowed_year_levels, true)) {
        $message = 'Invalid year level selected.';
        $message_type = 'danger';
    } elseif (!in_array($semester, $allowed_semesters, true)) {
        $message = 'Invalid semester selected.';
        $message_type = 'danger';
    } else {
        // Check if professor exists
        $prof_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND role = 'professor' LIMIT 1");

        if ($prof_stmt) {
            mysqli_stmt_bind_param($prof_stmt, "i", $professor_id);
            mysqli_stmt_execute($prof_stmt);
            mysqli_stmt_store_result($prof_stmt);

            if (mysqli_stmt_num_rows($prof_stmt) === 0) {
                $message = 'Selected professor is invalid.';
                $message_type = 'danger';
            } else {
                // Check duplicate subject code
                $check_stmt = mysqli_prepare($conn, "SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");

                if ($check_stmt) {
                    mysqli_stmt_bind_param($check_stmt, "s", $subject_code);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_store_result($check_stmt);

                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $message = 'Subject code already exists.';
                        $message_type = 'warning';
                    } else {
                        $insert_stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO subjects (subject_code, subject_name, professor_id, year_level, semester) VALUES (?, ?, ?, ?, ?)"
                        );

                        if ($insert_stmt) {
                            mysqli_stmt_bind_param($insert_stmt, "ssiss", $subject_code, $subject_name, $professor_id, $year_level, $semester);

                            if (mysqli_stmt_execute($insert_stmt)) {
                                mysqli_stmt_close($insert_stmt);
                                mysqli_stmt_close($check_stmt);
                                mysqli_stmt_close($prof_stmt);
                                header("Location: subjects.php?msg=added");
                                exit;
                            } else {
                                $message = 'Failed to save subject.';
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'Failed to prepare insert query.';
                            $message_type = 'danger';
                        }
                    }

                    mysqli_stmt_close($check_stmt);
                } else {
                    $message = 'Failed to prepare duplicate check query.';
                    $message_type = 'danger';
                }
            }

            mysqli_stmt_close($prof_stmt);
        } else {
            $message = 'Failed to prepare professor check query.';
            $message_type = 'danger';
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
    
    /* FORMS - In-adjust ang padding para hindi mataba ang dropdowns/inputs */
    .form-label-custom { font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-control-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 10px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; }
    .form-control-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    .form-select-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 10px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; cursor: pointer;}
    .form-select-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    
    /* BUTTONS */
    .btn-save { background-color: #10B981; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 24px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); display: inline-flex; align-items: center; justify-content: center; }
    .btn-save:hover { background-color: #059669; color: white; transform: translateY(-2px); box-shadow: 0 6px 8px rgba(16, 185, 129, 0.3); }
    
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
                        Home <span class="mx-1">></span> Subject Mgmt <span class="mx-1">></span> <strong class="text-dark">Add Subject</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Add New Subject</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Create a new subject and assign it to a professor.</p>
                    </div>
                    <a href="subjects.php" class="btn-cancel shadow-sm"><i class="fas fa-arrow-left me-2"></i> Back to Subjects</a>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES); ?> alert-custom shadow-sm border-0 border-start border-4 border-<?= htmlspecialchars($message_type, ENT_QUOTES); ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($message, ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <div class="saas-card">
                    <h6 class="fw-bold text-dark mb-4"><i class="fas fa-book-open text-primary me-2"></i> Subject Details</h6>
                    
                    <form method="POST" id="addSubjectForm">
                        <div class="row g-4">

                            <div class="col-md-6">
                                <label class="form-label-custom">Subject Code <span class="text-danger">*</span></label>
                                <input type="text" name="subject_code" class="form-control-custom w-100" value="<?= htmlspecialchars($subject_code, ENT_QUOTES); ?>" placeholder="e.g. ITE 314" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Subject Name <span class="text-danger">*</span></label>
                                <input type="text" name="subject_name" class="form-control-custom w-100" value="<?= htmlspecialchars($subject_name, ENT_QUOTES); ?>" placeholder="e.g. Information Management" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Assign Professor <span class="text-danger">*</span></label>
                                <select name="professor_id" class="form-select-custom w-100" required>
                                    <option value="">-- Select Professor --</option>
                                    <?php if ($professors && mysqli_num_rows($professors) > 0): ?>
                                        <?php while ($prof = mysqli_fetch_assoc($professors)): ?>
                                            <option value="<?= (int) $prof['user_id']; ?>" <?= ((int) $professor_id === (int) $prof['user_id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($prof['full_name'], ENT_QUOTES); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Year Level <span class="text-danger">*</span></label>
                                <select name="year_level" class="form-select-custom w-100" required>
                                    <option value="">-- Select Year Level --</option>
                                    <option value="1st Year" <?= $year_level === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?= $year_level === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?= $year_level === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?= $year_level === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Semester <span class="text-danger">*</span></label>
                                <select name="semester" class="form-select-custom w-100" required>
                                    <option value="">-- Select Semester --</option>
                                    <option value="1st Semester" <?= $semester === '1st Semester' ? 'selected' : ''; ?>>1st Semester</option>
                                    <option value="2nd Semester" <?= $semester === '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option>
                                </select>
                            </div>

                        </div>

                        <hr class="my-4" style="border-color: #E2E8F0;">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="subjects.php" class="btn-cancel">Cancel</a>
                            <button type="submit" name="submit" id="saveSubjectBtn" class="btn-save">
                                <i class="fas fa-save me-2" id="saveSubjectIcon"></i> Save Subject
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
    const form = document.getElementById("addSubjectForm");
    const saveBtn = document.getElementById("saveSubjectBtn");
    const saveIcon = document.getElementById("saveSubjectIcon");

    if (form && saveBtn && saveIcon) {
        // Loading effect upon submission
        form.addEventListener("submit", function() {
            saveBtn.classList.add("disabled");
            saveIcon.classList.remove("fa-save");
            saveIcon.classList.add("fa-spinner", "fa-spin");
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>