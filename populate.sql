USE admin_new;

INSERT INTO maintenance_logs (item_name, description, maintenance_date, assigned_staff, priority, reported_by, status) VALUES 
('Gym Equipment Lubrication', 'Standard monthly lubrication of treadmills and weight machines.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Roberto Cruz', 'medium', 'Gym Manager', 'pending'),
('Pool Chlorine Level Adjustment', 'Routine chemical balance check and cleaning.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Juan Santos', 'high', 'Pool Attendant', 'pending'),
('Garden Sprinkler Repair', 'Fixing broken nozzles in the North Wing garden.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Maria Leon', 'low', 'Landscaping Staff', 'pending'),
('Grand Ballroom AC Filter Cleaning', 'Deep cleaning of HVAC filters before the weekend events.', DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Roberto Cruz', 'medium', 'Event Coordinator', 'pending'),
('Elevator Safety Certification', 'Annual inspection by external contractor (TechLift Solutions).', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'TechLift Solutions', 'high', 'Front Office', 'pending'),
('Kitchen Grease Trap Cleaning', 'Monthly maintenance of drainage systems.', DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'Sanitation Team', 'medium', 'Head Chef', 'pending');
