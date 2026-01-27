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
        <a href="<?= $isSuperAdmin ? '../Super-admin/Dashboard.php' : '../Modules/dashboard.php?tab=dashboard' ?>"
            class="logo-link" title="Go to Dashboard">
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
                    <i class="fa-solid fa-circle-user"></i> Account
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
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a></li>
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
            <li><a href="#" onclick="checkVaultPin(event, '../Modules/document management(archiving).php')"
                    class="<?= ($current_page == 'document management(archiving).php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <i class="fa-solid fa-vault"></i> Document Archiving
                </a></li>
            <li><a href="../Modules/Visitor-logs.php"
                    class="<?= ($current_page == 'Visitor-logs.php') ? 'active' : '' ?>" style="white-space: nowrap;">
                    <i class="fa-solid fa-id-card-clip"></i> Visitors Management
                </a></li>
            <li><a href="../Modules/legalmanagement.php"
                    class="<?= ($current_page == 'legalmanagement.php') ? 'active' : '' ?>"
                    style="white-space: nowrap;">
                    <i class="fa-solid fa-scale-balanced"></i> Legal Management
                </a></li>
            <!-- Dropdown for Calendar & Maintenance -->
            <?php
            $mgr_active = (isset($_GET['tab']) && ($_GET['tab'] == 'calendar' || $_GET['tab'] == 'management' || $_GET['tab'] == 'maintenance'));
            ?>
            <li class="has-dropdown">
                <a href="#" class="dropdown-toggle <?= $mgr_active ? 'active' : '' ?>"
                    onclick="toggleDropdown(event, this)">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fa-solid fa-calendar-check"></i> Facilities Reservation & Maintenance
                    </div>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"
                        style="transform: <?= $mgr_active ? 'rotate(180deg)' : '0deg' ?>;"></i>
                </a>
                <ul class="dropdown-menu" style="display: <?= $mgr_active ? 'block' : 'none' ?>;">
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

    // 5. PIN PROTECTION FOR VAULT (Modern Box Design)
    window.checkVaultPin = function (event, url) {
        if (event) event.preventDefault();

        // Store target URL
        window.pendingVaultUrl = url || '../Modules/document management(archiving).php';

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

    // 6. Handle Sidebar Dropdown Toggle
    window.toggleDropdown = function (event, element) {
        event.preventDefault();
        const parentLi = element.closest('li');
        const dropdownMenu = parentLi.querySelector('.dropdown-menu');
        const arrow = parentLi.querySelector('.dropdown-arrow');

        // Close other open dropdowns (optional, but good for UX)
        document.querySelectorAll('.has-dropdown .dropdown-menu').forEach(menu => {
            if (menu !== dropdownMenu) {
                menu.style.display = 'none';
                menu.previousElementSibling.querySelector('.dropdown-arrow').style.transform = 'rotate(0deg)';
            }
        });

        if (dropdownMenu.style.display === 'block') {
            dropdownMenu.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        } else {
            dropdownMenu.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        }
    };
</script>