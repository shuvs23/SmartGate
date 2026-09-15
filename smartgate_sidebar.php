<?php
/*
=========================================================
SMARTGATE PERSISTENT SIDEBAR
=========================================================
Required session variables:
$_SESSION['role']
$_SESSION['full_name']
=========================================================
*/

require_once __DIR__ . "/security.php";
smartgate_start_session();

$currentPage = basename($_SERVER['PHP_SELF']);

$role = $_SESSION['role'] ?? '';
$fullName = $_SESSION['full_name'] ?? 'User';

$isSuperAdmin = ($role === 'super_admin');
$isMIS        = ($role === 'MIS');
$isSecurity   = ($role === 'Security');
$isCCDU       = ($role === 'CCDU');
$isGuidance   = ($role === 'Guidance');
$isIGP        = ($role === 'IGP');
$isLibrary    = ($role === 'Library');

/*
=========================================================
ACTIVE PAGE FUNCTION
=========================================================
*/
function sidebarActive($page)
{
    global $currentPage;

    return $currentPage === $page ? 'active' : '';
}
?>

<style>

/* =========================================================
   SMARTGATE SIDEBAR
   ========================================================= */

.smartgate-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;

    width: 255px;
    height: 100vh;
    max-height: 100vh;

    background: linear-gradient(180deg, #083b82 0%, #062b63 100%);
    color: #ffffff;

    z-index: 9999;

    display: flex;
    flex-direction: column;

    border-right: 1px solid rgba(255,255,255,0.14);

    overflow-y: auto;
    overscroll-behavior: contain;
}


/* =========================================================
   BRAND
   ========================================================= */

.smartgate-brand {
    padding: 18px 18px 16px;

    border-bottom: 1px solid rgb(255, 255, 255);
}

.smartgate-brand-title {
    font-size: 18px;
    font-weight: 700;

    color: #ffffff;

    line-height: 1.2;
}

.smartgate-brand-subtitle {
    margin-top: 4px;

    font-size: 10px;

    color: #c8ddfa;

    line-height: 1.4;
}


/* =========================================================
   USER
   ========================================================= */

.smartgate-user {
    padding: 15px 18px;

    border-bottom: 1px solid rgba(248, 243, 243, 0.14);
}

.smartgate-user-name {
    font-size: 13px;
    font-weight: 600;

    color: #ffffff;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.smartgate-user-role {
    margin-top: 4px;

    font-size: 11px;

    color: #c8ddfa;
}


/* =========================================================
   NAVIGATION CONTAINER
   ========================================================= */

.smartgate-nav {
    flex: 1;

    padding: 14px 11px;
}


/* =========================================================
   SECTION TITLE
   ========================================================= */

.smartgate-section-title {
    padding: 7px 10px 7px;

    color: #a9c9f3;

    font-size: 10px;
    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.8px;
}


/* =========================================================
   NAVIGATION LINK
   ========================================================= */

.smartgate-nav-link {
    display: flex;
    align-items: center;

    width: 100%;

    min-height: 40px;

    margin-bottom: 3px;

    padding: 9px 10px;

    border-radius: 8px;

    color: #e4efff;

    text-decoration: none;

    font-size: 13px;
    font-weight: 500;

    transition:
        background 0.15s ease,
        color 0.15s ease;
}


/* =========================================================
   LINK ICON
   ========================================================= */

.smartgate-nav-icon {
    width: 23px;

    margin-right: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    font-size: 14px;
}


/* =========================================================
   LINK TEXT
   ========================================================= */

.smartgate-nav-text {
    flex: 1;

    line-height: 1.3;
}


/* =========================================================
   HOVER
   ========================================================= */

.smartgate-nav-link:hover {
    background: rgba(255,255,255,0.13);

    color: #ffffff;

    text-decoration: none;
}


/* =========================================================
   ACTIVE
   ========================================================= */

.smartgate-nav-link.active {
    background: #2563eb;

    color: #ffffff;

    font-weight: 600;

    box-shadow:
        0 3px 8px rgba(37, 99, 235, 0.20);
}

.smartgate-nav-link.active:hover {
    background: #2563eb;

    color: #ffffff;
}


/* =========================================================
   FOOTER
   ========================================================= */

