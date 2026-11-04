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

// FETCH ASSIGNED SUBJECTS
$sql = "
    SELECT 
        subject_id,
        subject_code,
        subject_name,
        year_level,
        semester
    FROM subjects
    WHERE professor_id = ?
    ORDER BY subject_name ASC
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
    
    /* LEFT ALIGNED HEADERS */
    .table-saas th { color: #64748B; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px; border-bottom: 1px solid #E2E8F0; background-color: #F8FAFC; text-align: left; }
    .table-saas th.center-col { text-align: center; } /* Specific centering lang para sa # at Actions */
    
    .table-saas th:first-child { border-top-left-radius: 8px; }
    .table-saas th:last-child { border-top-right-radius: 8px; }
    
    .table-saas td { padding: 15px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; font-size: 14px; color: #334155; font-weight: 500; text-align: left; }
    .table-saas td.center-col { text-align: center; }

    .table-saas tbody tr { transition: 0.2s; }
    .table-saas tbody tr:hover { background-color: #F8FAFC; }

    /* ICON BOX & BADGES */
    .subject-icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; background-color: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }
    .subject-code-badge { font-family: 'Courier New', monospace; font-size: 12px; background-color: #EEF2FF; border: 1px solid #C7D2FE; color: #4F46E5; padding: 3px 8px; border-radius: 6px; font-weight: 700; }

    /* BUTTONS */
    .btn-encode { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 13px; padding: 8px 16px; border: none; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); display: inline-flex; align-items: center; }
    .btn-encode:hover { background-color: #0036cc; color: white; transform: translateY(-2px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }

    /* SEARCH BAR */
    .search-wrapper { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px 15px; display: flex; align-items: center; width: 300px; transition: 0.2s; }
    .search-wrapper:focus-within { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); }
    .search-wrapper input { border: none; background: transparent; outline: none; width: 100%; margin-left: 10px; font-size: 13px; color: #0F172A; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> Dashboard <span class="mx-1">></span> <strong class="text-dark">My Subjects</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Assigned Subjects</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Select a subject from your load to start encoding student grades.</p>
                    </div>
                </div>

                <div class="saas-card">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i> Subject Directory</h6>
                        
                        <div class="search-wrapper">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" id="searchSubject" placeholder="Search subject code or name...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-saas">
                            <thead>
                                <tr>
                                    <th class="center-col" style="width: 5%;">#</th>
                                    <th style="width: 45%;">Subject Details</th>
                                    <th style="width: 15%;">Year Level</th>
                                    <th style="width: 15%;">Semester</th>
                                    <th class="center-col" style="width: 20%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $count = 1;
                                    $icon_colors = ['#8B5CF6', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#4F46E5'];

                                    while ($row = mysqli_fetch_assoc($result)): 
                                        $safe_code = htmlspecialchars($row['subject_code']);
                                        $safe_name = htmlspecialchars($row['subject_name']);
                                        $safe_year = htmlspecialchars($row['year_level']);
                                        $safe_sem = htmlspecialchars($row['semester']);
                                        
                                        // Randomize Color based on Subject ID
                                        $color = $icon_colors[$row['subject_id'] % count($icon_colors)];
                                        
                                        $search_data = strtolower($safe_code . ' ' . $safe_name . ' ' . $safe_year . ' ' . $safe_sem);
                                    ?>
                                        <tr class="subject-row" data-search="<?= $search_data ?>">
                                            <td class="text-muted center-col fw-bold"><?= $count++; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="subject-icon-box" style="background-color: <?= $color ?>;">
                                                        <i class="fas fa-book"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 14.5px;"><?= $safe_name; ?></div>
                                                        <div class="mt-1"><span class="subject-code-badge"><?= $safe_code; ?></span></div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <span class="badge-soft"><?= $safe_year; ?></span>
                                            </td>
                                            
                                            <td>
                                                <span class="badge-soft"><?= $safe_sem; ?></span>
                                            </td>
                                            
                                            <td class="center-col">
                                                <a href="encode_grades.php?subject_id=<?= (int) $row['subject_id']; ?>"
                                                   class="btn-encode">
                                                    <i class="fas fa-external-link-square-alt me-2"></i> Encode Grades
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="center-col text-muted py-5">
                                            <i class="fas fa-folder-open text-secondary mb-3" style="font-size: 36px; opacity: 0.5;"></i><br>
                                            <span style="font-size: 15px; font-weight: 500;">No assigned subjects found.</span><br>
                                            <small>Please wait for the administrator to assign subjects to your account.</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr id="noLogsMessage" style="display: none;">
                                    <td colspan="5" class="center-col py-5 text-muted">
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
document.addEventListener("DOMContentLoaded", function() {
    // LIVE SEARCH LOGIC
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

<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}

include '../includes/footer.php';
?>