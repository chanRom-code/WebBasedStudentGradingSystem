<?php
session_start();
include '../includes/config.php';

// Gumamit ng "or" para iwas text conversion error
if (!isset($_SESSION['user_id']) or !isset($_SESSION['role']) or $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$current_page = basename($_SERVER['PHP_SELF']);
$swal_script = ''; 

// TOGGLE USER STATUS (ACTIVATE / DEACTIVATE)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    $new_status = $_GET['toggle'] === 'active' ? 'active' : 'inactive';

    if ($user_id > 0) {
        // Prevent deactivating the currently logged-in account
        if ($user_id === (int) $_SESSION['user_id'] && $new_status === 'inactive') {
            header("Location: users.php?msg=self_deactivate");
            exit;
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $msg = $new_status === 'active' ? 'activated' : 'deactivated';
                header("Location: users.php?msg=" . $msg);
                exit;
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: users.php?msg=error");
        exit;
    }
}

// SWEETALERT MESSAGES
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deactivated') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Deactivated!', text: 'User account has been disabled.', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'activated') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Activated!', text: 'User account has been enabled.', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'added') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Success!', text: 'New user added successfully.', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'edited') {
        $swal_script = "Swal.fire({ icon: 'success', title: 'Updated!', text: 'User details have been updated successfully.', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'self_deactivate') {
        $swal_script = "Swal.fire({ icon: 'warning', title: 'Oops...', text: 'You cannot deactivate your own account while logged in.', customClass: { popup: 'rounded-20' } });";
    } elseif ($_GET['msg'] === 'error') {
        $swal_script = "Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong while processing your request.', customClass: { popup: 'rounded-20' } });";
    }
}