.smartgate-footer {
    padding: 13px 12px;

    border-top: 1px solid rgba(255,255,255,0.08);
}

.smartgate-footer-brand {
    padding: 0 7px 12px;

    color: #ffffff;

    font-size: 12px;
    font-weight: 700;

    line-height: 1.3;
}

.smartgate-footer-description {
    margin-top: 4px;

    color: #64748b;

    font-size: 9px;
    font-weight: 400;

    line-height: 1.4;
}


/* =========================================================
   LOGOUT
   ========================================================= */

.smartgate-logout {
    display: flex;
    align-items: center;

    width: 100%;

    min-height: 40px;

    padding: 9px 10px;

    border-radius: 8px;

    color: #cbd5e1;

    text-decoration: none;

    font-size: 13px;
    font-weight: 500;
}

.smartgate-logout:hover {
    background: rgba(255,255,255,0.13);

    color: #ffffff;

    text-decoration: none;
}


/* =========================================================
   MAIN CONTENT
   ========================================================= */

.smartgate-main {
    margin-left: 255px;

    min-height: 100vh;

    padding: 28px;
}

/* Legacy pages retain their data markup but use the shared sidebar. */
.legacy-shell > .sidebar,
.legacy-shell > .main > .topbar {
    display: none !important;
}

.legacy-shell > .main {
    width: calc(100% - 255px);
    min-height: 100vh;
    margin-left: 255px;
}

.legacy-shell > .main.sg-page-shell {
    padding: 0;
}


/* =========================================================
   MOBILE MENU BUTTON
   ========================================================= */

.smartgate-mobile-toggle {
    display: none;

    position: fixed;

    top: 14px;
    left: 14px;

    width: 42px;
    height: 42px;

    border: none;

    border-radius: 8px;

    background: #083b82;

    color: #ffffff;

    font-size: 20px;

    cursor: pointer;

    z-index: 10001;

    box-shadow:
        0 4px 12px rgba(0,0,0,0.18);
}


/* =========================================================
   MOBILE OVERLAY
   ========================================================= */

.smartgate-overlay {
    display: none;

    position: fixed;

    inset: 0;

    background: rgba(6,43,99,0.45);

    z-index: 9998;
}


/* =========================================================
   SCROLLBAR
   ========================================================= */

.smartgate-sidebar::-webkit-scrollbar {
    width: 5px;
}

.smartgate-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.smartgate-sidebar::-webkit-scrollbar-thumb {
    background: #5e8dcc;

    border-radius: 10px;
}

.smartgate-sidebar::-webkit-scrollbar-thumb:hover {
    background: #80a8dc;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 900px) {

    .smartgate-sidebar {
        transform: translateX(-100%);

        transition:
            transform 0.2s ease;
    }

    .smartgate-sidebar.open {
        transform: translateX(0);
    }

    .smartgate-main {
        margin-left: 0;

        padding: 20px;
    }

    .legacy-shell > .main {
        width: 100%;
        margin-left: 0;
    }

    .smartgate-mobile-toggle {
        display: flex;

        align-items: center;
        justify-content: center;
    }

    .smartgate-overlay.show {
        display: block;
    }
}


/* =========================================================
   PRINT
   ========================================================= */

@media print {

    .smartgate-sidebar,
    .smartgate-mobile-toggle,
    .smartgate-overlay {
        display: none !important;
    }

    .smartgate-main {
        margin-left: 0 !important;

        padding: 0 !important;
    }

}

</style>


<!-- =====================================================
     MOBILE MENU BUTTON
     ===================================================== -->

<button
    type="button"
    class="smartgate-mobile-toggle"
    id="smartgateMobileToggle"
    aria-label="Open navigation"
>
    ☰
</button>


<!-- =====================================================
     MOBILE OVERLAY
     ===================================================== -->

<div
    class="smartgate-overlay"
    id="smartgateOverlay"
></div>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside
    class="smartgate-sidebar"
    id="smartgateSidebar"
