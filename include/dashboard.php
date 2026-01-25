<?php
// Dashboard Tab - Enhanced UI
?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="dashboard" class="tab-content <?= (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard') ? 'active' : '' ?>"
    style="padding: 0;">
    <?php
    // Fetch metrics
    try {
        $db = get_pdo(); // Shared PDO
    
        // 1. Available Rooms (Facilities)
        $available_rooms = $db->query("SELECT COUNT(*) FROM facilities WHERE status = 'active'")->fetchColumn() ?? 0;

        // 2. Today's Visitors (Local DB only for count)
        $today_visitors = $db->query("SELECT COUNT(*) FROM direct_checkins WHERE DATE(checkin_date) = CURDATE()")->fetchColumn() ?? 0;

        // 3. Archived Documents
        $total_documents = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn() ?? 0;

        // 4. Employee Count from HR4 API
        require_once __DIR__ . '/../integ/hr4_api.php';
        $employees_data = fetchAllEmployees();
        $employee_count = (is_array($employees_data) && isset($employees_data)) ? count($employees_data) : 0;

        // 5. Maintenance Logic (Pending Logs)
        $pending_maintenance = $db->query("SELECT * FROM maintenance_logs WHERE status != 'completed' ORDER BY maintenance_date ASC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

        // 6. Compliance Status (Legal Contracts)
        $high_risk_contracts = $db->query("SELECT COUNT(*) FROM contracts WHERE risk_score >= 70")->fetchColumn() ?? 0;
        $total_contracts = $db->query("SELECT COUNT(*) FROM contracts")->fetchColumn() ?? 0;

        // 7. Archiving Break-down (Simulated or from categories if they exist)
        $recent_archived = $db->query("SELECT * FROM documents ORDER BY uploaded_at DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

        // 8. Recent Reservations for Activity Card
        $recent_reservations_dash = $db->query("SELECT r.*, f.name as facility_name FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id ORDER BY r.id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

        // Define modules for linking in the activities modal
        $available_modules = [
            'legalmanagement.php' => 'Legal Management',
            'document management(archiving).php' => 'Document Archiving',
            'Visitor-logs.php' => 'Visitor Logs',
            'dashboard.php' => 'Dashboard'
        ];
        $module_keys = array_keys($available_modules);

        // Add dummy data for visual testing if empty
        if (empty($pending_maintenance)) {
            $pending_maintenance = [
                ['id' => 9991, 'item_name' => 'HVAC System - North Wing', 'description' => 'Periodic filter replacement and coolant level check', 'maintenance_date' => date('Y-m-d'), 'status' => 'pending'],
                ['id' => 9992, 'item_name' => 'Elevator Unit 1', 'description' => 'Safety sensor calibration and lubrication', 'maintenance_date' => date('Y-m-d', strtotime('+3 days')), 'status' => 'in-progress']
            ];
        }

        if (empty($recent_archived)) {
            $recent_archived = [
                ['id' => 8881, 'name' => 'Annual_Financial_Report_2025.pdf', 'uploaded_at' => date('Y-m-d H:i:s')],
                ['id' => 8882, 'name' => 'Facility_Floor_Plans_Updated.pdf', 'uploaded_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))]
            ];
        }

    } catch (PDOException $e) {
        $available_rooms = 0;
        $today_visitors = 0;
        $total_documents = 0;
        $employee_count = 0;
        $pending_maintenance = [];
        $high_risk_contracts = 0;
        $total_contracts = 0;
        $recent_archived = [];
        $available_modules = [];
        $module_keys = [];
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
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Visitors (Today)</p>
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
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Total Documents</p>
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

    <!-- Bottom Split Section (4 Cards Grid) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">

        <!-- Compliance Reports (Legal) -->
        <div
            style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Compliance Reports</h3>
                <a href="legalmanagement.php" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">View
                    Legal <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>

            <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border-left: 4px solid #ef4444;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 600; color: #1e293b;">High Risk Contracts</span>
                        <span
                            style="background: #fee2e2; color: #ef4444; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 700;"><?= $high_risk_contracts ?>
                            Flagged</span>
                    </div>
                    <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                        <div
                            style="width: <?= ($total_contracts > 0) ? ($high_risk_contracts / $total_contracts * 100) : 0 ?>%; height: 100%; background: #ef4444;">
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; border-left: 4px solid #3b82f6;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 600; color: #1e293b;">Total Active Contracts</span>
                        <span style="color: #3b82f6; font-weight: 700;"><?= $total_contracts ?></span>
                    </div>
                </div>

                <div style="margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="text-align: center; background: #f0fdf4; padding: 10px; border-radius: 10px;">
                        <div style="color: #166534; font-weight: 700; font-size: 1.2rem;">98%</div>
                        <div style="color: #15803d; font-size: 0.7rem; font-weight: 500;">Compliance Rate</div>
                    </div>
                    <div style="text-align: center; background: #eff6ff; padding: 10px; border-radius: 10px;">
                        <div style="color: #1a56db; font-weight: 700; font-size: 1.2rem;">0</div>
                        <div style="color: #1d4ed8; font-size: 0.7rem; font-weight: 500;">Pending Cases</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity (Employee Activity from HR4) -->
        <div
            style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent Activity</h3>
                <a href="#" onclick="openRecentActivitiesModal(event)"
                    style="font-size: 0.85rem; color: #64748b; text-decoration: none;">View All <i
                        class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php if (empty($employees_data) || !is_array($employees_data)): ?>
                    <div
                        style="text-align: center; padding: 30px; background: #f8fafc; border-radius: 12px; border: 1px dashed #e2e8f0;">
                        <p style="color: #94a3b8; font-size: 0.9rem; margin: 0;">No recent employee activity.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $recent_emps = array_slice($employees_data, 0, 4);
                    foreach ($recent_emps as $emp):
                        $gender = strtolower($emp['gender'] ?? 'male');
                        $icon = ($gender === 'female' || $gender === 'f') ? '../assets/image/Women.png' : '../assets/image/Men.png';
                        ?>
                        <div
                            style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #fdfdfd; border-radius: 10px; border: 1px solid #f8fafc;">
                            <div
                                style="width: 35px; height: 35px; border-radius: 50%; overflow: hidden; background: #f8fafc; border: 1px solid #e2e8f0; flex-shrink: 0;">
                                <img src="<?= $icon ?>" alt="avatar" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div
                                    style="font-weight: 600; color: #1e293b; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #4338ca; font-weight: 500;">
                                    <?= htmlspecialchars($emp['role'] ?? $emp['position'] ?? 'Staff') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div
            style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Archiving Status</h3>
                <a href="document management(archiving).php"
                    style="font-size: 0.85rem; color: #64748b; text-decoration: none;">Go to Vault <i
                        class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>

            <!-- Stats Boxes (Mirroring Archiving Module) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div
                    style="background: #eff6ff; padding: 15px; border-radius: 14px; border: 1px solid #dbeafe; text-align: center;">
                    <div
                        style="font-size: 0.75rem; font-weight: 700; color: #1e40af; text-transform: uppercase; margin-bottom: 4px;">
                        Total Files</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #1e3a8a;"><?= $total_documents ?></div>
                </div>
                <div
                    style="background: #fdf2f2; padding: 15px; border-radius: 14px; border: 1px solid #fee2e2; text-align: center;">
                    <div
                        style="font-size: 0.75rem; font-weight: 700; color: #991b1b; text-transform: uppercase; margin-bottom: 4px;">
                        Storage</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: #7f1d1d;">0 MB</div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <h4 style="font-size: 0.8rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Recent Files
                </h4>
                <?php if (empty($recent_archived)): ?>
                    <div style="text-align: center; padding: 20px;">
                        <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">No files found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recent_archived, 0, 3) as $doc): ?>
                        <div
                            style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 10px;">
                            <i class="fa-solid fa-file-pdf" style="color: #ef4444; font-size: 0.9rem;"></i>
                            <div
                                style="flex: 1; min-width: 0; font-size: 0.8rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;">
                                <?= htmlspecialchars($doc['name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Maintenance Tracking -->
        <div
            style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Maintenance Schedule</h3>
                <a href="dashboard.php?tab=management"
                    style="font-size: 0.85rem; color: #64748b; text-decoration: none;">Schedules <i
                        class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; flex: 1;">
                <?php if (empty($pending_maintenance)): ?>
                    <div
                        style="text-align: center; padding: 30px; background: #f0fdf4; border-radius: 12px; border: 1px solid #dcfce7; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <i class="fa-solid fa-circle-check"
                            style="font-size: 2rem; color: #22c55e; margin-bottom: 10px;"></i>
                        <span style="color: #15803d; font-size: 0.9rem; font-weight: 700;">All Clear</span>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($pending_maintenance, 0, 3) as $job): ?>
                        <div
                            style="padding: 12px; border-radius: 12px; background: #fffcf0; border: 1px solid #fef08a; display: flex; align-items: flex-start; gap: 10px;">
                            <div
                                style="width: 30px; height: 30px; background: #fef9c3; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #ca8a04; flex-shrink: 0;">
                                <i class="fa-solid fa-wrench" style="font-size: 0.8rem;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div
                                    style="font-weight: 700; color: #854d0e; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($job['item_name']) ?></div>
                                <div style="font-size: 0.7rem; color: #a16207; margin-top: 2px;">
                                    <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i>
                                    <?= date('M d', strtotime($job['maintenance_date'])) ?>
                                </div>
                            </div>
                            <span
                                style="background: #ca8a04; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?= $job['status'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Recent Activities Modal HTML (Kept for compatibility with JS) -->
<div id="recentActivitiesModal"
    style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease;">
    <div
        style="background-color: #fff; border-radius: 16px; width: 90%; max-width: 600px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div
            style="padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b;">Recent Activities</h3>
            <button onclick="closeRecentActivitiesModal()"
                style="background: transparent; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px;">
            <?php if (empty($employees_data) || !is_array($employees_data)): ?>
                <p style="text-align: center; color: #94a3b8; padding: 20px;">No recent activities found.</p>
            <?php else: ?>
                <?php
                $m_idx_modal = 0;
                foreach (array_slice($employees_data, 0, 10) as $emp):
                    $gender = strtolower($emp['gender'] ?? 'male');
                    $icon = ($gender === 'female' || $gender === 'f') ? '../assets/image/Women.png' : '../assets/image/Men.png';
                    $target_module = !empty($module_keys) ? $module_keys[$m_idx_modal % count($module_keys)] : 'dashboard.php';
                    $module_name = !empty($available_modules) ? $available_modules[$target_module] : 'Dashboard';
                    $m_idx_modal++;
                    ?>
                    <div
                        style="display: flex; align-items: center; gap: 15px; padding: 10px; border-radius: 12px; background: #f8fafc;">
                        <img src="<?= $icon ?>"
                            style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1e293b;">
                                <?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #64748b;">
                                <?= htmlspecialchars($emp['role'] ?? $emp['position'] ?? 'Staff') ?> • <span
                                    style="color: #4338ca; font-weight: 600;"><?= $module_name ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function openRecentActivitiesModal(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('recentActivitiesModal');
        modal.style.display = 'flex';
        setTimeout(() => { modal.style.opacity = '1'; }, 10);
        document.body.style.overflow = 'hidden';
    }

    function closeRecentActivitiesModal() {
        const modal = document.getElementById('recentActivitiesModal');
        modal.style.opacity = '0';
        setTimeout(() => { modal.style.display = 'none'; document.body.style.overflow = ''; }, 300);
    }
</script>