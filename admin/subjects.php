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

// DELETE SUBJECT
if (isset($_GET['delete'])) {
    $subject_id = (int) $_GET['delete'];

    if ($subject_id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM subjects WHERE subject_id = ?");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $subject_id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: subjects.php?msg=deleted");
                exit;
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: subjects.php?msg=error");
        exit;
    }
}

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Subject has been removed successfully.', showConfirmButton: false, timer: 2000 });";
    } elseif ($_GET['msg'] === 'added') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Success!', text: 'New subject added successfully.', showConfirmButton: false, timer: 2000 });";
    } elseif ($_GET['msg'] === 'error') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong while processing your request.' });";
    }
}

// FETCH SUBJECTS
$sql = "
    SELECT 
        subjects.subject_id,
        subjects.subject_code,
        subjects.subject_name,
        subjects.year_level,
        subjects.semester,
        users.full_name AS professor_name
    FROM subjects
    LEFT JOIN users ON subjects.professor_id = users.user_id
    ORDER BY subjects.subject_id DESC
";

$result = mysqli_query($conn, $sql);

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

    /* ICON BOX & BADGES */
    .subject-icon-box { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }
    .subject-code-badge { font-family: 'Courier New', monospace; font-size: 13px; background-color: #EEF2FF; border: 1px solid #C7D2FE; color: #4F46E5; padding: 4px 8px; border-radius: 6px; font-weight: 700; }

    /* BUTTONS */
    .btn-create { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 8px 20px; border: none; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); white-space: nowrap; }
    .btn-create:hover { background-color: #0036cc; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    .btn-delete { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; }
    .btn-delete:hover { background-color: #EF4444; color: white; }

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        
        /* Pinanatiling visible ang search bar at pinalaki para sakupin ang width kung kailangan */
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
                            Home <span class="mx-1">></span> Subject Mgmt <span class="mx-1">></span> <strong class="text-dark">Subject List</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Subject Management</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Manage curriculum subjects and assigned professors.</p>
                    </div>
                    <div>
                        <a href="add_subject.php" class="btn-create"><i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">Add New Subject</span><span class="d-inline d-sm-none">Add Subject</span></a>
                    </div>
                </div>

                <div class="saas-card">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 table-header-container">
                        <h6 class="fw-bold mb-0 text-dark w-100 w-md-auto"><i class="fas fa-book-open text-primary me-2"></i> Subject Directory</h6>
                        
                        <div class="search-wrapper">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchSubject" placeholder="Search code, name, or professor...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 35%;">Subject Info</th>
                                    <th style="width: 20%;">Professor</th>
                                    <th style="width: 12%;">Year Level</th>
                                    <th style="width: 13%;">Semester</th>
                                    <th style="width: 15%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $count = 1; 
                                    $icon_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];

                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $safe_code = htmlspecialchars($row['subject_code']);
                                        $safe_name = htmlspecialchars($row['subject_name']);
                                        $safe_prof = htmlspecialchars($row['professor_name'] ?? 'Unassigned');
                                        $safe_year = htmlspecialchars($row['year_level']);
                                        $safe_sem = htmlspecialchars($row['semester']);
                                        
                                        // Randomize color based on ID
                                        $color = $icon_colors[$row['subject_id'] % count($icon_colors)];
                                        
                                        $search_data = strtolower($safe_code . ' ' . $safe_name . ' ' . $safe_prof . ' ' . $safe_year);
                                    ?>
                                        <tr class="subject-row" data-search="<?= $search_data ?>">
                                            <td class="text-muted text-center"><?= $count++; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="subject-icon-box" style="background-color: <?= $color ?>;">
                                                        <i class="fas fa-book"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 14px;"><?= $safe_name; ?></div>
                                                        <div class="mt-1"><span class="subject-code-badge"><?= $safe_code; ?></span></div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <?php if(!empty($row['professor_name'])): ?>
                                                    <span class="text-dark fw-medium" style="font-size: 13.5px;"><i class="fas fa-user-tie text-muted me-1"></i> <?= $safe_prof; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border border-secondary fst-italic">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <span class="badge-soft"><?= $safe_year; ?></span>
                                            </td>
                                            
                                            <td>
                                                <span class="badge-soft"><?= $safe_sem; ?></span>
                                            </td>
                                            
                                            <td class="text-center">
                                                <button type="button" class="btn btn-delete shadow-sm" 
                                                        onclick="confirmDeletion(<?= (int) $row['subject_id']; ?>, '<?= addslashes($safe_name); ?>')">
                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-book-open fs-2 mb-3 text-secondary"></i><br>
                                            No subjects found in the system.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                        No subjects match your search.
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
    function confirmDeletion(subjectId, subjectName) {
        Swal.fire({
            title: 'Delete Subject?',
            text: "Are you sure you want to remove " + subjectName + "? This might affect students' grades assigned to this subject.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444', 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: '<i class="fas fa-trash-alt"></i> Yes, delete subject',
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
                window.location.href = 'subjects.php?delete=' + subjectId;
            }
        });
    }

    // LIVE SEARCH SCRIPT
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchSubject");
        const rows = document.querySelectorAll(".subject-row");
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