>


    <!-- =================================================
         BRAND
         ================================================= -->

    <div class="smartgate-brand">

        <div class="smartgate-brand-title">
            SmartGate
        </div>

        <div class="smartgate-brand-subtitle">
            Student Entry Management System
        </div>

    </div>


    <!-- =================================================
         USER
         ================================================= -->

    <div class="smartgate-user">

        <div class="smartgate-user-name">
            <?= htmlspecialchars($fullName) ?>
        </div>

        <div class="smartgate-user-role">
            <?= htmlspecialchars($role) ?>
        </div>

    </div>


    <!-- =================================================
         NAVIGATION
         ================================================= -->

    <nav class="smartgate-nav">


        <!-- =================================================
             MAIN
             ================================================= -->

        <div class="smartgate-section-title">
            Main
        </div>


        <!-- DASHBOARD -->

        <a
            href="admin.php"
            class="smartgate-nav-link <?= sidebarActive('admin.php') ?>"
        >

            <span class="smartgate-nav-icon">
                🏠
            </span>

            <span class="smartgate-nav-text">
                Dashboard
            </span>

        </a>


        <?php if ($isSuperAdmin || $isMIS): ?>

            <!-- STUDENT MANAGEMENT -->

            <a
                href="student_management.php"
                class="smartgate-nav-link <?= sidebarActive('student_management.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    🎓
                </span>

                <span class="smartgate-nav-text">
                    Student Management
                </span>

            </a>


            <!-- MIS STUDENT UPLOAD -->

            <a
                href="mis_upload.php"
                class="smartgate-nav-link <?= sidebarActive('mis_upload.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    📤
                </span>

                <span class="smartgate-nav-text">
                    MIS Student Upload
                </span>

            </a>


            <!-- STUDENT ATTENDANCE -->

            <a
                href="student_attendance.php"
                class="smartgate-nav-link <?= sidebarActive('student_attendance.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    👨‍🎓
                </span>

                <span class="smartgate-nav-text">
                    Student Attendance
                </span>

            </a>

        <?php endif; ?>


        <?php if (
            $isSuperAdmin ||
            $isMIS ||
            $isSecurity
        ): ?>

            <!-- ATTENDANCE LOGS -->

            <a
                href="attendance_logs.php"
                class="smartgate-nav-link <?= sidebarActive('attendance_logs.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    🕒
                </span>

                <span class="smartgate-nav-text">
                    Attendance Logs
                </span>

            </a>

        <?php endif; ?>


        <?php if ($isSuperAdmin || $isMIS): ?>

            <!-- ANALYTICS -->

            <a
                href="analytics.php"
                class="smartgate-nav-link <?= sidebarActive('analytics.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    📊
                </span>

                <span class="smartgate-nav-text">
                    Analytics
                </span>

            </a>

        <?php endif; ?>


        <?php if ($isSuperAdmin || $isSecurity): ?>

            <!-- BYPASS -->

            <a
                href="bypass.php"
                class="smartgate-nav-link <?= sidebarActive('bypass.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    🚪
                </span>

                <span class="smartgate-nav-text">
                    Bypass
                </span>

            </a>


            <!-- BYPASS LOGS -->

            <a
                href="bypass_logs.php"
                class="smartgate-nav-link <?= sidebarActive('bypass_logs.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    📋
                </span>

                <span class="smartgate-nav-text">
                    Bypass Logs
                </span>

            </a>

        <?php endif; ?>


        <?php if (
            $isSuperAdmin ||
            $isSecurity ||
            $isCCDU ||
            $isGuidance
        ): ?>

            <!-- STUDENT VIOLATIONS -->

            <a
                href="violations.php"
                class="smartgate-nav-link <?= sidebarActive('violations.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    ⚠️
                </span>

                <span class="smartgate-nav-text">
                    Student Violations
                </span>

            </a>

        <?php endif; ?>


        <!-- =================================================
             IGP / LIBRARY STUDENT ACCESS
             ================================================= -->

        <?php if (
            $isSuperAdmin ||
            $isMIS ||
            $isIGP ||
            $isLibrary
        ): ?>

            <a
                href="student_access.php"
                class="smartgate-nav-link <?= sidebarActive('student_access.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    🎫
                </span>

                <span class="smartgate-nav-text">
                    Student Access
                </span>

            </a>

        <?php endif; ?>


        <!-- =================================================
             RECORDS & REPORTS
             ================================================= -->

        <?php if ($isSuperAdmin || $isMIS): ?>

            <div class="smartgate-section-title">
                Records &amp; Reports
            </div>


            <!-- MIS REPORTS -->

            <a
                href="mis_reports.php"
                class="smartgate-nav-link <?= sidebarActive('mis_reports.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    📄
                </span>

                <span class="smartgate-nav-text">
                    MIS Reports
                </span>

            </a>


            <!-- ARCHIVED LOGS -->

            <a
                href="archived_logs.php"
                class="smartgate-nav-link <?= sidebarActive('archived_logs.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    🗄️
                </span>

                <span class="smartgate-nav-text">
                    Archived Logs
                </span>

            </a>

        <?php endif; ?>


        <!-- =================================================
             ADMINISTRATION
             ================================================= -->

        <?php if ($isSuperAdmin): ?>

            <div class="smartgate-section-title">
                Administration
            </div>


            <!-- USER MANAGEMENT -->

            <a
                href="user_management.php"
                class="smartgate-nav-link <?= sidebarActive('user_management.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    👤
                </span>

                <span class="smartgate-nav-text">
                    User Management
                </span>

            </a>


            <!-- AUDIT TRAIL -->

            <a
                href="audit_logs.php"
                class="smartgate-nav-link <?= sidebarActive('audit_logs.php') ?>"
            >

                <span class="smartgate-nav-icon">
                    🔎
                </span>

                <span class="smartgate-nav-text">
                    Audit Trail
                </span>

            </a>

        <?php endif; ?>


        <!-- =================================================
             SYSTEM
             ================================================= -->

        <div class="smartgate-section-title">
            System
        </div>


        <!-- DISPLAY -->

        <a
            href="display.php"
            class="smartgate-nav-link <?= sidebarActive('display.php') ?>"
        >

            <span class="smartgate-nav-icon">
                🖥️
            </span>

            <span class="smartgate-nav-text">
                SmartGate Display
            </span>

        </a>


    </nav>


    <!-- =================================================
         FOOTER
         ================================================= -->

    <div class="smartgate-footer">

        <div class="smartgate-footer-brand">

            SmartGate

            <div class="smartgate-footer-description">

                An IoT-Based Student Entry System
                with QR Code

            </div>

        </div>


        <!-- LOGOUT -->

        <a
            href="logout.php"
            class="smartgate-logout"
        >

            <span class="smartgate-nav-icon">
                🚪
            </span>

            <span class="smartgate-nav-text">
                Logout
            </span>

        </a>

    </div>

