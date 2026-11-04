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
$grade_id = isset($_GET['grade_id']) ? (int)$_GET['grade_id'] : 0;
$swal_script = ''; // Para sa SweetAlert

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

// SUBMIT REQUEST LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $check = mysqli_query($conn, "
        SELECT * FROM unlock_requests 
        WHERE grade_id = $grade_id 
        AND professor_id = $professor_id 
        AND request_status = 'Pending'
    ");

    if (mysqli_num_rows($check) > 0) {
        header("Location: request_unlock.php?grade_id=$grade_id&msg=already");
        exit;
    }
    
    $reason = trim($_POST['reason'] ?? '');

    if ($grade_id > 0 && $reason !== '') {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO unlock_requests (grade_id, professor_id, reason, request_status)
            VALUES (?, ?, ?, 'Pending')
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $grade_id, $professor_id, $reason);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: submitted_grades.php?msg=request_sent");
            exit;
        }
    }
}

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'already') {
        $swal_script = "Swal.fire({ icon: 'warning', title: 'Duplicate Request', text: 'You already have a pending unlock request for this grade.' });";
    } elseif ($_GET['msg'] === 'sent') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Request Sent!', text: 'Your unlock request has been submitted.', showConfirmButton: false, timer: 2000 });";
    }
}

// FETCH GRADE INFO KUNG MAY GRADE ID
$grade_info = null;
if ($grade_id > 0) {
   $query = mysqli_query($conn, "
        SELECT 
            g.grade_id,
            s.student_number,
            s.full_name AS student_name,
            sub.subject_code,
            sub.subject_name
        FROM grades g
        JOIN students s ON g.student_id = s.student_id
        JOIN subjects sub ON g.subject_id = sub.subject_id
        WHERE g.grade_id = $grade_id
        AND g.professor_id = $professor_id
    ");
    $grade_info = mysqli_fetch_assoc($query);

    // KUNG MAY GRADE ID PERO WALANG MATCH SA DATABASE (Invalid Access)
    if (!$grade_info) {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Invalid Access', text: 'Invalid or unauthorized grade access.' }).then(() => { window.location.href = 'submitted_grades.php'; });";
    }
}

// FETCH HISTORY
$sql = "
    SELECT
        ur.request_id,
        ur.reason,
        ur.request_status,
        g.grade_id,
        g.grading_period,
        s.student_number,
        s.full_name AS student_name,
        sub.subject_code,
        sub.subject_name
    FROM unlock_requests ur
    JOIN grades g ON ur.grade_id = g.grade_id
    JOIN students s ON g.student_id = s.student_id
    JOIN subjects sub ON g.subject_id = sub.subject_id
    WHERE ur.professor_id = ?
    ORDER BY ur.request_id DESC
";

$stmt = mysqli_prepare($conn, $sql);
$result = false;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $professor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* CONTENT & TOP NAVBAR (SAAS STYLE) */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
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

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }

    /* BADGES & TEXTS */
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; }
    .badge-success-soft { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-warning-soft { background-color: #FFFBEB; color: #F59E0B; border: 1px solid #FDE68A; }
    .badge-danger-soft { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; }
    
    .reason-text { font-style: italic; color: #64748B; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }
    
    /* MODAL STYLING (SAAS THEME) */
    .modal-content { border-radius: 16px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .modal-header-warning { background-color: #FFFBEB; border-bottom: 1px solid #FDE68A; border-top-left-radius: 16px; border-top-right-radius: 16px; padding: 20px 24px; }
    .modal-title-warning { color: #D97706; font-weight: 700; font-size: 16px; }
    .info-box-modal { background-color: #F8FAFC; border: 1px solid #E2E8F0; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #F59E0B; }
    .form-control-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; transition: 0.2s; }
    .form-control-custom:focus { border-color: #F59E0B; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); outline: none; }
    
    .btn-submit-req { background-color: #F59E0B; color: white; border-radius: 8px; font-weight: 600; font-size: 14px; padding: 10px 20px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(245, 158, 11, 0.2); }
    .btn-submit-req:hover { background-color: #D97706; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(245, 158, 11, 0.3); }
    .btn-cancel { background-color: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 600; font-size: 14px; padding: 10px 20px; transition: 0.2s; text-decoration: none; display: inline-block;}
    .btn-cancel:hover { background-color: #E2E8F0; color: #0F172A; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">

                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> Requests <span class="mx-1">></span> <strong class="text-dark">Unlock History</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Unlock Requests</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Track the status of your requests to unlock previously submitted grading sheets.</p>
                    </div>
                </div>

                <div class="saas-card">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-history text-primary me-2"></i> Request History</h6>
                        
                        <div class="search-wrapper">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchHistory" placeholder="Search student or subject...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 15%;">Student No.</th>
                                    <th style="width: 20%;">Student Name</th>
                                    <th style="width: 20%;">Subject</th>
                                    <th style="width: 10%;">Period</th>
                                    <th style="width: 20%;">Reason</th>
                                    <th style="width: 10%; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $count = 1;
                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $safe_num = htmlspecialchars($row['student_number']);
                                        $safe_name = htmlspecialchars($row['student_name']);
                                        $safe_sub = htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']);
                                        $safe_reason = htmlspecialchars($row['reason']);
                                        $search_data = strtolower($safe_num . ' ' . $safe_name . ' ' . $safe_sub);
                                    ?>
                                        <tr class="history-row" data-search="<?= $search_data ?>">
                                            <td class="text-muted fw-bold text-center"><?= $count++; ?></td>
                                            
                                            <td style="font-family: monospace; color: #64748B; font-size: 13px;">
                                                <?= $safe_num; ?>
                                            </td>
                                            
                                            <td class="fw-semibold text-dark" style="font-size: 14px;">
                                                <i class="fas fa-user-graduate text-muted me-2"></i><?= $safe_name; ?>
                                            </td>
                                            
                                            <td class="text-truncate" style="max-width: 250px; font-size: 13px;" title="<?= $safe_sub; ?>">
                                                <i class="fas fa-book text-muted me-1"></i> <?= $safe_sub; ?>
                                            </td>
                                            
                                            <td class="fw-bold text-secondary" style="font-size: 13px;"><?= htmlspecialchars($row['grading_period']); ?></td>
                                            
                                            <td>
                                                <span class="reason-text" title="<?= $safe_reason; ?>" style="font-size: 13px;">
                                                    "<?= $safe_reason; ?>"
                                                </span>
                                            </td>
                                            
                                            <td class="text-center">
                                                <?php if ($row['request_status'] === 'Pending'): ?>
                                                    <span class="badge-soft badge-warning-soft"><i class="fas fa-hourglass-half me-1"></i> Pending</span>
                                                <?php elseif ($row['request_status'] === 'Approved'): ?>
                                                    <span class="badge-soft badge-success-soft"><i class="fas fa-check-circle me-1"></i> Approved</span>
                                                <?php elseif ($row['request_status'] === 'Rejected'): ?>
                                                    <span class="badge-soft badge-danger-soft"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                                                <?php else: ?>
                                                    <span class="badge-soft"><?= htmlspecialchars($row['request_status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">
                                            <i class="fas fa-history text-secondary mb-3" style="font-size: 32px; opacity: 0.5;"></i><br>
                                            No unlock request history found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                        No history matches your search.
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

<?php if ($grade_id > 0 && $grade_info): ?>
<div class="modal fade" id="unlockModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="unlockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header-warning d-flex justify-content-between align-items-center">
                <h5 class="modal-title-warning mb-0" id="unlockModalLabel"><i class="fas fa-lock me-2"></i> Request Grade Unlock</h5>
                <a href="submitted_grades.php" class="text-decoration-none" style="color: #D97706; font-size: 20px;"><i class="fas fa-times"></i></a>
            </div>
            <form method="POST" id="requestForm">
                <div class="modal-body p-4">
                    
                    <div class="info-box-modal mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <span class="text-muted" style="width: 70px; font-size: 13px;">Student:</span> 
                            <strong class="text-dark fs-6"><?= htmlspecialchars($grade_info['student_name']) ?></strong> 
                            <span class="ms-2 text-muted" style="font-family: monospace; font-size: 13px;">(<?= htmlspecialchars($grade_info['student_number']) ?>)</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="text-muted" style="width: 70px; font-size: 13px;">Subject:</span> 
                            <strong class="text-dark" style="font-size: 14px;"><?= htmlspecialchars($grade_info['subject_code']) ?> - <?= htmlspecialchars($grade_info['subject_name']) ?></strong>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark" style="font-size: 14px;">Reason for Unlock <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control-custom w-100" rows="4" placeholder="Please state a valid reason for requesting this edit to the administrator..." required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-0 bg-light p-3" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; justify-content: flex-end;">
                    <a href="submitted_grades.php" class="btn-cancel me-2">Cancel</a>
                    <button type="submit" name="submit_request" id="submitReqBtn" class="btn-submit-req">
                        <i class="fas fa-paper-plane me-2" id="submitReqIcon"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Trigger Success/Error Messages from backend
    <?= $swal_script; ?>

    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. AUTO-SHOW MODAL KUNG MAY GRADE_ID
        <?php if ($grade_id > 0 && $grade_info): ?>
            var unlockModal = new bootstrap.Modal(document.getElementById('unlockModal'));
            unlockModal.show();
        <?php endif; ?>

        // 2. LIVE SEARCH LOGIC
        const searchInput = document.getElementById("searchHistory");
        const rows = document.querySelectorAll(".history-row");
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

        // 3. LOADING EFFECT FOR SUBMIT REQUEST BUTTON
        const reqForm = document.getElementById("requestForm");
        const submitReqBtn = document.getElementById("submitReqBtn");
        const submitReqIcon = document.getElementById("submitReqIcon");

        if (reqForm) {
            reqForm.addEventListener("submit", function() {
                submitReqBtn.classList.add("disabled"); 
                submitReqIcon.classList.remove("fa-paper-plane"); 
                submitReqIcon.classList.add("fa-spinner", "fa-spin"); 
                submitReqBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
            });
        }
    });
</script>

<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}

include '../includes/footer.php';
?>