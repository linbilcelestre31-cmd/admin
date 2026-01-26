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

    // 5. PIN PROTECTION FOR VAULT
    window.checkVaultPin = function (event, url) {
        if (event) event.preventDefault();

        // Store target URL
        window.pendingVaultUrl = url || '../Modules/document management(archiving).php';

        // Inject PIN Modal if missing
        if (!document.getElementById('vaultPinModal')) {
            const modalHtml = `
                <div id="vaultPinModal" style="display: none; position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
                    <div style="background: white; padding: 30px; border-radius: 20px; width: 320px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); font-family: 'Inter', sans-serif;">
                        <div style="width: 60px; height: 60px; background: #fff7ed; color: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 24px;">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <h3 style="margin: 0 0 10px; color: #1e293b;">Vault Protection</h3>
                        <p style="margin: 0 0 20px; color: #64748b; font-size: 0.9rem;">Please enter security PIN to access document archive.</p>
                        <input type="password" id="sidebar-vault-pin-input" maxlength="4" placeholder="••••" style="width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 24px; text-align: center; letter-spacing: 10px; margin-bottom: 20px; outline: none; transition: border-color 0.2s;" onkeyup="if(event.key==='Enter') verifyVaultPin()">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button onclick="document.getElementById('vaultPinModal').style.display='none'" style="padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; cursor: pointer; font-weight: 600;">Cancel</button>
                            <button onclick="verifyVaultPin()" style="padding: 12px; border-radius: 10px; border: none; background: #3182ce; color: white; cursor: pointer; font-weight: 600;">Unlock</button>
                        </div>
                    </div>
                </div>`;
            const div = document.createElement('div');
            div.innerHTML = modalHtml;
            document.body.appendChild(div.firstElementChild);
        }

        const modal = document.getElementById('vaultPinModal');
        modal.style.display = 'flex';
        const input = document.getElementById('sidebar-vault-pin-input');
        if (input) {
            input.value = '';
            input.focus();
        }
    };

    window.verifyVaultPin = function () {
        const pinInput = document.getElementById('sidebar-vault-pin-input') || document.getElementById('vault-pin-input');
        const pin = pinInput ? pinInput.value : '';

        if (pin === '1234') { // Default PIN
            if (typeof window.runLoadingAnimation === 'function') {
                window.runLoadingAnimation(() => {
                    window.location.href = window.pendingVaultUrl;
                }, true);
            } else {
                window.location.href = window.pendingVaultUrl;
            }
        } else {
            alert("Incorrect PIN! Access Denied.");
            if (pinInput) {
                pinInput.value = '';
                pinInput.focus();
            }
        }
    };
</script>