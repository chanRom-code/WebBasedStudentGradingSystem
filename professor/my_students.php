<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit;
}

$professor_id = (int) $_SESSION['user_id'];
$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Professor');
$current_page = basename($_SERVER['PHP_SELF']);

// KUNIN ANG LAHAT NG ESTUDYANTE NA NAKA-ENROLL SA MGA SUBJECTS NG PROFESSOR NA ITO (KASAMA ANG ADDRESS)
$sql = "
    SELECT 
        s.student_id, 
        s.student_number, 
        s.full_name, 
        s.gender, 
        s.course, 
        s.year_level, 
        s.address,
        sub.subject_code, 
        sub.subject_name
    FROM students s
    JOIN student_subjects ss ON s.student_id = ss.student_id
    JOIN subjects sub ON ss.subject_id = sub.subject_id
    WHERE sub.professor_id = ?
    ORDER BY sub.subject_code ASC, s.full_name ASC
";

$stmt = mysqli_prepare($conn, $sql);
$students_result = false;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $professor_id);
    mysqli_stmt_execute($stmt);
    $students_result = mysqli_stmt_get_result($stmt);
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* MATCHING SAAS DASHBOARD STYLES */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    
    /* CARDS & TABLES */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .table-saas { width: 100%; border-collapse: collapse; }
    
    /* Inayos ang text alignment para mas malinis */
    .table-saas th { color: #64748B; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px; border-bottom: 1px solid #E2E8F0; background-color: #F8FAFC; position: sticky; top: 0; z-index: 10; text-align: left; }
    .table-saas td { padding: 15px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; font-size: 14px; color: #334155; font-weight: 500; text-align: left; }
    .table-saas th.center-col, .table-saas td.center-col { text-align: center; }
    
    .table-saas tbody tr { transition: 0.2s; }
    .table-saas tbody tr:hover { background-color: #F8FAFC; }

    /* AVATARS & BADGES */
    .user-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }
    .student-number-badge { font-family: 'Courier New', monospace; font-size: 13px; background-color: #F8FAFC; border: 1px solid #CBD5E1; color: #475569; padding: 4px 8px; border-radius: 6px; font-weight: 600; }
    .subject-badge { background-color: #EEF2FF; color: #4F46E5; border: 1px solid #C7D2FE; font-size: 10.5px; padding: 4px 8px; border-radius: 6px; font-weight: 700; letter-spacing: 0.5px;}

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 15px; display: flex; align-items: center; width: 350px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #10B981; background-color: #fff; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
    
    .table-responsive { max-height: 65vh; overflow-y: auto; overflow-x: auto; border-radius: 8px; border: 1px solid #E2E8F0; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> My Subjects <span class="mx-1">></span> <strong class="text-dark">Student List</strong>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name_session ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Professor</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name_session, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Student List</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">All students currently enrolled under your assigned subjects.</p>
                    </div>
                </div>

                <div class="saas-card p-0 overflow-hidden">
                    
                    <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-3 border-bottom border-light">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-users text-primary me-2"></i> Enrolled Students Directory</h6>
                        
                        <div class="search-wrapper m-0">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchStudent" placeholder="Search name, number, address or subject...">
                        </div>
                    </div>

                    <div class="table-responsive m-0 border-0">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th class="center-col" style="width: 5%;">#</th>
                                    <th style="width: 30%;">Student Profile</th>
                                    <th style="width: 15%;">Student Number</th>
                                    <th style="width: 25%;">Enrolled Subject</th>
                                    <th style="width: 15%;">Course & Year</th>
                                    <th class="center-col" style="width: 10%;">Gender</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                                    <?php 
                                    $count = 1; 
                                    $avatar_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];
                                    
                                    while ($row = mysqli_fetch_assoc($students_result)): 
                                        $safe_name = htmlspecialchars($row['full_name']);
                                        $safe_num = htmlspecialchars($row['student_number']);
                                        $safe_gender = htmlspecialchars($row['gender'] ?? 'N/A');
                                        $safe_course = htmlspecialchars($row['course'] ?? 'No Course');
                                        $safe_year = htmlspecialchars($row['year_level']);
                                        $safe_address = htmlspecialchars($row['address'] ?? '');
                                        $safe_subject_code = htmlspecialchars($row['subject_code']);
                                        $safe_subject_name = htmlspecialchars($row['subject_name']);
                                        
                                        $initial = strtoupper(substr($safe_name, 0, 1));
                                        $color = $avatar_colors[$row['student_id'] % count($avatar_colors)];
                                        
                                        $search_data = strtolower($safe_name . ' ' . $safe_num . ' ' . $safe_course . ' ' . $safe_subject_code . ' ' . $safe_subject_name . ' ' . $safe_address);
                                    ?>
                                        <tr class="student-row" data-search="<?= $search_data ?>">
                                            <td class="text-muted center-col fw-bold"><?= $count++; ?></td>
                                            
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
                                                <span class="student-number-badge"><?= $safe_num; ?></span>
                                            </td>
                                            
                                            <td>
                                                <div class="text-truncate" style="max-width: 250px;" title="<?= $safe_subject_code . ' - ' . $safe_subject_name ?>">
                                                    <span class="subject-badge mb-1 d-inline-block"><?= $safe_subject_code ?></span><br>
                                                    <span style="font-size: 12.5px; font-weight: 500; color: #475569;"><?= $safe_subject_name ?></span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="fw-bold text-dark text-truncate" style="font-size: 12.5px; max-width: 180px;" title="<?= $safe_course ?>"><?= $safe_course; ?></div>
                                                <div class="text-muted" style="font-size: 11.5px; font-weight: 500;">
                                                    <?= $safe_year; ?>
                                                </div>
                                            </td>
                                            
                                            <td class="center-col">
                                                <span class="badge-soft"><?= $safe_gender; ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-users-slash fs-2 mb-3 text-secondary opacity-50"></i><br>
                                            No students found across your assigned subjects.
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
    // LIVE SEARCH SCRIPT
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

<?php 
if ($stmt) { mysqli_stmt_close($stmt); }
include '../includes/footer.php'; 
?>