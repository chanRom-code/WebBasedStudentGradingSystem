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

$filter_professor_id = isset($_GET['professor_id']) ? (int)$_GET['professor_id'] : 0;
$current_professor_filter_param = $filter_professor_id > 0 ? '&professor_id=' . $filter_professor_id : '';

function getPendingRequestCount(mysqli $conn): int
{
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM unlock_requests WHERE request_status = 'Pending'");
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

// APPROVE REQUEST
if (isset($_GET['approve'])) {
    $request_id = (int) $_GET['approve'];

    if ($request_id > 0) {
        $grade_id = 0;

        $grade_stmt = mysqli_prepare($conn, "SELECT grade_id FROM unlock_requests WHERE request_id = ? LIMIT 1");
        if ($grade_stmt) {
            mysqli_stmt_bind_param($grade_stmt, "i", $request_id);
            mysqli_stmt_execute($grade_stmt);
            $grade_result = mysqli_stmt_get_result($grade_stmt);
            $grade_row = mysqli_fetch_assoc($grade_result);
            $grade_id = (int) ($grade_row['grade_id'] ?? 0);
            mysqli_stmt_close($grade_stmt);
        }

        $approve_stmt = mysqli_prepare($conn, "UPDATE unlock_requests SET request_status = 'Approved' WHERE request_id = ?");
        if ($approve_stmt) {
            mysqli_stmt_bind_param($approve_stmt, "i", $request_id);
            mysqli_stmt_execute($approve_stmt);
            mysqli_stmt_close($approve_stmt);
        }

        if ($grade_id > 0) {
            $unlock_stmt = mysqli_prepare($conn, "UPDATE grades SET is_locked = 0 WHERE grade_id = ?");
            if ($unlock_stmt) {
                mysqli_stmt_bind_param($unlock_stmt, "i", $grade_id);
                mysqli_stmt_execute($unlock_stmt);
                mysqli_stmt_close($unlock_stmt);
            }
        }

        header("Location: unlock_requests.php?msg=approved" . $current_professor_filter_param);
        exit;
    }
    header("Location: unlock_requests.php?msg=error" . $current_professor_filter_param);
    exit;
}

// REJECT REQUEST
if (isset($_GET['reject'])) {
    $request_id = (int) $_GET['reject'];

    if ($request_id > 0) {
        $reject_stmt = mysqli_prepare($conn, "UPDATE unlock_requests SET request_status = 'Rejected' WHERE request_id = ?");
        if ($reject_stmt) {
            mysqli_stmt_bind_param($reject_stmt, "i", $request_id);
            mysqli_stmt_execute($reject_stmt);
            mysqli_stmt_close($reject_stmt);

            header("Location: unlock_requests.php?msg=rejected" . $current_professor_filter_param);
            exit;
        }
    }
    header("Location: unlock_requests.php?msg=error" . $current_professor_filter_param);
    exit;
}

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Approved!', text: 'Unlock request approved successfully. The professor can now edit the grades.', showConfirmButton: false, timer: 2500 });";
    } elseif ($_GET['msg'] === 'rejected') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Rejected!', text: 'Unlock request has been rejected.', showConfirmButton: false, timer: 2000 });";
    } elseif ($_GET['msg'] === 'error') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong while processing the request.' });";
    }
}

$pending_requests_count = getPendingRequestCount($conn);

// Fetch professors for the filter dropdown
$professors_list_query = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role = 'professor' ORDER BY full_name ASC");

$sql = "
    SELECT
        ur.request_id,
        ur.reason,
        ur.request_status,
        u.full_name AS professor_name,
        s.full_name AS student_name,
        sub.subject_name,
        g.grading_period
    FROM unlock_requests ur
    JOIN users u ON ur.professor_id = u.user_id
    JOIN grades g ON ur.grade_id = g.grade_id
    JOIN students s ON g.student_id = s.student_id
    JOIN subjects sub ON g.subject_id = sub.subject_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filter_professor_id > 0) {
    $sql .= " AND ur.professor_id = ?";
    $params[] = $filter_professor_id;
    $types .= 'i';
}

$sql .= " ORDER BY FIELD(ur.request_status, 'Pending', 'Approved', 'Rejected'), ur.request_id DESC";

