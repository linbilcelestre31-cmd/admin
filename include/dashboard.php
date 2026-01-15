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
    </div>

    <!-- Revenue Section -->
    <div style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <div>
                <h2 style="font-size: 2rem; font-weight: 800; color: #1e293b; margin: 0;">
                    ₱<?= number_format($revenue, 2) ?></h2>
                <div style="margin-top: 5px; color: #22c55e; font-weight: 600; font-size: 0.9rem;">
                    <i class="fa-solid fa-arrow-up"></i> 16% <span style="color: #94a3b8; font-weight: 400;">from last
                        month</span>
                </div>
            </div>
            <div>
                <!-- Mock Legend -->
                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #64748b;">
                    <span style="width: 10px; height: 10px; background: #22c55e; border-radius: 50%;"></span> Revenue
                </span>
            </div>
        </div>

        <!-- Chart Container -->
        <div style="height: 300px; width: 100%;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Bottom Split Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">

        <!-- Recent Activities -->
        <div style="background: white; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">Recent activities</h3>
                <a href="#" onclick="switchTab('reservations'); return false;"
                    style="font-size: 0.85rem; color: #64748b; text-decoration: none;">View all <i
                        class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php if (empty($recent_activities)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 20px;">No recent activities.</p>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <!-- Avatar Placeholder -->
                            <div
                                style="width: 40px; height: 40px; background: #e0e7ff; color: #4338ca; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">
                                <?= substr($activity['customer_name'], 0, 2) ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;">
                                    <?= htmlspecialchars($activity['customer_name']) ?>
                                </div>
                                <div style="font-size: 0.85rem; color: #64748b;">
                                    <?= htmlspecialchars($activity['facility_name']) ?> <span
                                        style="display: inline-block; width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%; vertical-align: middle; margin: 0 4px;"></span>
                                    <?= $activity['event_type'] ?>
                                </div>
                            </div>
                            <div style="font-size: 0.8rem; color: #94a3b8;">
                                <!-- Simple Time Ago -->
                                <?php
                                $time = strtotime($activity['created_at']);
                                $diff = time() - $time;
                                if ($diff < 60)
                                    echo "Just now";
                                elseif ($diff < 3600)
                                    echo round($diff / 60) . " mins";
                                elseif ($diff < 86400)
                                    echo round($diff / 3600) . " hours";
                                else
                                    echo round($diff / 86400) . " days";
                                ?>
                            </div>
                        </div>
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

<!-- Initialize Charts -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('revenueChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                    datasets: [{
                        label: 'Revenue',
                        data: [5000, 8000, 6000, 15940, 9000, 12000, 10000], // Mock Data
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        barThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [2, 4], color: '#f1f5f9' },
                            ticks: { callback: function (value) { return '₱' + value / 1000 + 'k'; } }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });
</script>