<?php
session_start();
include '../includes/config.php';

date_default_timezone_set('Asia/Manila');

// Gumamit tayo ng "or" na salita imbes na mga linya para iwas sa \vert text error
if (!isset($_SESSION['user_id']) or !isset($_SESSION['role']) or$_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit;
}

$professor_id = (int)$_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Professor');

function getProfessorCount(mysqli $conn, string$sql): int
{
    $result = mysqli_query($conn,$sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

// Assigned subjects
$subjects_count = getProfessorCount($conn, "
    SELECT COUNT(*) AS total
    FROM subjects
    WHERE professor_id = {$professor_id}
");

// Submitted grades
$submitted_count = getProfessorCount($conn, "
    SELECT COUNT(*) AS total
    FROM grades
    WHERE professor_id = {$professor_id}
    AND is_submitted = 1
");

// Pending unlock requests
$pending_requests = getProfessorCount($conn, "
    SELECT COUNT(*) AS total
    FROM unlock_requests ur
    JOIN grades g ON ur.grade_id = g.grade_id
    WHERE g.professor_id = {$professor_id}
    AND ur.request_status = 'Pending'
");

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* CONTENT & TOP NAVBAR (SAAS STYLE) */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }

    /* CARDS */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: 0.3s; }
    .saas-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
    
    .card-title-text { color: #64748B; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
    .card-value { font-size: 28px; font-weight: 800; color: #0F172A; line-height: 1.2; }
    .card-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    
    /* TREND INDICATORS */
    .trend-up { color: #10B981; font-size: 12.5px; font-weight: 600; }
    .trend-neutral { color: #64748B; font-size: 12.5px; font-weight: 500; }
    .trend-warning { color: #F59E0B; font-size: 12.5px; font-weight: 600; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> Dashboard <span class="mx-1">></span> <strong class="text-dark">Overview</strong>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Professor</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Overview</h3>
                        <p class="text-muted mb-0" style="font-size: 14.5px;">Welcome back, <?= $full_name ?>. Here's your teaching summary.</p>
                    </div>
                    <div class="text-muted fw-medium" style="font-size: 14px;">
                        <i class="far fa-calendar-alt me-2"></i> <?= date('F d, Y') ?>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="saas-card h-100 border-start border-4 border-primary">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Assigned Subjects</div>
                                    <div class="card-value"><?= number_format($subjects_count); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #EFF6FF; color: #0047FF;">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                            <div class="trend-up"><i class="fas fa-chalkboard-teacher me-1"></i> Active Classes</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="saas-card h-100 border-start border-4 border-success">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Submitted Grades</div>
                                    <div class="card-value"><?= number_format($submitted_count); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #ECFDF5; color: #10B981;">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                            <div class="trend-up text-success"><i class="fas fa-check-circle me-1"></i> Completed</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="saas-card h-100 border-start border-4 border-warning">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Pending Unlocks</div>
                                    <div class="card-value"><?= number_format($pending_requests); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #FFFBEB; color: #F59E0B;">
                                    <i class="fas fa-unlock-alt"></i>
                                </div>
                            </div>
                            <?php if($pending_requests > 0): ?>
                                <div class="trend-warning"><i class="fas fa-hourglass-half me-1"></i> Waiting for approval</div>
                            <?php else: ?>
                                <div class="trend-neutral"><i class="fas fa-check text-success me-1"></i> All clear</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="saas-card mt-2">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-info-circle fs-5"></i>
                        </div>
                        <h5 class="fw-bold mb-0 text-dark">System Guide</h5>
                    </div>
                    <div class="ps-5 ms-2 text-muted" style="line-height: 1.7; font-size: 14.5px;">
                        <p class="mb-2">
                            This dashboard allows you to seamlessly monitor your assigned subjects, track your submitted grading sheets,
                            and view the status of your unlock requests.
                        </p>
                        <ul class="mb-0">
                            <li><strong class="text-dark">My Subjects:</strong> Start encoding grades for your assigned classes.</li>
                            <li><strong class="text-dark">Submitted Grades:</strong> Review your previously finalized and locked records.</li>
                            <li><strong class="text-dark">Unlock Requests:</strong> Ask the administrator to re-open a locked grading sheet if you need to make edits.</li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- SESSION RECOVERY CHECKER -->
<!-- ============================================================== -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const profId = '<?= $professor_id ?>';
        if (!profId) return;

        const metaKey = 'draft_meta_prof_' + profId;
        const draftMeta = localStorage.getItem(metaKey);

        if (draftMeta) {
            try {
                const meta = JSON.parse(draftMeta);
                const storageKey = 'draft_grades_sub_' + meta.subject_id + '_per_' + meta.period;
                const draftData = localStorage.getItem(storageKey);

                // Kapag may naiwang input (draftData ay existing)
                if (draftData) {
                    Swal.fire({
                        title: 'Unsaved Grades Detected!',
                        html: `It looks like you have unsaved grades for <br><strong class="text-primary fs-5">${meta.subject_code} (${meta.period} Period)</strong>.<br><br>Would you like to continue encoding?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#0047FF', // Blue
                        cancelButtonColor: '#94A3B8',  // Slate/Gray
                        confirmButtonText: '<i class="fas fa-edit me-1"></i> Yes, continue',
                        cancelButtonText: 'Discard Draft',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redirect papunta sa page ng subject na may draft
                            window.location.href = 'encode_grades.php?subject_id=' + meta.subject_id + '&period=' + meta.period;
                        } else {
                            // User chose to discard, clear the draft from localStorage
                            localStorage.removeItem(storageKey);
                            localStorage.removeItem(metaKey);
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Discarded',
                                text: 'Your draft has been cleared.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    });
                } else {
                    localStorage.removeItem(metaKey);
                }
            } catch(e) {
                localStorage.removeItem(metaKey);
                console.error("Failed to parse draft meta data.");
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>