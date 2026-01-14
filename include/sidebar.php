<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_dashboard = ($current_page == 'facilities-reservation.php');

function get_nav_link($tab, $is_dashboard)
{
    if ($is_dashboard) {
        return "#\" onclick=\"event.preventDefault(); handleSidebarNav('$tab'); return false;\"";
    } else {
        return "../Modules/facilities-reservation.php?tab=$tab\"";
    }
}
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <a href="../Modules/facilities-reservation.php" class="logo-link" title="Go to Dashboard">
            <div class="logo-area">
                <div class="logo">
                    <img src="../assets/image/logo.png" alt="Atiéra Logo"
                        style="height:80px; width:auto; display:block; margin:0 auto;">
                </div>
            </div>
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-title">Main Navigation</div>
        <ul class="nav-links">
            <li><a href="<?= get_nav_link('dashboard', $is_dashboard) ?>" class=" <?= ($is_dashboard && (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard')) ? 'active' : '' ?>" data-tab="dashboard">
                    <span class="icon-img-placeholder">📊</span> Dashboard
                </a></li>
            <li><a href="<?= get_nav_link('facilities', $is_dashboard) ?>" class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'facilities') ? 'active' : '' ?>" data-tab="facilities">
                    <span class="icon-img-placeholder">🏢</span> Facilities
                </a></li>
            <li><a href="<?= get_nav_link('reservations', $is_dashboard) ?>" class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'reservations') ? 'active' : '' ?>" data-tab="reservations">
                    <span class="icon-img-placeholder">📅</span> Reservations
                </a></li>
            <li><a href="<?= get_nav_link('calendar', $is_dashboard) ?>" class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'calendar') ? 'active' : '' ?>" data-tab="calendar">
                    <span class="icon-img-placeholder">📅</span> Calendar
                </a></li>
            <li><a href="<?= get_nav_link('management', $is_dashboard) ?>" class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'management') ? 'active' : '' ?>" data-tab="management">
                    <span class="icon-img-placeholder">⚙️</span> Management
                </a></li>
            <li><a href="../Modules/legalmanagement.php"
                    class="<?= ($current_page == 'legalmanagement.php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <span class="icon-img-placeholder">⚖️</span> Legal Management
                </a></li>
            <li><a href="document management(archiving).php"
                    class="<?= ($current_page == 'document management(archiving).php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <span class="icon-img-placeholder">🗄️</span> Document Archiving
                </a></li>
            <li><a href="../Modules/Visitor-logs.php"
                    class="<?= ($current_page == 'Visitor-logs.php') ? 'active' : '' ?>">
                    <span class="icon-img-placeholder">🚶</span> Visitors Log
                </a></li>
        </ul>
    </div>

    <div class="nav-section">
        <div class="nav-title">External Links</div>
        <ul class="nav-links">
            <li><a href="<?= get_nav_link('reports', $is_dashboard) ?>" class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'reports') ? 'active' : '' ?>" data-tab="reports">
                    <span class="icon-img-placeholder">📈</span> Reports
                </a></li>
            <li><a href="../include/Settings.php" class="<?= ($current_page == 'Settings.php') ? 'active' : '' ?>">
                    <span class="icon-img-placeholder">⚙️</span> Settings
                </a></li>
        </ul>
    </div>
</nav>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // 1. Inject Overlay if missing (Avoid duplicates)
        if (!document.getElementById('loadingOverlay')) {
            const div = document.createElement('div');
            div.id = 'loadingOverlay';
            div.style.cssText = 'display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;';
            div.innerHTML = '<iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"></iframe>';
            document.body.appendChild(div);
        }

        // 2. Define Global Loader Function
        window.runLoadingAnimation = function (callback, isRedirect = false) {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'block';
                loader.style.opacity = '1';
                const iframe = loader.querySelector('iframe');
                if (iframe) iframe.src = iframe.src;

                setTimeout(() => {
                    if (callback) callback();
                    if (!isRedirect) {
                        // Fade out if staying on page
                        loader.style.opacity = '0';
                        setTimeout(() => { loader.style.display = 'none'; }, 500);
                    }
                }, 5000); // 5s Duration
            } else {
                if (callback) callback();
            }
        };

        // 3. Intercept Normal URL Links in Sidebar
        const links = document.querySelectorAll('.sidebar a');
        links.forEach(a => {
            const href = a.getAttribute('href');
            const onclick = a.getAttribute('onclick');

            // If it's a direct URL link (not hash, not handled by onclick)
            if (href && href !== '#' && !href.startsWith('javascript') && !onclick) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (typeof window.runLoadingAnimation === 'function') {
                        window.runLoadingAnimation(() => {
                            window.location.href = href;
                        }, true);
                    } else {
                        window.location.href = href;
                    }
                });
            }
        });
    });

    // 4. Handle Tab Switching (Called by onclick)
    window.handleSidebarNav = function (tab) {
        if (typeof window.runLoadingAnimation === 'function') {
            window.runLoadingAnimation(() => {
                if (typeof switchTab === 'function') switchTab(tab);
            }, false);
        } else {
            if (typeof switchTab === 'function') switchTab(tab);
        }
    };
</script>