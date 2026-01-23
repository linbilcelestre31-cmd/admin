<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$isSuperAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');
$current_page = basename($_SERVER['PHP_SELF']);
$is_dashboard = ($current_page == 'dashboard.php');

function get_nav_link($tab, $is_dashboard, $isSuperAdmin)
{
    if ($is_dashboard) {
        return "#\" onclick=\"event.preventDefault(); handleSidebarNav('$tab'); return false;\"";
    } else {
        if ($isSuperAdmin && $tab === 'dashboard') {
            return "../Super-admin/Dashboard.php\"";
        }
        return "../Modules/dashboard.php?tab=$tab\"";
    }
}
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $isSuperAdmin ? '../Super-admin/Dashboard.php' : '../Modules/dashboard.php' ?>" class="logo-link"
            title="Go to Dashboard">
            <div class="logo-area">
                <div class="logo" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <img src="../assets/image/logo.png" alt="Atiéra Logo"
                        style="height:60px; width:auto; display:block; margin:0 auto; transition: all 0.3s; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                    <?php if ($isSuperAdmin): ?>
                        <div
                            style="background: rgba(212, 175, 55, 0.15); color: #d4af37; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border: 1px solid rgba(212, 175, 55, 0.3); display: inline-block;">
                            Administrative
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-title">Settings</div>
        <ul class="nav-links">
            <li>
                <a href="<?= $isSuperAdmin ? '../Super-admin/Settings.php' : '../include/Settings.php' ?>"
                    class="<?= ($current_page == 'Settings.php') ? 'active' : '' ?>">
                    <span class="icon-img-placeholder">👤</span> Account
                </a>
            </li>
        </ul>
    </div>

    <div class="nav-section">
        <div class="nav-title">Main Navigation</div>
        <ul class="nav-links">
            <li><a href="<?= get_nav_link('dashboard', $is_dashboard, $isSuperAdmin) ?>"
                    class=" <?= ($is_dashboard && (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard')) ? 'active' : '' ?>"
                    data-tab="dashboard">
                    <span class="icon-img-placeholder">📊</span> Dashboard
                </a></li>
            <li class="has-dropdown">
                <a href="#" onclick="event.preventDefault(); toggleDropdown('facilities-dropdown');" class="dropdown-toggle">
                    <span class="icon-img-placeholder">🏢</span> Facilities
                    <span class="dropdown-arrow">▼</span>
                </a>
                <ul id="facilities-dropdown" class="dropdown-menu">
                    <li><a href="<?= get_nav_link('maintenance', $is_dashboard, $isSuperAdmin) ?>"
                            class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'maintenance') ? 'active' : '' ?>"
                            data-tab="maintenance">
                            <span class="icon-img-placeholder">🔧</span> Maintenance
                        </a></li>
                </ul>
            </li>
            <li class="has-dropdown">
                <a href="#" onclick="event.preventDefault(); toggleDropdown('reservations-dropdown');" class="dropdown-toggle">
                    <span class="icon-img-placeholder">📅</span> Reservations
                    <span class="dropdown-arrow">▼</span>
                </a>
                <ul id="reservations-dropdown" class="dropdown-menu">
                    <li><a href="<?= get_nav_link('reservations', $is_dashboard, $isSuperAdmin) ?>"
                            class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'reservations') ? 'active' : '' ?>"
                            data-tab="reservations">
                            <span class="icon-img-placeholder">📅</span> All Reservations
                        </a></li>
                </ul>
            </li>
            <li><a href="<?= get_nav_link('management', $is_dashboard, $isSuperAdmin) ?>"
                    class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'management') ? 'active' : '' ?>"
                    data-tab="management">
                    <span class="icon-img-placeholder">⚙️</span> Management
                </a></li>
            <li><a href="../Modules/legalmanagement.php"
                    class="<?= ($current_page == 'legalmanagement.php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <span class="icon-img-placeholder">⚖️</span> Legal Management
                </a></li>
            <li><a href="../Modules/document management(archiving).php"
                    class="<?= ($current_page == 'document management(archiving).php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <span class="icon-img-placeholder">🗄️</span> Document Archiving
                </a></li>
            <li><a href="../Modules/Visitor-logs.php"
                    class="<?= ($current_page == 'Visitor-logs.php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <span class="icon-img-placeholder">🚶</span> Visitor Management
                </a></li>


        </ul>
    </div>

    <div class="nav-section">
        <div class="nav-title">External Links</div>
        <ul class="nav-links">
            <li><a href="<?= get_nav_link('reports', $is_dashboard, $isSuperAdmin) ?>"
                    class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'reports') ? 'active' : '' ?>"
                    data-tab="reports">
                    <span class="icon-img-placeholder">📈</span> Reports
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
            div.innerHTML = '<iframe src="../animation/loading.html" style="width:100%; height:100%; border:none; background:transparent;" allowtransparency="true"></iframe>';
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
                    // Check if target is one of the allowed modules for animation
                    const isTargetModule = href.includes('legalmanagement.php') || href.includes('document management(archiving).php');

                    if (isTargetModule) {
                        e.preventDefault();
                        if (typeof window.runLoadingAnimation === 'function') {
                            window.runLoadingAnimation(() => {
                                window.location.href = href;
                            }, true);
                        } else {
                            window.location.href = href;
                        }
                    }
                    // Otherwise, do nothing (let default navigation happen without animation)
                });
            }
        });
    });

    // 4. Handle Tab Switching (Called by onclick)
    window.handleSidebarNav = function (tab) {
        // Allow animation for specific tabs if needed, or just default behavior
        if (typeof switchTab === 'function') switchTab(tab);
    };

    // 5. Handle Dropdown Toggle
    window.toggleDropdown = function (dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        const arrow = dropdown.previousElementSibling.querySelector('.dropdown-arrow');
        
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        } else {
            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            document.querySelectorAll('.dropdown-arrow').forEach(arr => {
                arr.style.transform = 'rotate(0deg)';
            });
            
            dropdown.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        }
    };
</script>