// FETCH USERS (Kasama na yung status column)
$result = mysqli_query($conn, "SELECT user_id, full_name, email, role, status FROM users ORDER BY role ASC, full_name ASC");

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
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; display: inline-flex; align-items: center;}
    
    .badge-admin { background-color: #EEF2FF; color: #4F46E5; border: 1px solid #C7D2FE; }
    .badge-prof { background-color: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
    
    /* STATUS BADGES */
    .badge-active { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-inactive { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; }
    
    .badge-current { background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

    /* BUTTONS */
    .btn-create { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 8px 20px; border: none; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); cursor: pointer; white-space: nowrap; }
    .btn-create:hover { background-color: #0036cc; color: white; transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    
    .btn-edit { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #BFDBFE; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
    .btn-edit:hover { background-color: #3B82F6; color: white; }

    .btn-delete { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; border: none;}
    .btn-delete:hover { background-color: #EF4444; color: white; }

    .btn-activate { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; border-radius: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; border: none;}
    .btn-activate:hover { background-color: #10B981; color: white; }

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }

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
                            Home <span class="mx-1">></span> User Management <span class="mx-1">></span> <strong class="text-dark">System Users</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">System Users</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Manage administrators and professors access.</p>
                    </div>
                    <div>
                        <a href="javascript:void(0);" onclick="confirmAddUser()" class="btn-create"><i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">Add New User</span><span class="d-inline d-sm-none">Add User</span></a>
                    </div>
                </div>

                <div class="saas-card">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 table-header-container">
                        <h6 class="fw-bold mb-0 text-dark w-100 w-md-auto"><i class="fas fa-users-cog text-primary me-2"></i> User Directory</h6>
                        
                        <div class="search-wrapper">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchUser" placeholder="Search by name, email, or role...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 25%;">User Profile</th>
                                    <th style="width: 25%;">Email Address</th>
                                    <th style="width: 15%;">Role</th>
                                    <th style="width: 10%;">Status</th>
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
                                        $safe_email = htmlspecialchars($row['email']);
                                        $safe_role = htmlspecialchars($row['role']);
                                        $status = $row['status'] ?? 'active';
                                        $is_active = ($status === 'active');
                                        
                                        $initial = strtoupper(substr($safe_name, 0, 1));
                                        $color = $avatar_colors[$row['user_id'] % count($avatar_colors)];
                                        
                                        $search_data = strtolower($safe_name . ' ' . $safe_email . ' ' . $safe_role . ' ' . $status);
                                        $is_current_user = ((int) $row['user_id'] === (int) $_SESSION['user_id']);
                                    ?>
                                        <tr class="user-row" data-search="<?= $search_data ?>">
                                            <td class="text-muted text-center"><?= $count++; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="user-avatar" style="background-color: <?= $color ?>;">
                                                        <?= $initial ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 14px;"><?= $safe_name; ?></div>
                                                        <?php if ($is_current_user): ?>
                                                            <span class="text-primary" style="font-size: 11px; font-weight: 600;">(You)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td style="font-size: 13.5px;">
                                                <i class="far fa-envelope text-muted me-1"></i> <?= $safe_email; ?>
                                            </td>
                                            
                                            <td>
                                                <?php if($row['role'] === 'admin'): ?>
                                                    <span class="badge-soft badge-admin"><i class="fas fa-shield-alt me-1"></i> Admin</span>
                                                <?php else: ?>
                                                    <span class="badge-soft badge-prof"><i class="fas fa-chalkboard-teacher me-1"></i> Professor</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <?php if($is_active): ?>
                                                    <span class="badge-soft badge-active"><i class="fas fa-circle me-1" style="font-size:8px;"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge-soft badge-inactive"><i class="fas fa-circle me-1" style="font-size:8px;"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2 align-items-center">
                                                    
                                                    <?php if ($is_current_user): ?>
                                                        <a href="javascript:void(0);" onclick="selfEditWarning()" class="btn-edit shadow-sm">
                                                            <i class="fas fa-edit me-1"></i> Edit
                                                        </a>
                                                        <span class="badge-soft badge-current py-2" style="font-size: 10px;">Active Session</span>
                                                    <?php else: ?>
                                                        <a href="javascript:void(0);" onclick="confirmEdit(<?= (int) $row['user_id']; ?>, '<?= addslashes($safe_name); ?>')" class="btn-edit shadow-sm">
                                                            <i class="fas fa-edit me-1"></i> Edit
                                                        </a>
                                                        
                                                        <?php if ($is_active): ?>
                                                            <button type="button" class="btn btn-delete shadow-sm" 
                                                                    onclick="confirmToggle(<?= (int) $row['user_id']; ?>, '<?= addslashes($safe_name); ?>', 'inactive')">
                                                                <i class="fas fa-user-slash me-1"></i> Deactivate
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-activate shadow-sm" 
                                                                    onclick="confirmToggle(<?= (int) $row['user_id']; ?>, '<?= addslashes($safe_name); ?>', 'active')">
                                                                <i class="fas fa-user-check me-1"></i> Activate
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-users-slash fs-2 mb-3 text-secondary"></i><br>
                                            No users found in the system.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-search text-secondary mb-2" style="font-size: 24px;"></i><br>
                                        No users match your search.
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
    // Trigger Success/Error Messages kung may laman yung $swal_script mula sa PHP
    <?= $swal_script; ?>

    // Add New User Confirmation
    function confirmAddUser() {
        Swal.fire({
            title: 'Add New User?',
            text: "Do you want to create a new administrator or professor account?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0047FF', 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: '<i class="fas fa-plus me-1"></i> Yes, proceed',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: { popup: 'rounded-20' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'add_user.php';
            }
        });
    }

    // Kapag pinindot ni admin ang "Edit" button sa sarili niyang pangalan
    function selfEditWarning() {
        Swal.fire({
            icon: 'info',
            title: 'Manage Your Own Profile',
            text: 'To update your personal details and password, please go to the "My Profile" tab.',
            confirmButtonColor: '#0047FF',
            confirmButtonText: 'Go to My Profile',
            showCancelButton: true,
            cancelButtonText: 'Close',
            customClass: { popup: 'rounded-20' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'profile.php';
            }
        });
    }

    // Edit Confirmation
    function confirmEdit(userId, userName) {
        Swal.fire({
            title: 'Edit User Account?',
            text: "Do you want to update the details or role of " + userName + "?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3B82F6', 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: '<i class="fas fa-edit me-1"></i> Yes, proceed to edit',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: { popup: 'rounded-20' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'edit_user.php?id=' + userId;
            }
        });
    }

    // TOGGLE (Activate/Deactivate) Confirmation
    function confirmToggle(userId, userName, action) {
        const isDeactivating = (action === 'inactive');
        
        const titleText = isDeactivating ? 'Deactivate Account?' : 'Activate Account?';
        const msgText = isDeactivating 
            ? "Are you sure you want to deactivate " + userName + "? They will no longer be able to log in to the system."
            : "Are you sure you want to activate " + userName + "? They will regain access to their account.";
            
        const btnText = isDeactivating ? '<i class="fas fa-user-slash me-1"></i> Yes, deactivate' : '<i class="fas fa-user-check me-1"></i> Yes, activate';
        const btnColor = isDeactivating ? '#EF4444' : '#10B981';

        Swal.fire({
            title: titleText,
            text: msgText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: btnColor, 
            cancelButtonColor: '#94A3B8', 
            confirmButtonText: btnText,
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
                window.location.href = 'users.php?toggle=' + action + '&id=' + userId;
            }
        });
    }

    // LIVE SEARCH SCRIPT
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchUser");
        const rows = document.querySelectorAll(".user-row");
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