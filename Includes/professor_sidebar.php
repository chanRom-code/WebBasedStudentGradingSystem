<?php
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}

if (!isset($professor_id)) {
    $professor_id = (int) ($_SESSION['user_id'] ?? 0);
}

$pending_requests_count = 0;

if (isset($conn) && $professor_id > 0) {
    $pending_query = mysqli_query($conn, "
        SELECT COUNT(*) AS total
        FROM unlock_requests ur
        JOIN grades g ON ur.grade_id = g.grade_id
        WHERE g.professor_id = {$professor_id}
        AND ur.request_status = 'Pending'
    ");

    if ($pending_query) {
        $row = mysqli_fetch_assoc($pending_query);
        $pending_requests_count = (int) ($row['total'] ?? 0);
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #F4F7FE; 
    }
    .sidebar { 
        background-color: #ffffff !important; 
        
        /* DITO ANG FIX: Fixed na 100vh para hindi sumabay sa haba ng table */
        height: 100vh; 
        
        position: sticky; 
        top: 0; 
        z-index: 1000;
        border-right: 1px solid #E2E8F0;
        
        /* DITO ANG FIX: Scrollable ang sidebar just in case maliit ang screen */
        overflow-y: auto; 
    }

    /* Para hindi pangit tignan yung scrollbar ng sidebar */
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

    .sidebar .nav-link { 
        color: #64748B; 
        padding: 12px 16px; 
        border-radius: 10px; 
        transition: all 0.2s ease; 
        font-weight: 500;
        font-size: 14.5px;
        margin-bottom: 4px;
    }
    .sidebar .nav-link:hover { 
        background: #F1F5F9; 
        color: #0F172A; 
    }
    .sidebar .nav-link.active { 
        background-color: #0047FF !important; 
        color: #fff !important; 
        box-shadow: 0 4px 10px rgba(0, 71, 255, 0.2); 
    }
    .active-sub { color: #0047FF !important; font-weight: 600; }
    .menu-arrow { transition: transform 0.3s ease; }
    .nav-link[aria-expanded="true"] .menu-arrow { transform: rotate(90deg); }
    .hover-red:hover { background: rgba(239, 68, 68, 0.1) !important; color: #EF4444 !important; }
    
    /* Para sa rounded border ng SweetAlert popup */
    .rounded-20 { border-radius: 20px !important; }
</style>

<div class="col-md-2 sidebar d-flex flex-column p-3">
    
    <div class="text-center mb-4 mt-2">
        <img src="../assets/SFICS-LOGO.jpg" alt="SFICS Logo" width="65" class="rounded-circle shadow-sm mb-2">
        <h6 class="mb-0 fw-bold text-dark">SFICS</h6>
        <small class="text-muted fw-semibold" style="font-size: 0.7rem;">Grading System</small>
    </div>

    <nav class="nav flex-column gap-1 flex-grow-1 mt-2">
        
        <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large me-3" style="width: 16px;"></i> Dashboard
        </a>
        
        <div class="nav-item">
            <?php 
                $is_subject_active = in_array($current_page, ['subjects.php', 'encode_grades.php', 'my_students.php', 'student_list.php']); 
            ?>
            <a class="nav-link d-flex justify-content-between align-items-center <?= $is_subject_active ? 'active' : '' ?>" 
               data-bs-toggle="collapse" href="#subjectMenu" role="button" aria-expanded="<?= $is_subject_active ? 'true' : 'false' ?>">
                <span><i class="fas fa-book-open me-3" style="width: 16px;"></i> My Subjects</span>
                <i class="fas fa-chevron-right menu-arrow" style="font-size: 0.7rem;"></i>
            </a>
            <div class="collapse <?= $is_subject_active ? 'show' : '' ?>" id="subjectMenu">
                <div class="nav flex-column ms-4 mt-1 border-start border-2 border-light">
                    <a href="subjects.php" class="nav-link py-1 ps-3 <?= ($current_page == 'subjects.php' || $current_page == 'encode_grades.php') ? 'active-sub' : 'text-muted' ?>">Subject List</a>
                    <a href="my_students.php" class="nav-link py-1 ps-3 <?= ($current_page == 'my_students.php' || $current_page == 'student_list.php') ? 'active-sub' : 'text-muted' ?>">Student List</a>
                </div>
            </div>
        </div>
        
        <a href="submitted_grades.php" class="nav-link <?= $current_page === 'submitted_grades.php' ? 'active' : ''; ?>">
            <i class="fas fa-check-double me-3" style="width: 16px;"></i> Submitted Grades
        </a>
        
        <a href="request_unlock.php" class="nav-link d-flex justify-content-between align-items-center <?= $current_page === 'request_unlock.php' ? 'active' : ''; ?>">
            <span><i class="fas fa-unlock-alt me-3" style="width: 16px;"></i> Unlock Requests</span>
            <?php if ($pending_requests_count > 0): ?>
                <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;"><?= $pending_requests_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="profile.php" class="nav-link <?= $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle me-3" style="width: 16px;"></i> My Profile
        </a>

        <a href="settings.php" class="nav-link <?= $current_page === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog me-3" style="width: 16px;"></i> Settings
        </a>
    </nav>

    <div class="mt-auto pt-3 pb-2 border-top">
        <a href="#" onclick="confirmLogout(event)" class="nav-link text-muted fw-bold hover-red d-flex align-items-center" style="padding: 10px 16px; border-radius: 8px;">
            <i class="fas fa-sign-out-alt me-3" style="width: 16px;"></i> Log Out
        </a>
    </div>
</div>

<script>
function confirmLogout(event) {
    event.preventDefault(); // Pipigilan ang direktang pag-click

    Swal.fire({
        title: 'Sign Out?',
        text: "Are you sure you want to log out of your account?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444', // Red for logout
        cancelButtonColor: '#94A3B8',  // Gray for cancel
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, Log me out',
        cancelButtonText: 'Cancel',
        reverseButtons: true, // Para nasa kanan ang cancel at kaliwa ang confirm
        customClass: { popup: 'rounded-20' }
    }).then((result) => {
        if (result.isConfirmed) {
            // Pag nag-Yes, ididiretso sa logout.php
            window.location.href = '../logout.php';
        }
    });
}
</script>