</aside>


<script>

/* =========================================================
   SMARTGATE MOBILE SIDEBAR
   ========================================================= */

(function () {

    const sidebar =
        document.getElementById('smartgateSidebar');

    const toggle =
        document.getElementById('smartgateMobileToggle');

    const overlay =
        document.getElementById('smartgateOverlay');


    if (!sidebar || !toggle || !overlay) {
        return;
    }


    function openSidebar() {

        sidebar.classList.add('open');

        overlay.classList.add('show');

    }


    function closeSidebar() {

        sidebar.classList.remove('open');

        overlay.classList.remove('show');

    }


    /* Keep the sidebar at the same scroll position after navigation. */
    const sidebarScrollKey = 'smartgateSidebarScrollTop';
    const savedScrollTop = Number.parseInt(
        sessionStorage.getItem(sidebarScrollKey) || '0',
        10
    );

    if (Number.isFinite(savedScrollTop) && savedScrollTop > 0) {
        window.requestAnimationFrame(function () {
            sidebar.scrollTop = savedScrollTop;
        });
    }

    sidebar.addEventListener('scroll', function () {
        sessionStorage.setItem(
            sidebarScrollKey,
            String(sidebar.scrollTop)
        );
    });


    toggle.addEventListener(
        'click',
        function () {

            if (sidebar.classList.contains('open')) {

                closeSidebar();

            } else {

                openSidebar();

            }

        }
    );


    overlay.addEventListener(
        'click',
        closeSidebar
    );


    /*
     * Close mobile sidebar after clicking a link.
     */

    const links =
        sidebar.querySelectorAll('a');

    links.forEach(function (link) {

        link.addEventListener(
            'click',
            function () {

                sessionStorage.setItem(
                    sidebarScrollKey,
                    String(sidebar.scrollTop)
                );

                if (
                    window.innerWidth <= 900
                ) {

                    closeSidebar();

                }

            }
        );

    });

})();

</script>

<script src="smartgate_activity.js"></script>
