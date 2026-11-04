<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$current_page = basename($_SERVER['PHP_SELF']);
$swal_script = ''; // Para sa SweetAlert

// DELETE STUDENT
if (isset($_GET['delete'])) {
    $student_id = (int) $_GET['delete'];

    if ($student_id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM students WHERE student_id = ?");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $student_id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: students.php?msg=deleted");
                exit;
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: students.php?msg=error");
        exit;
    }
}

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Student record has been removed successfully.', showConfirmButton: false, timer: 2000 });";
    } elseif ($_GET['msg'] === 'added') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Success!', text: 'New student added successfully.', showConfirmButton: false, timer: 2000 });";
    } elseif ($_GET['msg'] === 'edited') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Updated!', text: 'Student record has been updated successfully.', showConfirmButton: false, timer: 2000 });";
    } elseif ($_GET['msg'] === 'error') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong while processing your request.' });";
    }
}

// FETCH STUDENTS (IDINAGDAG NA NATIN ANG COURSE, GENDER, PHONE, AT ADDRESS)
$result = mysqli_query($conn, "SELECT student_id, student_number, full_name, gender, course, year_level, semester, phone, address FROM students ORDER BY full_name ASC");

include '../includes/header.php';
?>

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

    /* BUTTONS */
    .btn-create { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 8px 20px; border: none; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); white-space: nowrap; }
    .btn-create:hover { background-color: #0036cc; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    
    .btn-edit { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #BFDBFE; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-edit:hover { background-color: #3B82F6; color: white; }

    .btn-delete { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; }
    .btn-delete:hover { background-color: #EF4444; color: white; }

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 320px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
    
    /* NUMBER BADGE */
    .student-number-badge { font-family: 'Courier New', monospace; font-size: 13px; background-color: #F8FAFC; border: 1px solid #CBD5E1; color: #475569; padding: 4px 8px; border-radius: 6px; font-weight: 600; }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        
        /* Pinanatiling visible ang search bar sa mobile */
        .search-wrapper { width: 100%; margin-top: 10px; }
        .table-header-container { flex-direction: column; align-items: flex-start !important; }
        
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        .btn-create { font-size: 13px; padding: 6px 14px; }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10 w-100-mobile">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <!-- Mobile Hamburger Button para mag-toggle ng sidebar -->
                        <button class="btn btn-light d-md-none me-2 border shadow-sm" onclick="toggleMobileSidebar()" style="border-radius: 8px;">
                            <i class="fas fa-bars text-dark"></i>
                        </button>
                        <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                            Home <span class="mx-1">></span> User Management <span class="mx-1">></span> <strong class="text-dark">Student Records</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Student Records</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Manage and monitor enrolled students in the system.</p>
                    </div>
                    <div>
                        <a href="add_student.php" class="btn-create"><i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">Add New Student</span><span class="d-inline d-sm-none">Add Student</span></a>
                    </div>
                </div>

                <div class="saas-card">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 table-header-container">
                        <h6 class="fw-bold mb-0 text-dark w-100 w-md-auto"><i class="fas fa-user-graduate text-primary me-2"></i> Student Directory</h6>
                        
                        <div class="search-wrapper">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchStudent" placeholder="Search name, course, or address...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 30%;">Student Profile</th>
                                    <th style="width: 15%;">Student Number</th>
                                    <th style="width: 20%;">Course & Year</th>
                                    <th style="width: 10%;">Gender</th>
                                    <th style="width: 20%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $count = 1; 
                                    $avatar_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];
                                    
                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $safe_name = htmlspecialchars($row['full_name']);
                                        $safe_num = htmlspecialchars($row['student_number']);
                                        $safe_gender = htmlspecialchars($row['gender'] ?? 'N/A');
                                        $safe_course = htmlspecialchars($row['course'] ?? 'No Course');
                                        $safe_year = htmlspecialchars($row['year_level']);
                                        $safe_sem = htmlspecialchars($row['semester']);
                                        $safe_address = htmlspecialchars($row['address'] ?? '');
                                        
                                        $initial = strtoupper(substr($safe_name, 0, 1));
                                        $color = $avatar_colors[$row['student_id'] % count($avatar_colors)];
                                        
                                        // Include all fields in search
                                        $search_data = strtolower($safe_name . ' ' . $safe_num . ' ' . $safe_course . ' ' . $safe_year . ' ' . $safe_sem . ' ' . $safe_address . ' ' . $safe_gender);
                                    ?>
                                        <tr class="student-row" data-search="<?= $search_data ?>">
                                            <td class="text-muted text-center"><?= $count++; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="user-avatar" style="background-color: <?= $color ?>;">
                                                        <?= $initial ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 14px;"><?= $safe_name; ?></div>
                                                        <div class="text-muted text-truncate" style="font-size: 12px; max-width: 200px;" title="<?= $safe_address ?>">
                                                            <?php if (!empty($safe_address)): ?>
                                                                <i class="fas fa-map-marker-alt me-1"></i> <?= $safe_address; ?>
                                                            <?php else: ?>
                                                                <span class="fst-italic opacity-50">No address provided</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <span class="student-number-badge">
                                                    <?= $safe_num; ?>
                                                </span>
                                            </td>
                                            
                                            <td>
                                                <div class="fw-bold text-dark text-truncate" style="font-size: 13px; max-width: 180px;" title="<?= $safe_course ?>"><?= $safe_course; ?></div>
                                                <div class="text-muted" style="font-size: 11.5px; font-weight: 500;">
                                                    <?= $safe_year; ?> <span class="mx-1">•</span> <?= $safe_sem; ?>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <span class="badge-soft"><?= $safe_gender; ?></span>
                                            </td>
                                            
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2 align-items-center">
                                                    <a href="edit_student.php?id=<?= (int) $row['student_id']; ?>" class="btn-edit shadow-sm">
                                                        <i class="fas fa-edit me-1"></i> Edit
                                                    </a>

                                                    <button type="button" class="btn btn-delete shadow-sm" 
                                                            onclick="confirmDeletion(<?= (int) $row['student_id']; ?>, '<?= addslashes($safe_name); ?>')">
                                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-user-slash fs-2 mb-3 text-secondary"></i><br>
                                            No students found in the system.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                        No students match your search.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // Trigger Success/Error Messages
    <?= $swal_script; ?>

    // Professional Delete Confirmation
    function confirmDeletion(studentId, studentName) {
        Swal.fire({
            title: 'Delete Student?',
            text: "Are you sure you want to remove " + studentName + " from the records? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444', 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: '<i class="fas fa-trash-alt"></i> Yes, delete student',
            cancelButtonText: 'Cancel',
            reverseButtons: true 
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                window.location.href = 'students.php?delete=' + studentId;
            }
        });
    }

    // LIVE SEARCH SCRIPT (Includes Course, Address, Gender)
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchStudent");
        const rows = document.querySelectorAll(".student-row");
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

<?php include '../includes/footer.php'; ?>