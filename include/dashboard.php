<?php
// Dashboard Tab - Enhanced UI
?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="dashboard" class="tab-content" style="padding: 0;">
    <?php
    // Fetch metrics
    try {
        $db = get_pdo(); // Shared PDO
        // 2. Available Rooms (Facilities)
        $available_rooms = $db->query("SELECT COUNT(*) FROM facilities WHERE status = 'active'")->fetchColumn() ?? 0;

        // 3. Today's Visitors (Local DB only for count)
        $today_visitors = $db->query("SELECT COUNT(*) FROM direct_checkins WHERE DATE(checkin_date) = CURDATE()")->fetchColumn() ?? 0;

        // 4. Archived Documents
        $total_documents = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn() ?? 0;

        // 5. Employee Count from HR4 API
        require_once __DIR__ . '/../integ/hr4_api.php';
        $employees_data = fetchAllEmployees();
        $employee_count = (is_array($employees_data) && isset($employees_data)) ? count($employees_data) : 0;

    } catch (PDOException $e) {
        $new_bookings = 0;
        $available_rooms = 0;
        $check_in = 0;
        $check_out = 0;
        $revenue = 0;
        $recent_activities = [];
        $employee_count = 0;
    }
    ?>

    <!-- Top Metrics Grid -->
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">



        <!-- Available Room Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #fff7ed; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #f97316;">
                    <i class="fa-solid fa-hotel" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $available_rooms ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Available Facilities</p>
            </div>
            <div style="height: 40px; margin-top: 10px; display: flex; align-items: flex-end; gap: 3px; opacity: 0.5;">
                <div style="width: 15%; background: #f97316; height: 30%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #f97316; height: 50%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #f97316; height: 80%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #f97316; height: 40%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #f97316; height: 60%; border-radius: 2px;"></div>
            </div>
        </div>

        <!-- Visitors Today Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #22c55e;">
                    <i class="fa-solid fa-id-card-clip" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $today_visitors ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Check In (Today)</p>
            </div>
            <div style="height: 40px; margin-top: 10px; display: flex; align-items: flex-end; gap: 3px; opacity: 0.5;">
                <div style="width: 15%; background: #22c55e; height: 60%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #22c55e; height: 85%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #22c55e; height: 50%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #22c55e; height: 95%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #22c55e; height: 75%; border-radius: 2px;"></div>
            </div>
        </div>

        <!-- Documents Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #fef2f2; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                    <i class="fa-solid fa-vault" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $total_documents ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Total Archived Documents</p>
            </div>
            <div style="height: 40px; margin-top: 10px; display: flex; align-items: flex-end; gap: 3px; opacity: 0.5;">
                <div style="width: 15%; background: #ef4444; height: 40%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #ef4444; height: 25%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #ef4444; height: 60%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #ef4444; height: 35%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #ef4444; height: 50%; border-radius: 2px;"></div>
            </div>
        </div>

        <!-- Employees Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #f5f3ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #7c3aed;">
                    <i class="fa-solid fa-user-group" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $employee_count ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Active Employees</p>
            </div>
            <div style="height: 40px; margin-top: 10px; display: flex; align-items: flex-end; gap: 3px; opacity: 0.5;">
                <div style="width: 15%; background: #7c3aed; height: 50%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #7c3aed; height: 80%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #7c3aed; height: 40%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #7c3aed; height: 90%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #7c3aed; height: 60%; border-radius: 2px;"></div>
            </div>
        </div>


    </div>

    <!-- Bottom Split Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">

        <!-- Recent Activities -->
        <div style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent activities</h3>
                <a href="#" onclick="openRecentActivitiesModal(event)"
                    style="font-size: 0.85rem; color: #64748b; text-decoration: none;">View all <i
                        class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php
                // Fetch recent employees from HR4 API as "Recent Activities"
                $recent_emps = is_array($employees_data) ? array_slice($employees_data, 0, 4) : [];

                // Define some available modules for linking
                $available_modules = [
                    'legalmanagement.php' => 'Legal Management',
                    'document management(archiving).php' => 'Document Archiving',
                    'Visitor-logs.php' => 'Visitor Logs',
                    'dashboard.php' => 'Dashboard'
                ];
                $module_keys = array_keys($available_modules);

                if (empty($recent_emps)):
                    ?>
                    <p style="text-align: center; color: #94a3b8; padding: 20px;">No recent activities.</p>
                <?php else: ?>
                    <?php
                    $m_idx = 0;
                    foreach ($recent_emps as $emp):
                        // Determine gender image
                        $gender = strtolower($emp['gender'] ?? 'male');
                        $icon = ($gender === 'female' || $gender === 'f') ? '../assets/image/Women.png' : '../assets/image/Men.png';

                        // Assign a module link
                        $target_module = $module_keys[$m_idx % count($module_keys)];
                        $module_name = $available_modules[$target_module];
                        $m_idx++;
                        ?>
                        <a href="<?= $target_module ?>" style="text-decoration: none; display: block; transition: all 0.3s;"
                            class="activity-item">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <!-- Icon Image -->
                                <div
                                    style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <img src="<?= $icon ?>" alt="avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;">
                                        <?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #64748b;">
                                        <?= htmlspecialchars($emp['role'] ?? $emp['position'] ?? 'Staff') ?>
                                        <span
                                            style="display: inline-block; width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%; vertical-align: middle; margin: 0 4px;"></span>
                                        <span style="color: #4338ca; font-weight: 600;"><?= $module_name ?></span>
                                    </div>
                                </div>
                                <div style="font-size: 0.8rem; color: #94a3b8;">
                                    Recently
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>



    </div>

