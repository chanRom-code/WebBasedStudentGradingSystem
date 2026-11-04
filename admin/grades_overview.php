<?php
session_start();

// Ipinaalam sa IDE kung saan galing ang $conn
/** @var mysqli $conn */
include '../includes/config.php';

if (!isset($_SESSION['user_id']) or !isset($_SESSION['role']) or $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = isset($_SESSION['full_name']) ? htmlspecialchars((string)$_SESSION['full_name'], ENT_QUOTES) : 'Administrator';
$current_page = basename($_SERVER['PHP_SELF']);

$sql = "
    SELECT 
        g.grading_period,
        g.is_submitted,
        g.is_locked,
        s.full_name AS student_name,
        sub.subject_code,
        sub.subject_name,
        sub.semester,
        u.user_id AS professor_id,
        u.full_name AS professor_name
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN subjects sub ON g.subject_id = sub.subject_id
    JOIN users u ON g.professor_id = u.user_id
    ORDER BY 
        u.full_name ASC,
        sub.semester ASC,
        sub.subject_name ASC,
        s.full_name ASC,
        FIELD(g.grading_period, 'Prelim', 'Midterm', 'Prefinal', 'Final')
";

$result = mysqli_query($conn, $sql);

$grouped_data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $prof_id = $row['professor_id'];
        $prof_name = $row['professor_name'];
        $semester = !empty($row['semester']) ? $row['semester'] : 'Unknown'; 
        $subject_title = (!empty($row['subject_code']) ? $row['subject_code'] . ' - ' : '') . $row['subject_name'];

        if (!isset($grouped_data[$prof_id])) {
            $grouped_data[$prof_id] = [
                'name' => $prof_name,
                'semesters' => []
            ];
        }
        if (!isset($grouped_data[$prof_id]['semesters'][$semester])) {
            $grouped_data[$prof_id]['semesters'][$semester] = [];
        }
        if (!isset($grouped_data[$prof_id]['semesters'][$semester][$subject_title])) {
            $grouped_data[$prof_id]['semesters'][$semester][$subject_title] = [];
        }

        $grouped_data[$prof_id]['semesters'][$semester][$subject_title][] = $row;
    }
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* MATCHING SAAS DASHBOARD STYLES */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    
    .table-saas { width: 100%; border-collapse: collapse; }
    .table-saas th { color: #64748B; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; padding: 12px 15px; border-bottom: 1px solid #E2E8F0; background-color: #F8FAFC; }
    .table-saas td { padding: 12px 15px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; font-size: 13.5px; color: #334155; font-weight: 500; }
    .table-saas tbody tr:hover { background-color: #F8FAFC; }

    .prof-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 15px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .badge-soft { 
        display: inline-flex; align-items: center; justify-content: center;
        padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; 
        letter-spacing: 0.3px; text-transform: uppercase; white-space: nowrap; 
    }
    .badge-soft i { margin-right: 4px; font-size: 12px; } 
    
    .badge-success-soft { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-warning-soft { background-color: #FFFBEB; color: #F59E0B; border: 1px solid #FDE68A; }
    .badge-danger-soft { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; }
    .badge-info-soft { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #BFDBFE; }
    .badge-default-soft { background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 15px; display: flex; align-items: center; width: 350px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 14px; color: #0F172A; }

    /* ACCORDION STYLES */
    .prof-card { padding: 0 !important; overflow: hidden; transition: 0.3s; }
    .prof-header { cursor: pointer; padding: 20px 24px; transition: background-color 0.2s; }
    .prof-header:hover { background-color: #F8FAFC; }
    .prof-content { display: none; padding: 24px; background-color: #F8FAFC; border-top: 1px solid #E2E8F0; }
    
    .semester-title { font-size: 13px; font-weight: 700; letter-spacing: 1px; color: #0047FF; border-bottom: 2px solid #BFDBFE; padding-bottom: 8px; margin-bottom: 20px; text-transform: uppercase;}
    
    /* SUBJECT ACCORDION STYLES */
    .subject-card { background: #ffffff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
    .subject-header { cursor: pointer; padding: 16px 20px; background-color: #ffffff; display: flex; justify-content: space-between; align-items: center; transition: background-color 0.2s; }
    .subject-header:hover { background-color: #F8FAFC; }
    .subject-content { display: none; border-top: 1px solid #E2E8F0; background-color: #ffffff; }
    
    .toggle-icon { transition: transform 0.3s; }
    .toggle-icon.rotate { transform: rotate(180deg); }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        
        /* Pinanatiling visible ang search bar at isinasaayos ang layout sa table header */
        .search-wrapper { width: 100%; margin-top: 10px; }
        
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        
        /* Subject Header adjustments for mobile */
        .subject-header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px;
        }

        .subject-header .d-flex.align-items-center.gap-2 {
            width: 100%;
            justify-content: space-between;
        }
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
                            Home <span class="mx-1">></span> Grades <span class="mx-1">></span> <strong class="text-dark">Overview</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Grades Overview</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Monitor grades organized by Professor, Semester, and Subject.</p>
                    </div>
                    
                    <div class="search-wrapper bg-white shadow-sm">
                        <i class="fas fa-search text-muted"></i>
                        <input type="text" id="searchProf" placeholder="Search Professor Name...">
                    </div>
                </div>

                <div id="professorsContainer">
                    <?php if (empty($grouped_data)): ?>
                        <div class="saas-card text-center py-5">
                            <i class="fas fa-folder-open text-secondary mb-3" style="font-size: 40px;"></i>
                            <h5 class="text-dark fw-bold">No Records Found</h5>
                            <p class="text-muted">There are no grade records in the system yet.</p>
                        </div>
                    <?php else: ?>

                        <?php 
                        $avatar_colors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#8B5CF6'];
                        
                        foreach ($grouped_data as $prof_id => $prof_data) {
                            $safe_prof_name = htmlspecialchars($prof_data['name'], ENT_QUOTES);
                            $initial = strtoupper(substr($safe_prof_name, 0, 1));
                            $color = $avatar_colors[strlen($safe_prof_name) % count($avatar_colors)];
                            $search_key = strtolower($safe_prof_name);
                        ?>
                            <!-- LEVEL 1: PROFESSOR CARD -->
                            <div class="saas-card mb-3 prof-card" data-prof-name="<?= $search_key ?>">
                                
                                <div class="prof-header d-flex justify-content-between align-items-center" onclick="toggleProf(<?= $prof_id ?>)">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="prof-avatar" style="background-color: <?= $color ?>;">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 16px;"><?= $safe_prof_name ?></h5>
                                            <small class="text-muted">Professor / Instructor</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge-soft badge-default-soft d-none d-md-inline-flex">Click to view semesters & subjects</span>
                                        <i class="fas fa-chevron-down text-muted toggle-icon fs-5" id="icon-prof-<?= $prof_id ?>"></i>
                                    </div>
                                </div>

                                <div class="prof-content" id="content-prof-<?= $prof_id ?>">
                                    
                                    <?php foreach ($prof_data['semesters'] as $semester => $subjects) { ?>
                                        
                                        <div class="mb-4">
                                            <div class="semester-title">
                                                <i class="fas fa-calendar-alt me-2"></i> <?= htmlspecialchars($semester) ?> Semester
                                            </div>

                                            <?php foreach ($subjects as $subject_title => $records) { 
                                                // Gumawa ng unique ID gamit ang md5 para sa bawat subject ng bawat prof
                                                $sub_unique_id = $prof_id . '_' . md5($subject_title);
                                            ?>
                                                
                                                <!-- LEVEL 2: SUBJECT CARD -->
                                                <div class="subject-card">
                                                    <div class="subject-header d-flex justify-content-between align-items-center" onclick="toggleSubject('<?= $sub_unique_id ?>')">
                                                        <div class="fw-semibold text-dark fs-6" style="word-break: break-word;">
                                                            <i class="fas fa-book text-primary me-2"></i> <?= htmlspecialchars($subject_title) ?>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="badge-soft badge-info-soft">View Students</span>
                                                            <i class="fas fa-chevron-down text-muted toggle-icon fs-6" id="icon-sub-<?= $sub_unique_id ?>"></i>
                                                        </div>
                                                    </div>

                                                    <!-- LEVEL 3: STUDENTS LIST TABLE -->
                                                    <div class="subject-content" id="content-sub-<?= $sub_unique_id ?>">
                                                        <div class="table-responsive">
                                                            <table class="table-saas m-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 5%; text-align: center;">#</th>
                                                                        <th style="width: 35%;">Student Name</th>
                                                                        <th style="width: 20%;">Grading Period</th>
                                                                        <th style="width: 20%;">Status</th>
                                                                        <th style="width: 20%; text-align: center;">Lock Status</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php 
                                                                    $count = 1;
                                                                    foreach ($records as $row) {
                                                                        $safe_stud = htmlspecialchars((string)$row['student_name'], ENT_QUOTES);
                                                                        $safe_period = htmlspecialchars((string)$row['grading_period'], ENT_QUOTES);
                                                                    ?>
                                                                        <tr>
                                                                            <td class="text-muted text-center"><?= $count++; ?></td>
                                                                            <td>
                                                                                <i class="fas fa-user-graduate text-primary me-2" style="opacity: 0.7;"></i> 
                                                                                <span class="fw-semibold text-dark"><?= $safe_stud; ?></span>
                                                                            </td>
                                                                            <td>
                                                                                <span class="badge-soft badge-info-soft"><?= $safe_period; ?></span>
                                                                            </td>
                                                                            <td>
                                                                                <?php if ((int) $row['is_submitted'] === 1): ?>
                                                                                    <span class="badge-soft badge-success-soft"><i class="fas fa-check-circle"></i> Submitted</span>
                                                                                <?php else: ?>
                                                                                    <span class="badge-soft badge-warning-soft"><i class="fas fa-clock"></i> Pending</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <?php if ((int) $row['is_locked'] === 1): ?>
                                                                                    <span class="badge-soft badge-danger-soft"><i class="fas fa-lock"></i> Locked</span>
                                                                                <?php else: ?>
                                                                                    <span class="badge-soft badge-default-soft"><i class="fas fa-lock-open"></i> Open</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php } ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>

                                            <?php } ?>
                                        </div>
                                    <?php } ?>

                                </div>
                            </div>
                        <?php } ?>
                    <?php endif; ?>
                    
                    <div id="noLogsMessage" class="saas-card text-center py-5" style="display: none;">
                        <i class="fas fa-search text-secondary mb-3" style="font-size: 40px;"></i>
                        <h5 class="text-dark fw-bold">Professor Not Found</h5>
                        <p class="text-muted">No professor matches your search query.</p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle Professor Card (Level 1)
    function toggleProf(profId) {
        const content = document.getElementById('content-prof-' + profId);
        const icon = document.getElementById('icon-prof-' + profId);
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.classList.add('rotate');
        } else {
            content.style.display = 'none';
            icon.classList.remove('rotate');
        }
    }

    // Toggle Subject Card (Level 2)
    function toggleSubject(subUniqueId) {
        const content = document.getElementById('content-sub-' + subUniqueId);
        const icon = document.getElementById('icon-sub-' + subUniqueId);
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.classList.add('rotate');
        } else {
            content.style.display = 'none';
            icon.classList.remove('rotate');
        }
    }

    // Live Search for Professor Name
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchProf");
        const profCards = document.querySelectorAll(".prof-card");
        const noLogsMessage = document.getElementById("noLogsMessage");

        if (searchInput) {
            searchInput.addEventListener("input", function() {
                const query = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                profCards.forEach(card => {
                    const profName = card.getAttribute("data-prof-name");
                    
                    if (profName.includes(query)) {
                        card.style.display = ""; 
                        visibleCount++;
                    } else {
                        card.style.display = "none"; 
                    }
                });

                if (noLogsMessage) {
                    noLogsMessage.style.display = (visibleCount === 0 && profCards.length > 0) ? "block" : "none";
                }
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>