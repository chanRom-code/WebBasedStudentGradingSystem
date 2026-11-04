<?php
session_start();
include '../includes/config.php';

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$current_page = basename($_SERVER['PHP_SELF']);
$swal_script = ''; // Para sa SweetAlert

// SWEETALERT MESSAGES (Catching messages from approved_password_reset.php at deny_password_reset.php)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Approved!', text: 'Password reset request has been approved and updated.', showConfirmButton: false, timer: 2500, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'denied') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Denied', text: 'Password reset request has been denied and removed.', showConfirmButton: false, timer: 2500, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'error') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong while processing the request.', customClass: { popup: 'rounded-20' } });";
    }
}

// Kunin ang mga pending requests
$query = "SELECT * FROM password_resets WHERE status = 'pending' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// Count para sa badge sa sidebar
$pending_requests_count = mysqli_num_rows($result);

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

    /* AVATARS & BUTTONS */
    .email-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .btn-approve { background-color: #10B981; color: white; border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 16px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); margin-right: 5px; }
    .btn-approve:hover { background-color: #059669; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(16, 185, 129, 0.3); }

    /* DITO ANG BAGONG DENY BUTTON STYLE */
    .btn-deny { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 16px; transition: 0.2s; }
    .btn-deny:hover { background-color: #EF4444; color: white; transform: translateY(-1px); }

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
    
    /* Para pumantay yung buttons */
    .action-buttons { display: flex; gap: 8px; justify-content: center; }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        
        /* Pinanatiling visible ang search bar at sumakop sa buong width */
        .search-wrapper { width: 100%; margin-top: 10px; }
        .table-header-container { flex-direction: column; align-items: flex-start !important; }
        
        .action-buttons { flex-wrap: wrap; }
        .btn-approve, .btn-deny { flex: 1; text-align: center; justify-content: center; display: flex; align-items: center; }
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
                            Home <span class="mx-1">></span> Requests <span class="mx-1">></span> <strong class="text-dark">Password Req.</strong>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Administrator</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Password Requests</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Review and approve password reset requests from users.</p>
                    </div>
                </div>

                <div class="saas-card">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 table-header-container">
                        <h6 class="mb-0 fw-bold text-dark w-100 w-md-auto"><i class="fas fa-key text-warning me-2"></i> Pending Reset Requests</h6>
                        
                        <div class="search-wrapper w-100 w-md-auto">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchEmail" placeholder="Search email address...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 35%;">Email Address</th>
                                    <th style="width: 25%;">Date Requested</th>
                                    <th style="width: 35%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $count = 1;
                                    $avatar_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];

                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $safe_email = htmlspecialchars($row['email']);
                                        $initial = strtoupper(substr($safe_email, 0, 1));
                                        
                                        // Dynamic Color based on ID
                                        $color = $avatar_colors[$row['id'] % count($avatar_colors)];
                                    ?>
                                        <tr class="req-row" data-search="<?= strtolower($safe_email) ?>">
                                            <td class="text-muted text-center fw-bold"><?= $count++; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="email-avatar" style="background-color: <?= $color ?>;">
                                                        <?= $initial ?>
                                                    </div>
                                                    <div class="fw-bold text-dark" style="font-size: 14px;">
                                                        <?= $safe_email; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div class="text-dark fw-medium" style="font-size: 13.5px;"><?= date('M d, Y', strtotime($row['created_at'])); ?></div>
                                                <div class="text-muted" style="font-size: 12px;"><i class="far fa-clock me-1"></i> <?= date('h:i A', strtotime($row['created_at'])); ?></div>
                                            </td>
                                            
                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn-approve shadow-sm" 
                                                            onclick="confirmApproval(<?= $row['id']; ?>, '<?= addslashes($safe_email); ?>')">
                                                        <i class="fas fa-check me-1"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn-deny shadow-sm" 
                                                            onclick="confirmDenial(<?= $row['id']; ?>, '<?= addslashes($safe_email); ?>')">
                                                        <i class="fas fa-times me-1"></i> Deny
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fas fa-check-double text-success mb-3" style="font-size: 40px; opacity: 0.5;"></i><br>
                                            <span style="font-size: 15px; font-weight: 500;">All caught up! No pending password reset requests.</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="4" class="text-center py-5 text-muted small">
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
    // Trigger Success/Error Messages from backend
    <?= $swal_script; ?>

    // Professional Form Submission with SweetAlert Temporary Password Prompt
    function confirmApproval(requestId, email) {
        Swal.fire({
            title: 'Set Temporary Password',
            text: 'Enter a temporary password for ' + email + '. The user will be forced to change this upon login.',
            input: 'text',
            inputPlaceholder: 'e.g. TempPass123',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#10B981', // SaaS Green
            cancelButtonColor: '#64748B',  // SaaS Slate
            confirmButtonText: '<i class="fas fa-check"></i> Approve & Save',
            customClass: { popup: 'rounded-20' },
            inputValidator: (value) => {
                if (!value) {
                    return 'You need to assign a temporary password!';
                }
                if (value.length < 6) {
                    return 'Password must be at least 6 characters long!';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Loading Animation habang nagse-save
                Swal.fire({
                    title: 'Processing...',
                    text: 'Approving request and setting new password...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Gumawa ng hidden form para i-submit yung ID at password sa backend
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'approved_password_reset.php'; 
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = requestId;
                
                const passInput = document.createElement('input');
                passInput.type = 'hidden';
                passInput.name = 'new_password';
                passInput.value = result.value;
                
                form.appendChild(idInput);
                form.appendChild(passInput);
                document.body.appendChild(form);
                
                form.submit();
            }
        });
    }

    // DENY FUNCTION SCRIPT
    function confirmDenial(requestId, email) {
        Swal.fire({
            title: 'Deny Request?',
            text: "Are you sure you want to deny and remove the password reset request for " + email + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444', // Red
            cancelButtonColor: '#94A3B8',  // Gray
            confirmButtonText: '<i class="fas fa-times me-1"></i> Yes, Deny Request',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: { popup: 'rounded-20' }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing...',
                    text: 'Denying request...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'deny_password_reset.php'; 
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = requestId;
                
                form.appendChild(idInput);
                document.body.appendChild(form);
                
                form.submit();
            }
        });
    }

    // Live Search Logic
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchEmail");
        const rows = document.querySelectorAll(".req-row");
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