</div>

<!-- CSS for activity hover -->
<style>
    .activity-item:hover {
        background: #f8fafc;
        transform: translateX(5px);
    }

    /* Modal Styles */
    #recentActivitiesModal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    #recentActivitiesModal.show {
        display: flex;
        opacity: 1;
    }

    .custom-modal-content {
        background-color: #fff;
        border-radius: 16px;
        width: 90%;
        max-width: 600px;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.95);
        transition: transform 0.3s ease;
    }

    #recentActivitiesModal.show .custom-modal-content {
        transform: scale(1);
    }

    .custom-modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .custom-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }

    .close-modal {
        background: transparent;
        border: none;
        font-size: 1.5rem;
        color: #94a3b8;
        cursor: pointer;
        transition: color 0.2s;
        line-height: 1;
    }

    .close-modal:hover {
        color: #ef4444;
    }

    .custom-modal-body {
        padding: 20px 25px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* Scrollbar for modal */
    .custom-modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .custom-modal-body::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .custom-modal-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .custom-modal-body::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<!-- Recent Activities Modal HTML -->
<div id="recentActivitiesModal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h3>Recent Activities</h3>
            <button class="close-modal" onclick="closeRecentActivitiesModal()">&times;</button>
        </div>
        <div class="custom-modal-body">
            <?php
            // Re-use logic to verify data availability
            if (empty($employees_data) || !is_array($employees_data)): ?>
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <i class="fa-solid fa-inbox"
                        style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                    <p>No recent activities found.</p>
                </div>
            <?php else:
                // Reset module index for modal loop
                $m_idx_modal = 0;
                foreach ($employees_data as $emp):
                    $gender = strtolower($emp['gender'] ?? 'male');
                    $icon = ($gender === 'female' || $gender === 'f') ? '../assets/image/Women.png' : '../assets/image/Men.png';

                    // Assign a module link
                    $target_module = $module_keys[$m_idx_modal % count($module_keys)];
                    $module_name = $available_modules[$target_module];
                    $m_idx_modal++;
                    ?>
                    <a href="<?= $target_module ?>" style="text-decoration: none; display: block; transition: all 0.2s;"
                        class="activity-item-modal">
                        <div
                            style="display: flex; align-items: center; gap: 15px; padding: 10px; border-radius: 12px; border: 1px solid transparent; transition: all 0.2s;">
                            <div
                                style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; background: #f8fafc; border: 1px solid #e2e8f0; flex-shrink: 0;">
                                <img src="<?= $icon ?>" alt="avatar" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1e293b; font-size: 1rem;">
                                    <?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                                </div>
                                <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">
                                    <?= htmlspecialchars($emp['role'] ?? $emp['position'] ?? 'Staff') ?>
                                    <span
                                        style="display: inline-block; width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%; vertical-align: middle; margin: 0 4px;"></span>
                                    <span style="color: #4338ca; font-weight: 500;"><?= $module_name ?></span>
                                </div>
                            </div>
                            <div style="font-size: 0.8rem; color: #94a3b8;">
                                Recently
                            </div>
                        </div>
                    </a>
                <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
    function openRecentActivitiesModal(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('recentActivitiesModal');
        modal.style.display = 'flex';
        // Small delay to allow display:flex to apply before adding opacity class for transition
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }

    function closeRecentActivitiesModal() {
        const modal = document.getElementById('recentActivitiesModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }, 300); // Match transition duration
    }

    // Close on outside click
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('recentActivitiesModal');
        if (event.target === modal) {
            closeRecentActivitiesModal();
        }
    });

    // Add hover effect style for modal items dynamically
    const styleSheet = document.createElement("style");
    styleSheet.innerText = `
        .activity-item-modal:hover > div {
            background: #f8fafc;
            border-color: #e2e8f0;
        }
    `;
    document.head.appendChild(styleSheet);
</script>