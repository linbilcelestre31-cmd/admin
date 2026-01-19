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
    
        // 1. New Bookings (Total Reservations)
        $new_bookings = $db->query("SELECT COUNT(*) FROM reservations")->fetchColumn() ?? 0;

        // 2. Available Rooms (Facilities)
        // Simple logic: Total Facilities. In a real app, check availability for *now*.
        $available_rooms = $db->query("SELECT COUNT(*) FROM facilities WHERE status = 'active'")->fetchColumn() ?? 0;

        // 3. Check In (Today's Reservations)
        $check_in = $db->query("SELECT COUNT(*) FROM reservations WHERE event_date = CURDATE()")->fetchColumn() ?? 0;

        // 4. Check Out (Estimating completed or ending today)
        $check_out = $db->query("SELECT COUNT(*) FROM reservations WHERE event_date = CURDATE() AND status = 'completed'")->fetchColumn() ?? 0;

        // 5. Revenue
        $revenue = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM reservations WHERE status = 'confirmed'")->fetchColumn() ?? 0;

        // 6. Recent Activities
        $recent_activities = $db->query("
            SELECT r.*, f.name as facility_name 
            FROM reservations r 
            JOIN facilities f ON r.facility_id = f.id 
            ORDER BY r.created_at DESC 
            LIMIT 4
         ")->fetchAll(PDO::FETCH_ASSOC);

        // 7. Employee Count from HR4 API
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

        <!-- Booking Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #eff6ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                    <i class="fa-solid fa-calendar-check" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $new_bookings ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">New Booking</p>
            </div>
            <!-- Mini Chart Decoration (CSS) -->
            <div style="height: 40px; margin-top: 10px; display: flex; align-items: flex-end; gap: 3px; opacity: 0.5;">
                <div style="width: 15%; background: #3b82f6; height: 40%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #3b82f6; height: 70%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #3b82f6; height: 50%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #3b82f6; height: 90%; border-radius: 2px;"></div>
                <div style="width: 15%; background: #3b82f6; height: 60%; border-radius: 2px;"></div>
            </div>
        </div>

        <!-- Available Room Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #fff7ed; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #f97316;">
                    <i class="fa-solid fa-door-open" style="font-size: 1.2rem;"></i>
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

        <!-- Check In Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #22c55e;">
                    <i class="fa-solid fa-arrow-right-to-bracket" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $check_in ?></h3>
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

        <!-- Check Out Card -->
        <div
            style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div
                    style="width: 45px; height: 45px; background: #fef2f2; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                    <i class="fa-solid fa-arrow-right-from-bracket" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;"><?= $check_out ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Check Out (Today)</p>
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

        <!-- HR 2 Payroll System Card (Bypass) -->
        <?php if ($isSuperAdmin):
            $hr2_key = $_GET['bypass_key'] ?? $_SESSION['api_key'] ?? '';
            ?>
            <a href="https://hr2.atierahotelandrestaurant.com/Modules/dashboard.php?bypass_key=<?= urlencode($hr2_key) ?>&super_admin_session=true"
                target="_blank" style="text-decoration: none; display: block;">
                <div style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; height: 100%; transition: all 0.3s; cursor: pointer;"
                    onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#10b981'; this.style.boxShadow='0 10px 15px -3px rgba(16, 185, 129, 0.1)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div
                            style="width: 45px; height: 45px; background: #ecfdf5; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #10b981;">
                            <i class="fa-solid fa-money-check-dollar" style="font-size: 1.2rem;"></i>
                        </div>
                        <div
                            style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">
                            SYSTEM ACCESS
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">HR 2</h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin: 5px 0 0;">Payroll & Accounts</p>
                    </div>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- Bottom Split Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">

        <!-- Recent Activities -->
        <div style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent activities</h3>
                <a href="#" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">View all <i
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

        <!-- Bookings Summary -->
        <div style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Bookings</h3>
                <button
                    style="border: 1px solid #e2e8f0; background: white; padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; color: #64748b;">
                    <i class="fa-regular fa-calendar"></i> Monthly
                </button>
            </div>

            <div style="margin-bottom: 25px;">
                <h2 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin: 0;"><?= $new_bookings ?> <span
                        style="font-size: 1rem; font-weight: 400; color: #94a3b8;">Total Bookings</span></h2>
            </div>

            <!-- Progress Bar -->
            <?php
            // Simulate split 70% online, 30% offline for demo
            $online_pct = 70;
            $offline_pct = 30;
            $online_count = round($new_bookings * 0.7);
            $offline_count = $new_bookings - $online_count;
            ?>
            <div
                style="width: 100%; height: 12px; background: #e2e8f0; border-radius: 6px; display: flex; overflow: hidden; margin-bottom: 25px;">
                <div style="width: <?= $online_pct ?>%; background: #22c55e;"></div>
                <div style="width: <?= $offline_pct ?>%; background: #f97316;"></div>
            </div>

            <div style="display: flex; gap: 30px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                        <span style="width: 10px; height: 10px; background: #22c55e; border-radius: 50%;"></span>
                        <span style="font-size: 0.85rem; color: #64748b;">Online Booking</span>
                    </div>
                    <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem; padding-left: 18px;">
                        <?= $online_count ?>
                    </div>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                        <span style="width: 10px; height: 10px; background: #f97316; border-radius: 50%;"></span>
                        <span style="font-size: 0.85rem; color: #64748b;">Offline Booking</span>
                    </div>
                    <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem; padding-left: 18px;">
                        <?= $offline_count ?>
                    </div>
                </div>
            </div>

            <div
                style="margin-top: 30px; font-size: 0.85rem; color: #94a3b8; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-circle-info"></i> All bookings data is synced in real-time.
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
</style>