<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        // Agad na ikinakabit ang dark-mode para maiwasan ang white flash habang nagloload
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
            document.addEventListener("DOMContentLoaded", function() {
                document.body.classList.add('dark-mode');
            });
        }
    </script>

    <style>
        /* GLOBAL SAAS BACKGROUND */
        body {
            background-color: #F4F7FE;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s ease;
        }

        /* =========================================
            ULTIMATE DARK MODE OVERRIDES
           ========================================= */
           
        /* 1. Backgrounds */
        html.dark-mode body, body.dark-mode { background-color: #0F172A !important; color: #E2E8F0 !important; }
        html.dark-mode .content-wrapper, body.dark-mode .content-wrapper { background-color: #0F172A !important; }
        html.dark-mode .sidebar, body.dark-mode .sidebar { background-color: #1E293B !important; border-color: #334155 !important; }
        html.dark-mode .saas-card, html.dark-mode .card, body.dark-mode .saas-card, body.dark-mode .card { background-color: #1E293B !important; border-color: #334155 !important; }
        
        /* 2. Universal Texts (Pipiliting maging puti/light gray) */
        html.dark-mode p, html.dark-mode small, html.dark-mode label, html.dark-mode li,
        body.dark-mode p, body.dark-mode small, body.dark-mode label, body.dark-mode li { color: #CBD5E1 !important; }
        
        html.dark-mode .text-dark, 
        html.dark-mode h1, html.dark-mode h2, html.dark-mode h3, 
        html.dark-mode h4, html.dark-mode h5, html.dark-mode h6,
        html.dark-mode strong, html.dark-mode b, html.dark-mode .fw-bold,
        body.dark-mode .text-dark, 
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
        body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
        body.dark-mode strong, body.dark-mode b, body.dark-mode .fw-bold { 
            color: #F8FAFC !important; 
        }
        
        html.dark-mode .text-muted, html.dark-mode .text-secondary,
        body.dark-mode .text-muted, body.dark-mode .text-secondary { color: #94A3B8 !important; }

        /* 3. Specific Dashboard & Normal Table Fixes */
        html.dark-mode .card-value, body.dark-mode .card-value { color: #F8FAFC !important; }
        html.dark-mode .card-title-text, body.dark-mode .card-title-text { color: #94A3B8 !important; }
        
        html.dark-mode .table-saas th, body.dark-mode .table-saas th { background-color: #334155 !important; color: #CBD5E1 !important; border-color: #475569 !important; }
        html.dark-mode .table-saas td, body.dark-mode .table-saas td { border-color: #334155 !important; color: #F8FAFC !important; }
        html.dark-mode .table-saas tbody tr:hover, body.dark-mode .table-saas tbody tr:hover { background-color: #475569 !important; }
        
        /* 4. FIX PARA SA REPORTS.PHP (EXCEL TABLE) */
        html.dark-mode .excel-table th, 
        html.dark-mode .excel-table td,
        html.dark-mode .excel-table th[style], 
        html.dark-mode .excel-table td[style],
        body.dark-mode .excel-table th, 
        body.dark-mode .excel-table td,
        body.dark-mode .excel-table th[style], 
        body.dark-mode .excel-table td[style] {
            background-color: #1E293B !important;
            color: #F8FAFC !important;
            border-color: #334155 !important;
        }

        html.dark-mode .excel-table th.sticky-col-1,
        html.dark-mode .excel-table th.sticky-col-2,
        html.dark-mode .excel-table td.sticky-col-1,
        html.dark-mode .excel-table td.sticky-col-2,
        body.dark-mode .excel-table th.sticky-col-1,
        body.dark-mode .excel-table th.sticky-col-2,
        body.dark-mode .excel-table td.sticky-col-1,
        body.dark-mode .excel-table td.sticky-col-2 {
            background-color: #1E293B !important;
            color: #F8FAFC !important;
            border-right: 1px solid #334155 !important;
        }

        /* 5. Badges Fixes */
        html.dark-mode .subject-code-badge, 
        html.dark-mode .student-number-badge,
        body.dark-mode .subject-code-badge, 
        body.dark-mode .student-number-badge { 
            background-color: #0F172A !important; border-color: #334155 !important; color: #93C5FD !important; 
        }
        html.dark-mode .badge-soft, body.dark-mode .badge-soft { 
            background-color: #334155 !important; color: #E2E8F0 !important; border-color: #475569 !important; 
        }

        /* 6. Inputs & Forms */
        html.dark-mode .search-wrapper, 
        html.dark-mode .form-control-custom, 
        html.dark-mode .form-control-readonly, 
        html.dark-mode .form-select-custom,
        body.dark-mode .search-wrapper, 
        body.dark-mode .form-control-custom, 
        body.dark-mode .form-control-readonly, 
        body.dark-mode .form-select-custom { background-color: #0F172A !important; border-color: #475569 !important; color: #F8FAFC !important; }
        html.dark-mode .search-wrapper input, body.dark-mode .search-wrapper input { color: #F8FAFC !important; }
        html.dark-mode .form-control-readonly, body.dark-mode .form-control-readonly { color: #94A3B8 !important; opacity: 0.8; }
        
        /* 7. Modals & SweetAlert Tweaks for Dark Mode */
        html.dark-mode .custom-modal-box, body.dark-mode .custom-modal-box { background-color: #1E293B !important; color: #F8FAFC !important; border: 1px solid #334155 !important;}
        html.dark-mode .modal-input, body.dark-mode .modal-input { background-color: #0F172A !important; border-color: #475569 !important; color: #F8FAFC !important; }
        
        html.dark-mode .swal2-popup, body.dark-mode .swal2-popup { background-color: #1E293B !important; color: #F8FAFC !important; }
        html.dark-mode .swal2-title, body.dark-mode .swal2-title { color: #F8FAFC !important; }
        html.dark-mode .swal2-html-container, body.dark-mode .swal2-html-container { color: #94A3B8 !important; }

        /* 8. BRUTE-FORCE INLINE COLOR OVERRIDES */
        html.dark-mode [style*="color: #0F172A"], html.dark-mode [style*="color:#0F172A"],
        html.dark-mode [style*="color: #1E293B"], html.dark-mode [style*="color:#1E293B"],
        html.dark-mode [style*="color: #334155"], html.dark-mode [style*="color:#334155"],
        html.dark-mode [style*="color: #475569"], html.dark-mode [style*="color:#475569"],
        html.dark-mode [style*="color: #333"], html.dark-mode [style*="color:#333"],
        html.dark-mode [style*="color: #000"], html.dark-mode [style*="color:#000"],
        body.dark-mode [style*="color: #0F172A"], body.dark-mode [style*="color:#0F172A"],
        body.dark-mode [style*="color: #1E293B"], body.dark-mode [style*="color:#1E293B"],
        body.dark-mode [style*="color: #334155"], body.dark-mode [style*="color:#334155"],
        body.dark-mode [style*="color: #475569"], body.dark-mode [style*="color:#475569"],
        body.dark-mode [style*="color: #333"], body.dark-mode [style*="color:#333"],
        body.dark-mode [style*="color: #000"], body.dark-mode [style*="color:#000"] {
            color: #F8FAFC !important;
        }
        
        /* 9. Sidebar Links Fix & Red Logout Button */
        html.dark-mode .sidebar .nav-link, body.dark-mode .sidebar .nav-link { color: #94A3B8 !important; }
        html.dark-mode .sidebar .nav-link:hover, body.dark-mode .sidebar .nav-link:hover { color: #F8FAFC !important; background-color: #334155 !important;}
        html.dark-mode .sidebar .nav-link.active, body.dark-mode .sidebar .nav-link.active { color: #fff !important; background-color: #0047FF !important;}

        html.dark-mode .sidebar a.hover-red, body.dark-mode .sidebar a.hover-red { color: #F87171 !important; }
        html.dark-mode .sidebar a.hover-red:hover, body.dark-mode .sidebar a.hover-red:hover { background-color: rgba(248, 113, 113, 0.15) !important; color: #FCA5A5 !important; }

        html.dark-mode .fas.text-dark, body.dark-mode .fas.text-dark { color: #94A3B8 !important; }

    </style>
</head>
<body class="<?= (isset($_SESSION['theme']) &&$_SESSION['theme'] === 'dark') ? 'dark-mode' : '' ?>">
<script>
    // Siguraduhing laging may dark-mode class ang body at html kung naka-save sa localStorage
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
    }
</script>