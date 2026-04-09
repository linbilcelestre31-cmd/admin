<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$isSuperAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');
$current_page = basename($_SERVER['PHP_SELF']);
$is_dashboard = ($current_page == 'dashboard.php');

require_once __DIR__ . '/Config.php';

function get_nav_link($tab, $is_dashboard, $isSuperAdmin) {
    if ($isSuperAdmin && $tab === 'dashboard') {
        return rtrim(getBaseUrl(), '/') . "/Super-admin/Dashboard.php";
    }
    return rtrim(getBaseUrl(), '/') . "/Modules/dashboard.php?tab=$tab";
}
?>
<style>
    /* Shared Mobile Sidebar Styles */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            height: 100vh !important;
            transform: translateX(-100%) !important;
            z-index: 10000 !important;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            width: 280px !important;
            box-shadow: 10px 0 30px rgba(0,0,0,0.5) !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .sidebar .nav-links a {
            color: #cbd5e0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
        }
        .sidebar .nav-links a i {
            color: #cbd5e0 !important;
            width: 20px !important;
            text-align: center !important;
        }
        .sidebar .nav-links a div {
            color: inherit !important;
        }
        .sidebar .nav-title, .sidebar .logo-area div {
            display: block !important;
        }
        .sidebar .dropdown-arrow {
            display: inline-block !important;
        }
        .sidebar.active {
            transform: translateX(0) !important;
        }
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999 !important;
            transition: opacity 0.3s ease;
        }
        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            padding-bottom: 80px !important; /* Space for bottom nav */
        }
        .mobile-bottom-nav {
            display: flex !important;
        }
    }

    /* Bottom Nav Styles */
    .mobile-bottom-nav {
        display: none;
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 40px);
        max-width: 450px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        height: 65px;
        border-radius: 20px;
        z-index: 9998;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 0 10px;
        justify-content: space-around;
        align-items: center;
    }

    .bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #94a3b8;
        font-size: 0.7rem;
        font-weight: 600;
        transition: all 0.3s;
        gap: 4px;
        flex: 1;
    }

    .bottom-nav-item i {
        font-size: 1.2rem;
        transition: all 0.3s;
    }

    .bottom-nav-item.active {
        color: #3b82f6;
    }

    .bottom-nav-item.active i {
        transform: translateY(-5px);
        color: #3b82f6;
    }

    /* Management Radial Menu Styles */
    .mgmt-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(8px);
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.4s ease;
    }
    
    .mgmt-overlay.show {
        display: block;
        opacity: 1;
    }

    .mgmt-radial-wrapper {
        position: fixed;
        bottom: 40px;
        right: 40px;
        width: 320px;
        height: 320px;
        z-index: 10001;
        pointer-events: none;
        visibility: hidden;
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        transform: scale(0.5) translate(50%, 50%);
        opacity: 0;
    }

    .mgmt-overlay.show + .mgmt-radial-wrapper {
        visibility: visible;
        pointer-events: auto;
        transform: scale(1) translate(0, 0);
        opacity: 1;
    }

    /* The 'Circle on the side' background */
    .mgmt-radial-bg {
        position: absolute;
        bottom: -50px;
        right: -50px;
        width: 380px;
        height: 380px;
        background: white;
        border-radius: 50%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .mgmt-radial-item {
        position: absolute;
        width: 100px;
        height: 100px;
        background: transparent;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 2;
        gap: 8px;
    }

    .mgmt-radial-item:hover {
        transform: translateY(-8px) scale(1.05);
        box-shadow: 0 20px 35px rgba(0,0,0,0.12);
        border-color: #6366f1;
    }

    .mgmt-radial-item i {
        font-size: 2.2rem;
        transition: transform 0.3s;
    }

    .mgmt-radial-item:hover i {
        transform: scale(1.1);
    }

    .mgmt-radial-item span {
        font-size: 0.85rem;
        font-weight: 700;
        text-align: center;
        color: #1e293b;
        line-height: 1;
        margin-top: 5px;
    }

    /* Positioning along the arc - further refined following the arrow UP */
    .item-1 { top: 10px; left: 50px; }      /* Facilities - Top Outer */
    .item-2 { top: 70px; left: 180px; }     /* Reservations - Higher (following arrow) */
    .item-3 { top: 130px; left: 20px; }     /* Calendar - Higher */
    .item-4 { top: 200px; left: 130px; }    /* Maintenance - Higher */

    .mgmt-radial-close {
        position: absolute;
        bottom: 40px;
        right: 40px;
        width: 65px;
        height: 65px;
        background: #f8fafc;
        color: #64748b;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 3;
        box-shadow: 0 8px 15px rgba(0,0,0,0.05);
        transition: all 0.2s;
        border: 1px solid rgba(0,0,0,0.02);
    }

    .mgmt-radial-close:hover {
        background: #ef4444;
        color: white;
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);
    }

    /* Color Palette directly on icons */
    .color-purple { color: #7c3aed; }
    .color-blue { color: #2563eb; }
    .color-indigo { color: #4f46e5; }
</style>

<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav">
    <a href="<?= get_nav_link('dashboard', $is_dashboard, $isSuperAdmin) ?>" 
       class="bottom-nav-item <?= ($current_page == 'dashboard.php' && (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard')) ? 'active' : '' ?>"
       data-tab="dashboard">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Dashboard</span>
    </a>
    <a href="<?= rtrim(getBaseUrl(), '/') ?>/Modules/Visitor-logs.php" 
       class="bottom-nav-item <?= ($current_page == 'Visitor-logs.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-id-card-clip"></i>
        <span>Visitors</span>
    </a>
    <a href="#" onclick="checkVaultPin(event, '<?= rtrim(getBaseUrl(), '/') ?>/Modules/document management(archiving).php')"
       class="bottom-nav-item <?= ($current_page == 'document management(archiving).php') ? 'active' : '' ?>">
        <i class="fa-solid fa-vault"></i>
        <span>Vault</span>
    </a>
    <a href="<?= rtrim(getBaseUrl(), '/') ?>/Modules/legalmanagement.php" 
       class="bottom-nav-item <?= ($current_page == 'legalmanagement.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-scale-balanced"></i>
        <span>Legal</span>
    </a>
    <a href="javascript:void(0)" onclick="openManagementModal()" class="bottom-nav-item">
        <i class="fa-solid fa-list-check"></i>
        <span>Management</span>
    </a>
</div>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" onclick="closeSidebar()"></div>

<nav class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $isSuperAdmin ? rtrim(getBaseUrl(), '/') . '/Super-admin/Dashboard.php' : rtrim(getBaseUrl(), '/') . '/Modules/dashboard.php' ?>" class="logo-link"
            title="Go to Dashboard">
            <div class="logo-area">
                <div class="logo" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <img id="sidebarLogo" src="<?= rtrim(getBaseUrl(), '/') ?>/assets/image/logo.png" alt="Atiéra Logo"
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
                <a href="<?= $isSuperAdmin ? rtrim(getBaseUrl(), '/') . '/Super-admin/Settings.php' : rtrim(getBaseUrl(), '/') . '/include/Settings.php' ?>"
                    class="<?= (strpos($current_page, 'Settings.php') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-user"></i> Account
                </a>
            </li>
        </ul>
    </div>

    <div class="nav-section">
        <div class="nav-title">Main Navigation</div>
        <ul class="nav-links">
            <li><a href="<?= get_nav_link('dashboard', $is_dashboard, $isSuperAdmin) ?>"
                    class="<?= ($current_page == 'dashboard.php' && (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard')) ? 'active' : '' ?>"
                    data-tab="dashboard">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a></li>
            <!-- Dropdown for Management -->
            <?php
            $mgr_active = (isset($_GET['tab']) && (
                $_GET['tab'] == 'facilities' ||
                $_GET['tab'] == 'reservations' ||
                $_GET['tab'] == 'calendar' ||
                $_GET['tab'] == 'management' ||
                $_GET['tab'] == 'maintenance'
            ));
            ?>
            <li class="has-dropdown">
                <a href="#" class="dropdown-toggle <?= $mgr_active ? 'active' : '' ?>"
                    onclick="toggleSidebarFolder(event, this)">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fa-solid fa-list-check"></i> Management
                    </div>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"
                        style="transform: <?= $mgr_active ? 'rotate(180deg)' : '0deg' ?>;"></i>
                </a>
                <ul class="dropdown-menu" style="display: <?= $mgr_active ? 'block' : 'none' ?>;">
                    <li><a href="<?= get_nav_link('facilities', $is_dashboard, $isSuperAdmin) ?>"
                            class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'facilities') ? 'active' : '' ?>"
                            data-tab="facilities">
                            <i class="fa-solid fa-hotel"></i> Facilities
                        </a></li>
                    <li><a href="<?= get_nav_link('reservations', $is_dashboard, $isSuperAdmin) ?>"
                            class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'reservations') ? 'active' : '' ?>"
                            data-tab="reservations">
                            <i class="fa-solid fa-calendar-check"></i> Reservations
                        </a></li>
                    <li><a href="<?= get_nav_link('calendar', $is_dashboard, $isSuperAdmin) ?>"
                            class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'calendar') ? 'active' : '' ?>"
                            data-tab="calendar">
                            <i class="fa-solid fa-calendar-days"></i> Calendar
                        </a></li>
                    <li><a href="<?= get_nav_link('management', $is_dashboard, $isSuperAdmin) ?>"
                            class=" <?= (isset($_GET['tab']) && ($_GET['tab'] == 'management' || $_GET['tab'] == 'maintenance')) ? 'active' : '' ?>"
                            data-tab="management">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Maintenance
                        </a></li>
                </ul>
            </li>

            <li>
                <a href="#" onclick="checkVaultPin(event, '<?= rtrim(getBaseUrl(), '/') ?>/Modules/document management(archiving).php')"
                    class="<?= ($current_page == 'document management(archiving).php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <i class="fa-solid fa-vault"></i> Document Archiving
                </a>
            </li>
            <li><a href="<?= rtrim(getBaseUrl(), '/') ?>/Modules/Visitor-logs.php"
                    class="<?= ($current_page == 'Visitor-logs.php') ? 'active' : '' ?>" style="white-space: nowrap;">
                    <i class="fa-solid fa-id-card-clip"></i> Visitors Management
                </a></li>
            <li><a href="<?= rtrim(getBaseUrl(), '/') ?>/Modules/legalmanagement.php"
                    class="<?= ($current_page == 'legalmanagement.php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <i class="fa-solid fa-scale-balanced"></i> Legal Management
                </a></li>

        </ul>
    </div>

    <div class="nav-section">
        <div class="nav-title">External Links</div>
        <ul class="nav-links">
            <li><a href="<?= get_nav_link('reports', $is_dashboard, $isSuperAdmin) ?>"
                    class=" <?= (isset($_GET['tab']) && $_GET['tab'] == 'reports') ? 'active' : '' ?>"
                    data-tab="reports">
                    <i class="fa-solid fa-chart-pie"></i> Reports
                </a></li>
        </ul>
    </div>

    <!-- Sidebar Bottom Logout Section -->
    <div class="nav-section"
        style="margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.05);">
        <ul class="nav-links">
            <li>
                <a href="#" onclick="openLogoutModal()" style="color: #fda4af;">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </li>
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
            div.innerHTML = `<iframe src="<?= getBaseUrl() ?>/animation/loading.html" style="width:100%; height:100%; border:none; background:transparent;" allowtransparency="true"></iframe>`;
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
                    // Check if we are on dashboard.php and the link has a data-tab
                    const isDashboard = window.location.pathname.includes('dashboard.php');
                    const tabName = a.getAttribute('data-tab');
                    
                    if (isDashboard && tabName && typeof window.switchTab === 'function') {
                        e.preventDefault();
                        window.switchTab(tabName);
                        // Update URL without reload
                        const newUrl = window.location.pathname + '?tab=' + tabName;
                        window.history.pushState({tab: tabName}, '', newUrl);
                        return;
                    }

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
                });
            }
        });
    });

    // 4. Handle Tab Switching (Called by onclick)
    window.handleSidebarNav = function (tab) {
        // Allow animation for specific tabs if needed, or just default behavior
        if (typeof switchTab === 'function') switchTab(tab);
    };

    // 5. PIN PROTECTION FOR VAULT (Modern Box Design)
    window.checkVaultPin = function (event, url) {
        if (event) event.preventDefault();

        // Store target URL
        window.pendingVaultUrl = url || '<?= getBaseUrl() ?>/Modules/document management(archiving).php';

        // Inject PIN Modal if missing
        if (!document.getElementById('vaultPinModal')) {
            const modalHtml = `
                <div id="vaultPinModal" style="display: none; position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); align-items: center; justify-content: center; transition: all 0.3s ease;">
                    <div style="background: #ffffff; padding: 40px; border-radius: 24px; width: 380px; text-align: center; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.6); position: relative; border: 1px solid rgba(255,255,255,0.1);">
                        
                        <div style="width: 70px; height: 70px; background: rgba(251, 146, 60, 0.1); color: #f97316; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; font-size: 28px; box-shadow: 0 10px 20px rgba(249, 115, 22, 0.1);">
                            <i class="fa-solid fa-vault"></i>
                        </div>
                        
                        <h3 style="margin: 0 0 10px; color: #0f172a; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px;">Vault Protection</h3>
                        <p style="margin: 0 0 30px; color: #64748b; font-size: 0.95rem; font-weight: 500; line-height: 1.5;">Identify yourself to access the secure document archive.</p>
                        
                        <div id="pin-container" style="display: flex; gap: 12px; justify-content: center; margin-bottom: 30px;">
                            <input type="password" class="vault-digit-input" maxlength="1" style="width: 60px; height: 75px; border: 2px solid #e2e8f0; border-radius: 16px; font-size: 28px; text-align: center; outline: none; transition: all 0.3s; background: #f8fafc; font-weight: 800; color: #1e293b;">
                            <input type="password" class="vault-digit-input" maxlength="1" style="width: 60px; height: 75px; border: 2px solid #e2e8f0; border-radius: 16px; font-size: 28px; text-align: center; outline: none; transition: all 0.3s; background: #f8fafc; font-weight: 800; color: #1e293b;">
                            <input type="password" class="vault-digit-input" maxlength="1" style="width: 60px; height: 75px; border: 2px solid #e2e8f0; border-radius: 16px; font-size: 28px; text-align: center; outline: none; transition: all 0.3s; background: #f8fafc; font-weight: 800; color: #1e293b;">
                            <input type="password" class="vault-digit-input" maxlength="1" style="width: 60px; height: 75px; border: 2px solid #e2e8f0; border-radius: 16px; font-size: 28px; text-align: center; outline: none; transition: all 0.3s; background: #f8fafc; font-weight: 800; color: #1e293b;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <button onclick="document.getElementById('vaultPinModal').style.display='none'" style="padding: 15px; border-radius: 14px; border: 2px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; font-weight: 700; font-size: 0.9rem; transition: all 0.2s;">Cancel</button>
                            <button onclick="verifyVaultPin()" style="padding: 15px; border-radius: 14px; border: none; background: #3182ce; color: white; cursor: pointer; font-weight: 700; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3); transition: all 0.2s;">Unlock Vault</button>
                        </div>
                    </div>
                </div>`;
            const div = document.createElement('div');
            div.innerHTML = modalHtml;
            document.body.appendChild(div.firstElementChild);

            // Add Input Behavior Logic
            const inputs = document.querySelectorAll('.vault-digit-input');
            inputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    if (e.target.value.length === 1 && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    if (Array.from(inputs).every(inp => inp.value.length === 1)) {
                        verifyVaultPin();
                    }
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                    if (e.key === 'Enter') {
                        verifyVaultPin();
                    }
                });

                input.addEventListener('focus', (e) => {
                    e.target.style.borderColor = '#3182ce';
                    e.target.style.background = '#fff';
                    e.target.style.boxShadow = '0 0 0 4px rgba(49, 130, 206, 0.1)';
                });

                input.addEventListener('blur', (e) => {
                    e.target.style.borderColor = '#e2e8f0';
                    e.target.style.background = '#f8fafc';
                    e.target.style.boxShadow = 'none';
                });
            });
        }

        const modal = document.getElementById('vaultPinModal');
        modal.style.display = 'flex';
        const inputs = document.querySelectorAll('.vault-digit-input');
        inputs.forEach(inp => inp.value = '');
        if (inputs[0]) inputs[0].focus();
    };

    window.verifyVaultPin = function () {
        const inputs = document.querySelectorAll('.vault-digit-input');
        let pin = '';
        inputs.forEach(inp => pin += inp.value);

        if (pin === '1234') { // Default Admin PIN
            // Hide Modal
            document.getElementById('vaultPinModal').style.display = 'none';
            // Start Animation THEN redirect
            if (typeof window.runLoadingAnimation === 'function') {
                window.runLoadingAnimation(() => {
                    window.location.href = window.pendingVaultUrl;
                }, true);
            } else {
                window.location.href = window.pendingVaultUrl;
            }
        } else {
            // Shake effect or just alert
            alert("Security Breach: Incorrect PIN! Access Denied.");
            inputs.forEach(inp => inp.value = '');
            if (inputs[0]) inputs[0].focus();
        }
    };

    // 6. Handle Sidebar Dropdown Toggle (Renamed to avoid conflict)
    window.toggleSidebarFolder = function (event, element) {
        if (event) event.preventDefault();
        const parentLi = element.closest('li');
        const dropdownMenu = parentLi.querySelector('.dropdown-menu');
        const arrow = parentLi.querySelector('.dropdown-arrow');

        if (!dropdownMenu) return;

        const isVisible = dropdownMenu.style.display === 'block';

        // Close other open dropdowns
        document.querySelectorAll('.has-dropdown .dropdown-menu').forEach(menu => {
            if (menu !== dropdownMenu) {
                menu.style.display = 'none';
                const otherArrow = menu.previousElementSibling.querySelector('.dropdown-arrow');
                if (otherArrow) otherArrow.style.transform = 'rotate(0deg)';
            }
        });

        if (isVisible) {
            dropdownMenu.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        } else {
            dropdownMenu.style.display = 'block';
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        }
    };

    // 7. Close Sidebar Dropdowns on Outside Click
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.has-dropdown')) {
            document.querySelectorAll('.has-dropdown .dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
                const arrow = menu.previousElementSibling.querySelector('.dropdown-arrow');
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            });
        }
    });

    // 8. Sidebar Toggle Logic for Menu Button
    window.toggleSidebar = function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const overlay = document.querySelector('.mobile-menu-overlay');
        
        if (window.innerWidth <= 768) {
            // Reset any desktop collapsed styles just in case
            sidebar.style.width = '';
            document.querySelectorAll('.nav-links a').forEach(a => {
                a.style.color = '';
                const icon = a.querySelector('i');
                if (icon) icon.style.color = '';
                const div = a.querySelector('div');
                if (div) div.style.color = '';
                const arrow = a.querySelector('.dropdown-arrow');
                if (arrow) arrow.style.display = '';
            });
            document.querySelectorAll('.nav-title, .logo-area div').forEach(el => el.style.display = '');

            const sidebarLogo = document.getElementById('sidebarLogo');
            if (sidebarLogo) {
                sidebarLogo.src = '<?= rtrim(getBaseUrl(), '/') ?>/assets/image/logo.png';
                sidebarLogo.style.height = '60px'; // Reset for mobile
            }

            // Mobile behavior: Use active class for transform
            const isActive = sidebar.classList.toggle('active');
            if (overlay) {
                overlay.style.display = isActive ? 'block' : 'none';
            }
        } else {
            // Desktop behavior (Collapsed/Expanded)
            if (sidebar.style.width === '80px') {
                sidebar.style.width = '280px';
                if(mainContent) mainContent.style.marginLeft = '280px';
                
                // Show logo.png
                const sidebarLogo = document.getElementById('sidebarLogo');
                if (sidebarLogo) {
                    sidebarLogo.src = '<?= rtrim(getBaseUrl(), '/') ?>/assets/image/logo.png';
                    sidebarLogo.style.height = '60px'; // Restore size
                }

                // Show text
                document.querySelectorAll('.nav-links a').forEach(a => {
                    Array.from(a.childNodes).forEach(n => {
                        if (n.nodeType === 3 && n.textContent.trim().length > 0) {
                            // Text node logic
                        }
                    });
                    // Remove hidden span wrappers if we created them
                    const textSpan = a.querySelector('.nav-text-span');
                    if (textSpan) {
                        textSpan.style.display = 'inline-block';
                    } else {
                        // Quick CSS workaround to show text
                        a.style.color = '#cbd5e0';
                    }
                    
                    const arrow = a.querySelector('.dropdown-arrow');
                    if (arrow) arrow.style.display = 'inline-block';
                    
                    const divWrapper = a.querySelector('div');
                    if (divWrapper && divWrapper.style !== undefined) divWrapper.style.color = '';
                });
                document.querySelectorAll('.nav-title').forEach(el => el.style.display = 'block');
                document.querySelectorAll('.logo-area div').forEach(el => el.style.display = 'block');
                
                // Show user profile text
                const userText = document.querySelector('.user-details-text');
                if (userText) userText.style.width = 'auto';
                const profileSection = document.querySelector('.user-profile-section');
                if (profileSection) profileSection.style.padding = '15px 20px';
            } else {
                sidebar.style.width = '80px';
                if(mainContent) mainContent.style.marginLeft = '80px';
                
                // Show logo2.png
                const sidebarLogo = document.getElementById('sidebarLogo');
                if (sidebarLogo) {
                    sidebarLogo.src = '<?= rtrim(getBaseUrl(), '/') ?>/assets/image/logo2.png';
                    sidebarLogo.style.height = '40px'; // Smaller for collapsed
                }

                // Hide text
                document.querySelectorAll('.nav-title').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.logo-area div').forEach(el => {
                    if (el.className !== 'logo-area' && el.className !== 'logo' && !el.querySelector('img')) {
                        el.style.display = 'none';
                    }
                });
                
                // Magic CSS trick to hide text content without spans
                document.querySelectorAll('.nav-links a').forEach(a => {
                    a.style.color = 'transparent';
                    
                    // Keep icon visible
                    const icon = a.querySelector('i');
                    if (icon) icon.style.color = '#cbd5e0';
                    
                    // Hide arrow
                    const arrow = a.querySelector('.dropdown-arrow');
                    if (arrow) arrow.style.display = 'none';
                    
                    const divWrapper = a.querySelector('div'); // Management tab has a div wrapper
                    if (divWrapper) divWrapper.style.color = 'transparent';
                });

                // Hide user profile text
                const userText = document.querySelector('.user-details-text');
                if (userText) userText.style.width = '0';
                const profileSection = document.querySelector('.user-profile-section');
                if (profileSection) profileSection.style.padding = '15px 10px';
            }
        }
        
        // Add smooth transitions if not present
        if (!sidebar.style.transition) sidebar.style.transition = 'all 0.3s ease';
        if (mainContent && !mainContent.style.transition) mainContent.style.transition = 'margin-left 0.3s ease';
    };

    window.closeSidebar = function() {
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth <= 768 && sidebar) {
            sidebar.classList.remove('active');
            const overlay = document.querySelector('.mobile-menu-overlay');
            if (overlay) overlay.style.display = 'none';
        }
    };

    // 9. Handle window resize to reset sidebar if going mobile
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                 sidebar.style.width = '';
                 sidebar.style.transition = '';
                 const sidebarLogo = document.getElementById('sidebarLogo');
                 if (sidebarLogo) {
                     sidebarLogo.src = '<?= rtrim(getBaseUrl(), '/') ?>/assets/image/logo.png';
                     sidebarLogo.style.height = '60px';
                 }
                 // Reset inline color/display for all a tags, titles etc
                 document.querySelectorAll('.nav-links a').forEach(a => {
                    a.style.color = '';
                    const icon = a.querySelector('i');
                    if (icon) icon.style.color = '';
                    const div = a.querySelector('div');
                    if (div) div.style.color = '';
                    const arrow = a.querySelector('.dropdown-arrow');
                    if (arrow) arrow.style.display = '';
                 });
                 document.querySelectorAll('.nav-title, .logo-area div').forEach(el => el.style.display = '');
            }
        }
    });

    // 10. Management Radial Menu Logic
    window.openManagementModal = function() {
        const overlay = document.getElementById('mgmtOverlay');
        const wrapper = document.getElementById('mgmtRadialWrapper');
        if (overlay && wrapper) {
            overlay.style.display = 'block';
            setTimeout(() => {
                overlay.classList.add('show');
            }, 10);
        }
    };

    window.closeManagementModal = function() {
        const overlay = document.getElementById('mgmtOverlay');
        if (overlay) {
            overlay.classList.remove('show');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 400);
        }
    };
