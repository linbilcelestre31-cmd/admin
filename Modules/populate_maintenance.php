<?php
require_once __DIR__ . '/../db/db.php';

$pdo = get_pdo();

$tasks = [
    [
        'item_name' => 'Gym Equipment Lubrication',
        'description' => 'Standard monthly lubrication of treadmills and weight machines to ensure smooth operation.',
        'maintenance_date' => date('Y-m-d', strtotime('+1 day')),
        'assigned_staff' => 'Roberto Cruz',
        'priority' => 'medium',
        'reported_by' => 'Gym Manager',
        'status' => 'pending'
    ],
    [
        'item_name' => 'Pool Chlorine Level Adjustment',
        'description' => 'Routine chemical balance check and cleaning of filtering system.',
        'maintenance_date' => date('Y-m-d', strtotime('+2 days')),
        'assigned_staff' => 'Juan Santos',
        'priority' => 'high',
        'reported_by' => 'Pool Attendant',
        'status' => 'pending'
    ],
    [
        'item_name' => 'Garden Sprinkler Repair',
        'description' => 'Fixing broken nozzles in the North Wing garden area to prevent water wastage.',
        'maintenance_date' => date('Y-m-d', strtotime('+3 days')),
        'assigned_staff' => 'Maria Leon',
        'priority' => 'low',
        'reported_by' => 'Landscaping Staff',
        'status' => 'pending'
    ],
    [
        'item_name' => 'Grand Ballroom AC Filter Cleaning',
        'description' => 'Deep cleaning of HVAC filters before the weekend conference events.',
        'maintenance_date' => date('Y-m-d', strtotime('+4 days')),
        'assigned_staff' => 'Roberto Cruz',
        'priority' => 'medium',
        'reported_by' => 'Event Coordinator',
        'status' => 'pending'
    ],
    [
        'item_name' => 'Elevator Safety Certification',
        'description' => 'Annual inspection by external contractor (TechLift Solutions) for safety compliance.',
        'maintenance_date' => date('Y-m-d', strtotime('+5 days')),
        'assigned_staff' => 'TechLift Solutions',
        'priority' => 'high',
        'reported_by' => 'Front Office',
        'status' => 'pending'
    ],
    [
        'item_name' => 'Kitchen Grease Trap Cleaning',
        'description' => 'Monthly maintenance of drainage systems in the main kitchen area.',
        'maintenance_date' => date('Y-m-d', strtotime('+6 days')),
        'assigned_staff' => 'Sanitation Team',
        'priority' => 'medium',
        'reported_by' => 'Head Chef',
        'status' => 'pending'
    ]
];

try {
    $stmt = $pdo->prepare("INSERT INTO maintenance_logs (item_name, description, maintenance_date, assigned_staff, priority, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($tasks as $task) {
        $stmt->execute([
            $task['item_name'],
            $task['description'],
            $task['maintenance_date'],
            $task['assigned_staff'],
            $task['priority'],
            $task['reported_by'],
            $task['status']
        ]);
    }
    echo "Successfully populated " . count($tasks) . " maintenance tasks!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>