$stmt = mysqli_prepare($conn, $sql);
$result = false;

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

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
    .prof-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 13px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; }
    .badge-warning-soft { background-color: #FFFBEB; color: #F59E0B; border: 1px solid #FDE68A; }
    .badge-success-soft { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-danger-soft { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; }
    
    .reason-text { font-style: italic; color: #64748B; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }

    /* SEARCH BAR & FILTERS */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 280px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
    
    .form-select-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 6px 15px; font-size: 13px; color: #334155; background-color: #F8FAFC; transition: 0.2s; cursor: pointer; height: 35px; }
    .form-select-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    
    .btn-filter { height: 35px; padding: 0 15px; font-size: 13px; font-weight: 500; border-radius: 8px; display: flex; align-items: center; }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        
        /* Layout fix para sa filter form at search bar sa mobile */
        .table-header-container { flex-direction: column; align-items: stretch !important; gap: 15px; }
        #filterForm { flex-direction: column; align-items: stretch !important; }
        .form-select-custom { width: 100% !important; }
        .btn-filter { width: 100%; justify-content: center; margin-top: 5px; }
        
        .search-wrapper { width: 100%; }
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
                            Home <span class="mx-1">></span> Requests <span class="mx-1">></span> <strong class="text-dark">Grade Unlocks</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Unlock Requests</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Manage and review professor requests to edit submitted grades.</p>
                    </div>
                </div>

                <div class="saas-card">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 table-header-container">
                        
                        <form method="GET" id="filterForm" class="d-flex align-items-center gap-2 m-0 w-100 w-md-auto">
                            <i class="fas fa-filter text-muted me-1 d-none d-md-inline" style="font-size: 14px;"></i>
                            <select name="professor_id" class="form-select-custom" style="width: 200px;">
                                <option value="">-- All Professors --</option>
                                <?php if ($professors_list_query && mysqli_num_rows($professors_list_query) > 0): ?>
                                    <?php while ($prof = mysqli_fetch_assoc($professors_list_query)): ?>
                                        <option value="<?= (int) $prof['user_id']; ?>" <?= $filter_professor_id === (int) $prof['user_id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($prof['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <button type="submit" id="filterBtn" class="btn btn-primary btn-filter shadow-sm">
                                <i class="fas fa-sync-alt me-1" id="filterIcon"></i> Filter
                            </button>
                        </form>
                        
                        <div class="search-wrapper w-100 w-md-auto">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchRequests" placeholder="Search student, subject...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 20%;">Professor</th>
                                    <th style="width: 15%;">Student</th>
                                    <th style="width: 20%;">Subject</th>
                                    <th style="width: 10%;">Period</th>
                                    <th style="width: 15%;">Reason</th>
                                    <th style="width: 5%;">Status</th>
                                    <th style="width: 10%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $count = 1;
                                    $avatar_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];

                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $safe_prof = htmlspecialchars($row['professor_name']);
                                        $safe_stud = htmlspecialchars($row['student_name']);
                                        $safe_sub = htmlspecialchars($row['subject_name']);
                                        $safe_reason = htmlspecialchars($row['reason']);
                                        
                                        // Profile Avatar Data
                                        $initial = strtoupper(substr($safe_prof, 0, 1));
                                        $color = $avatar_colors[strlen($safe_prof) % count($avatar_colors)];

                                        $search_string = strtolower($safe_prof . ' ' . $safe_stud . ' ' . $safe_sub . ' ' . $safe_reason);
                                    ?>
                                        <tr class="request-row" data-search="<?= $search_string ?>">
                                            <td class="text-muted text-center"><?= $count++; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="prof-avatar" style="background-color: <?= $color ?>;">
                                                        <?= $initial ?>
                                                    </div>
                                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 150px; font-size: 13.5px;" title="<?= $safe_prof ?>">
                                                        <?= $safe_prof; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td style="font-size: 13.5px;">
                                                <i class="fas fa-user-graduate text-muted me-1"></i> <?= $safe_stud; ?>
                                            </td>
                                            
                                            <td style="font-size: 13px;" class="text-truncate" title="<?= $safe_sub; ?>">
                                                <i class="fas fa-book text-muted me-1"></i> <?= $safe_sub; ?>
                                            </td>
                                            
                                            <td class="fw-bold text-secondary" style="font-size: 13px;">
                                                <?= htmlspecialchars($row['grading_period']); ?>
                                            </td>
                                            
                                            <td>
                                                <span class="reason-text" title="<?= $safe_reason; ?>" style="font-size: 13px;">
                                                    "<?= $safe_reason; ?>"
                                                </span>
                                            </td>
                                            
                                            <td>
                                                <?php if ($row['request_status'] === 'Pending'): ?>
                                                    <span class="badge-soft badge-warning-soft"><i class="fas fa-hourglass-half me-1"></i> Pending</span>
                                                <?php elseif ($row['request_status'] === 'Approved'): ?>
                                                    <span class="badge-soft badge-success-soft"><i class="fas fa-check-circle me-1"></i> Approved</span>
                                                <?php elseif ($row['request_status'] === 'Rejected'): ?>
                                                    <span class="badge-soft badge-danger-soft"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="text-center">
                                                <?php if ($row['request_status'] === 'Pending'): ?>
                                                    <?php 
                                                        $approve_url = "unlock_requests.php?approve=" . (int)$row['request_id'] . $current_professor_filter_param;
                                                        $reject_url = "unlock_requests.php?reject=" . (int)$row['request_id'] . $current_professor_filter_param;
                                                    ?>
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="btn btn-success btn-sm shadow-sm" style="border-radius: 6px; padding: 4px 8px;"
                                                                onclick="confirmAction('<?= $approve_url ?>', 'Approve', '#10B981', 'question')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm shadow-sm" style="border-radius: 6px; padding: 4px 8px;"
                                                                onclick="confirmAction('<?= $reject_url ?>', 'Reject', '#EF4444', 'warning')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic" style="font-size: 12px;">No action</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="fas fa-inbox text-secondary mb-3" style="font-size: 32px;"></i><br>
                                            No unlock requests found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                        No requests match your search.
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
    // Trigger Success/Error Messages from PHP
    <?= $swal_script; ?>

    // Professional Approve/Reject Confirmation
    function confirmAction(url, actionType, colorHex, iconType) {
        Swal.fire({
            title: actionType + ' Request?',
            text: "Are you sure you want to " + actionType.toLowerCase() + " this grade unlock request?",
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: colorHex, 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: 'Yes, ' + actionType.toLowerCase() + ' it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true 
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                window.location.href = url;
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        // 1. LIVE SEARCH LOGIC
        const searchInput = document.getElementById("searchRequests");
        const rows = document.querySelectorAll(".request-row");
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

        // 2. LOADING EFFECT PARA SA FILTER BUTTON
        const filterForm = document.getElementById("filterForm");
        const filterBtn = document.getElementById("filterBtn");
        const filterIcon = document.getElementById("filterIcon");

        if (filterForm) {
            filterForm.addEventListener("submit", function() {
                filterBtn.classList.add("disabled"); 
                filterIcon.classList.remove("fa-sync-alt"); 
                filterIcon.classList.add("fa-spinner", "fa-spin"); 
                filterBtn.innerHTML = 'Filtering...';
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>