</script>

<!-- Management Quick Access Radial Menu -->
<div id="mgmtOverlay" class="mgmt-overlay" onclick="closeManagementModal()"></div>
<div id="mgmtRadialWrapper" class="mgmt-radial-wrapper">
    <div class="mgmt-radial-bg"></div>
    
    <a href="<?= get_nav_link('facilities', $is_dashboard, $isSuperAdmin) ?>" class="mgmt-radial-item item-1" title="Facilities">
        <i class="fa-solid fa-hotel color-purple"></i>
    </a>
    
    <a href="<?= get_nav_link('reservations', $is_dashboard, $isSuperAdmin) ?>" class="mgmt-radial-item item-2" title="Reservations">
        <i class="fa-solid fa-calendar-check color-blue"></i>
    </a>
    
    <a href="<?= get_nav_link('calendar', $is_dashboard, $isSuperAdmin) ?>" class="mgmt-radial-item item-3" title="Calendar">
        <i class="fa-solid fa-calendar-days color-purple"></i>
    </a>
    
    <a href="<?= get_nav_link('management', $is_dashboard, $isSuperAdmin) ?>" class="mgmt-radial-item item-4" title="Maintenance">
        <i class="fa-solid fa-screwdriver-wrench color-indigo"></i>
    </a>
    
    <div class="mgmt-radial-close" onclick="closeManagementModal()">
        <i class="fa-solid fa-xmark"></i>
    </div>
</div>