<?php
/**
 * LEGAL MANAGEMENT MODULE
 * Purpose: Manage legal contracts, agreements, and compliance documents
 * Features: Contract tracking, risk assessment, deadline monitoring, document management
 * HR4 API Integration: Can link contracts to employee data and fetch employee information
 * Financial API Integration: Access financial data for contract analysis
 */

// Include HR4 API for employee-contract linking
require_once __DIR__ . '/../integ/hr4_api.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../db/db.php';
$db = get_pdo();

// Self-healing: Ensure contracts table has 'contract_type' column
try {
    $db->query("SELECT contract_type FROM contracts LIMIT 1");
} catch (PDOException $e) {
    try {
        $db->exec("ALTER TABLE contracts ADD COLUMN contract_type VARCHAR(50) DEFAULT 'External' AFTER case_id");
    } catch (PDOException $ex) {
        // Already exists or other error
    }
}

// Self-healing: Ensure contacts table exists
try {
    $db->query("SELECT 1 FROM contacts LIMIT 1");
} catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Ensure static data exists in contracts for Internal/External sections
try {
    $checkQ = $db->query("SELECT COUNT(*) FROM contracts WHERE name LIKE '%Privacy Policy%' OR name LIKE '%Logistics Supply%'");
    if ($checkQ->fetchColumn() == 0) {
        $db->exec("INSERT IGNORE INTO contracts (name, case_id, contract_type, description, risk_level, risk_score) VALUES 
            ('Employee Privacy Policy 2024', 'HR-POL-001', 'Internal', 'Comprehensive privacy policy for hotel and restaurant staff.', 'Low', 15),
            ('Internal Operational Guidelines', 'OPS-SOP-2024', 'Internal', 'Operational standard procedures for internal departments.', 'Low', 20),
            ('Global Logistics Supply Agreement', 'LOGI-2024-01', 'External', 'Supply chain and logistics agreement with LogiTrans Corp.', 'Medium', 45),
            ('Outsourced Security Services NDA', 'SEC-NDA-042', 'External', 'Non-disclosure agreement with SafeGuard Solutions.', 'Low', 30)");
    }
} catch (PDOException $e) {
}

// Ensure additional Internal Data exists for new tabs
try {
    $extraDocs = [
        ['Employee Code of Conduct 2024', 'HR-POL-002', 'Internal', 'Standard code of conduct for all employees.', 'Low', 10],
        ['Labor Union Collective Agreement', 'HR-LAB-001', 'Internal', 'Agreement with Hotel Workers Union regarding wages and benefits.', 'Medium', 35],
        ['Staff Disciplinary Policy', 'HR-DISC-001', 'Internal', 'Procedures for employee disciplinary actions.', 'Low', 15],
        ['Workplace Safety Compliance Guide', 'CMP-SAF-2024', 'Internal', 'Safety protocols compliant with DOLE standards.', 'Low', 5],
        ['Board Resolution 2024-001', 'GOV-RES-001', 'Internal', 'Board approval for FY 2024 budget allocation.', 'Low', 0],
        ['Corporate By-Laws 2024 Amendment', 'GOV-LAW-002', 'Internal', 'Amendments to corporate by-laws regarding shareholder meetings.', 'Medium', 25],
        ['Annual Risk Audit Report', 'RSK-AUD-2023', 'Internal', 'Comprehensive risk assessment audit for 2023.', 'Medium', 40],
        ['Disaster Recovery Plan', 'RSK-REC-001', 'Internal', 'IT and Operations disaster recovery and business continuity plan.', 'High', 65],

        // External
        ['Food Supplier Contract - BestMeats', 'SUP-2024-001', 'External', 'Annual contract for premium meat supply.', 'Low', 20],
        ['Beverage Partnership Agreement', 'SUP-2024-002', 'External', 'Exclusive partnership with Major Soda Co.', 'Low', 15],
        ['City Hall Business Permit 2024', 'GOV-PER-2024', 'External', 'Annual business operation permit renewal.', 'High', 80],
        ['BIR Tax Compliance Certificate', 'GOV-TAX-001', 'External', 'Certificate of updated tax compliance.', 'High', 75],
        ['Pending Litigation - Case 8821', 'LAW-DISP-001', 'External', 'Ongoing labor dispute case filed by former contractor.', 'High', 90],
        ['Settlement Agreement - Slip/Fall', 'LAW-SET-002', 'External', 'Settlement agreement regarding minor guest accident.', 'Medium', 45],
        ['Data Privacy Compliance 2024', 'REG-DPA-001', 'External', 'Compliance report for National Data Privacy Act.', 'Medium', 50],
        ['Guest Waiver & Release Form', 'CON-PROT-001', 'External', 'Standard liability waiver for swimming pool usage.', 'Low', 30]
    ];

    foreach ($extraDocs as $doc) {
        $check = $db->prepare("SELECT COUNT(*) FROM contracts WHERE name = ?");
        $check->execute([$doc[0]]);
        if ($check->fetchColumn() == 0) {
            $ins = $db->prepare("INSERT INTO contracts (name, case_id, contract_type, description, risk_level, risk_score) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute($doc);
        }
    }
} catch (PDOException $e) {
}

// Super Admin Bypass Protocol
$isSuperAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');
if (isset($_GET['super_admin_session']) && $_GET['super_admin_session'] === 'true' && isset($_GET['bypass_key'])) {
    $bypass_key = $_GET['bypass_key'];
    $stmt = $db->prepare("SELECT * FROM `SuperAdminLogin_tb` WHERE api_key = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$bypass_key]);
    $sa_user = $stmt->fetch();
    if ($sa_user) {
        $isSuperAdmin = true;
        // Don't overwrite if we already have a session ID
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === 'SUPER_ADMIN') {
            $_SESSION['user_id'] = $sa_user['id'];
        }
        $_SESSION['role'] = 'super_admin';
    }
}

// Check if user is logged in (after potential bypass)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch security PIN from settings
$archivePin = '1234'; // Default
try {
    $stmt = $db->prepare("SELECT setting_value FROM email_settings WHERE setting_key = 'archive_pin'");
    $stmt->execute();
    $savedPin = $stmt->fetchColumn();
    if ($savedPin) {
        $archivePin = $savedPin;
    }
} catch (PDOException $e) {
    // If table doesn't exist or other error, fallback to default
}

// AI Risk Assessment Class
class ContractRiskAnalyzer
{
    private $riskFactors = [
        'financial_terms' => [
            'long_term_lease' => ['weight' => 15, 'high_risk' => 'Lease term > 10 years'],
            'unfavorable_rent' => ['weight' => 10, 'high_risk' => 'Guaranteed minimum rent + revenue share'],
            'hidden_fees' => ['weight' => 8, 'high_risk' => 'Undisclosed additional charges'],
            'security_deposit' => ['weight' => 7, 'high_risk' => 'Security deposit > 6 months']
        ],
        'operational_control' => [
            'restrictive_hours' => ['weight' => 8, 'high_risk' => 'Limited operating hours'],
            'supplier_restrictions' => ['weight' => 10, 'high_risk' => 'Exclusive supplier requirements'],
            'renovation_limits' => ['weight' => 7, 'high_risk' => 'Strict renovation restrictions'],
            'staffing_controls' => ['weight' => 5, 'high_risk' => 'Limited staffing autonomy']
        ],
        'legal_protection' => [
            'unlimited_liability' => ['weight' => 12, 'high_risk' => 'Unlimited liability clauses'],
            'personal_guarantee' => ['weight' => 10, 'high_risk' => 'Personal guarantees required'],
            'unilateral_amendments' => ['weight' => 8, 'high_risk' => 'Unilateral amendment rights'],
            'dispute_resolution' => ['weight' => 6, 'high_risk' => 'Unfavorable dispute resolution']
        ],
        'flexibility_exit' => [
            'termination_penalties' => ['weight' => 8, 'high_risk' => 'Heavy termination penalties'],
            'renewal_restrictions' => ['weight' => 6, 'high_risk' => 'Automatic renewal without notice'],
            'assignment_rights' => ['weight' => 4, 'high_risk' => 'Limited assignment rights'],
            'force_majeure' => ['weight' => 2, 'high_risk' => 'No force majeure clause']
        ]
    ];

    public function analyzeContract($contractData)
    {
        $totalScore = 0;
        $maxPossibleScore = 0;
        $riskFactorsFound = [];
        $recommendations = [];

        foreach ($this->riskFactors as $category => $factors) {
            foreach ($factors as $factorKey => $factor) {
                $maxPossibleScore += $factor['weight'];

                // Simulate AI detection - in real implementation, this would analyze contract text
                if ($this->detectRiskFactor($contractData, $factorKey)) {
                    $totalScore += $factor['weight'];
                    $riskFactorsFound[] = [
                        'category' => $category,
                        'factor' => $factor['high_risk'],
                        'weight' => $factor['weight']
                    ];
                }
            }
        }

        // Calculate risk percentage
        $riskPercentage = ($totalScore / $maxPossibleScore) * 100;

        // Determine risk level
        if ($riskPercentage >= 70) {
            $riskLevel = 'High';
            $recommendations = $this->getHighRiskRecommendations();
        } elseif ($riskPercentage >= 31) {
            $riskLevel = 'Medium';
            $recommendations = $this->getMediumRiskRecommendations();
        } else {
            $riskLevel = 'Low';
            $recommendations = $this->getLowRiskRecommendations();
        }

        return [
            'risk_score' => round($riskPercentage),
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactorsFound,
            'recommendations' => $recommendations,
            'analysis_summary' => $this->generateAnalysisSummary($riskLevel, $riskFactorsFound)
        ];
    }

    private function detectRiskFactor($contractData, $factorKey)
    {
        // Simulated AI detection - in production, this would use NLP/text analysis
        $keywords = [
            'long_term_lease' => ['10 years', '15 years', '20 years', 'long-term', 'extended term'],
            'unfavorable_rent' => ['minimum rent', 'revenue share', 'percentage of sales', 'guaranteed payment'],
            'hidden_fees' => ['additional charges', 'hidden costs', 'undisclosed fees', 'extra payments'],
            'security_deposit' => ['security deposit', '6 months', 'advance payment', 'deposit amount'],
            'restrictive_hours' => ['operating hours', 'business hours', 'time restrictions', 'hour limitations'],
            'supplier_restrictions' => ['exclusive supplier', 'approved vendors', 'vendor restrictions', 'supplier limitations'],
            'renovation_limits' => ['renovation restrictions', 'modification limits', 'alteration approval', 'structural changes'],
            'staffing_controls' => ['staff approval', 'employee restrictions', 'hiring limitations', 'personnel controls'],
            'unlimited_liability' => ['unlimited liability', 'full responsibility', 'complete liability', 'total responsibility'],
            'personal_guarantee' => ['personal guarantee', 'individual assurance', 'personal commitment', 'individual warranty'],
            'unilateral_amendments' => ['unilateral amendment', 'one-sided changes', 'sole discretion', 'exclusive right'],
            'termination_penalties' => ['termination fee', 'early termination', 'cancellation penalty', 'break clause fee'],
            'renewal_restrictions' => ['automatic renewal', 'auto-renew', 'automatic extension', 'self-renewing']
        ];

        $contractText = strtolower($contractData['contract_name'] . ' ' . $contractData['description']);

        if (isset($keywords[$factorKey])) {
            foreach ($keywords[$factorKey] as $keyword) {
                if (strpos($contractText, strtolower($keyword)) !== false) {
                    return true;
                }
            }
        }

        // Random factor for demo purposes - remove in production
        return rand(0, 100) < 30; // 30% chance to detect a risk factor for demo
    }

    private function getHighRiskRecommendations()
    {
        return [
            'Immediate legal review required',
            'Negotiate key risk clauses',
            'Consider alternative agreements',
            'Implement risk mitigation strategies',
            'Regular compliance monitoring'
        ];
    }

    private function getMediumRiskRecommendations()
    {
        return [
            'Standard legal review recommended',
            'Clarify ambiguous terms',
            'Document all understandings',
            'Establish monitoring procedures',
            'Plan for periodic reviews'
        ];
    }

    private function getLowRiskRecommendations()
    {
        return [
            'Routine monitoring sufficient',
            'Maintain proper documentation',
            'Schedule annual reviews',
            'Monitor regulatory changes',
            'Standard compliance procedures'
        ];
    }

    private function generateAnalysisSummary($riskLevel, $riskFactors)
    {
        $factorCount = count($riskFactors);

        if ($riskLevel === 'High') {
            return "Critical risk level detected with {$factorCount} high-risk factors requiring immediate attention.";
        } elseif ($riskLevel === 'Medium') {
            return "Moderate risk level with {$factorCount} risk factors needing standard review.";
        } else {
            return "Low risk level with minimal risk factors. Standard monitoring recommended.";
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_pdo();

    // Unified create/update handler for employees
    if (isset($_POST['save_employee'])) {
        $empId = intval($_POST['employee_id'] ?? 0);
        $name = $_POST['employee_name'] ?? '';
        $position = $_POST['employee_position'] ?? '';
        $email = $_POST['employee_email'] ?? '';
        $phone = $_POST['employee_phone'] ?? '';

        if ($empId > 0) {
            $q = "UPDATE contacts SET name=?, role=?, email=?, phone=? WHERE id=?";
            $s = $db->prepare($q);
            if ($s->execute([$name, $position, $email, $phone, $empId])) {
                $success_message = "Employee updated successfully!";
            } else {
                $error_message = "Failed to update employee.";
            }
        } else {
            $q = "INSERT INTO contacts (name, role, email, phone) VALUES (?, ?, ?, ?)";
            $s = $db->prepare($q);
            if ($s->execute([$name, $position, $email, $phone])) {
                $success_message = "Employee added successfully!";
            } else {
                $error_message = "Failed to add employee.";
            }
        }
    }

    // Add Document
    if (isset($_POST['add_document'])) {
        $doc_name = $_POST['doc_name'] ?? '';
        $doc_case = $_POST['doc_case'] ?? '';
        $file_path = '';
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $tmp = $_FILES['doc_file']['tmp_name'];
            $orig = $_FILES['doc_file']['name'];
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $fname = uniqid('doc_') . '.' . $ext;
            $dest = $upload_dir . $fname;
            if (move_uploaded_file($tmp, $dest))
                $file_path = $dest;
        }
        $q = "INSERT INTO documents (name, case_id, file_path, uploaded_at) VALUES (?, ?, ?, NOW())";
        $s = $db->prepare($q);
        if ($s->execute([$doc_name, $doc_case, $file_path])) {
            $success_message = "Document uploaded successfully!";
        } else {
            $error_message = "Failed to upload document.";
        }
    }

    // Delete Document
    if (isset($_POST['delete_document'])) {
        $doc_id = intval($_POST['document_id'] ?? 0);
        if ($doc_id > 0) {
            $q = "DELETE FROM documents WHERE id = ?";
            $s = $db->prepare($q);
            if ($s->execute([$doc_id])) {
                $success_message = "Document deleted.";
            } else {
                $error_message = "Failed to delete document.";
            }
        }
    }

    // Delete Contract (Super Admin Only)
    if (isset($_POST['delete_contract'])) {
        $contract_id = intval($_POST['contract_id'] ?? 0);
        if ($contract_id > 0) {
            $q = "DELETE FROM contracts WHERE id = ?";
            $s = $db->prepare($q);
            if ($s->execute([$contract_id])) {
                $success_message = "Contract deleted successfully.";
            } else {
                $error_message = "Failed to delete contract.";
            }
        }
    }

    // Handle Record Update
    if (isset($_POST['update_legal_record'])) {
        $id = intval($_POST['edit_id'] ?? 0);
        $type = $_POST['edit_type'] ?? '';
        $name = $_POST['edit_name'] ?? '';
        $case_id = $_POST['edit_case_id'] ?? '';

        if ($id > 0 && ($type === 'contract' || $type === 'document')) {
            if ($type === 'contract') {
                $desc = $_POST['edit_description'] ?? '';
                $ctype = $_POST['edit_contract_type'] ?? 'External';
                $q = "UPDATE contracts SET name = ?, case_id = ?, description = ?, contract_type = ? WHERE id = ?";
                $s = $db->prepare($q);
                $s->execute([$name, $case_id, $desc, $ctype, $id]);
            } else {
                $q = "UPDATE documents SET name = ?, case_id = ? WHERE id = ?";
                $s = $db->prepare($q);
                $s->execute([$name, $case_id, $id]);
            }
            $success_message = "Record updated successfully.";
        }
    }

    // Delete Employee (Super Admin Only)
    if (isset($_POST['delete_employee'])) {
        $emp_id = intval($_POST['employee_id'] ?? 0);
        if ($emp_id > 0) {
            $q = "DELETE FROM contacts WHERE id = ?";
            $s = $db->prepare($q);
            if ($s->execute([$emp_id])) {
                $success_message = "Employee record deleted successfully.";
            } else {
                $error_message = "Failed to delete employee.";
            }
        }
    }

    // Handle contract upload with AI analysis
    if (isset($_POST['add_contract'])) {
        $contract_name = $_POST['contract_name'];
        $case_id = $_POST['contract_case'];
        $description = $_POST['contract_description'] ?? '';

        // AI Risk Analysis
        $analyzer = new ContractRiskAnalyzer();
        $contractData = [
            'contract_name' => $contract_name,
            'description' => $description
        ];

        $riskAnalysis = $analyzer->analyzeContract($contractData);

        // Handle file upload
        $file_name = '';
        if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/contracts/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_tmp_name = $_FILES['contract_file']['tmp_name'];
            $file_original_name = $_FILES['contract_file']['name'];
            $file_extension = pathinfo($file_original_name, PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . $contract_name . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($file_tmp_name, $file_path)) {
                $file_name = $file_path;
            } else {
                $error_message = "Failed to upload file.";
            }
        }

        // Optional cover image upload -> saved as related document
        $image_path = '';
        if (isset($_FILES['contract_image']) && $_FILES['contract_image']['error'] === UPLOAD_ERR_OK) {
            $img_dir = 'uploads/contracts/images/';
            if (!is_dir($img_dir)) {
                mkdir($img_dir, 0777, true);
            }
            $img_tmp = $_FILES['contract_image']['tmp_name'];
            $img_name = $_FILES['contract_image']['name'];
            $img_ext = pathinfo($img_name, PATHINFO_EXTENSION);
            $img_file = uniqid('cimg_') . '.' . $img_ext;
            $img_dest = $img_dir . $img_file;
            if (move_uploaded_file($img_tmp, $img_dest)) {
                $image_path = $img_dest;
            }
        }

        $contract_type = $_POST['contract_type'] ?? 'External';

        $query = "INSERT INTO contracts (name, case_id, contract_type, description, file_path, risk_level, risk_score, risk_factors, recommendations, analysis_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);

        $risk_factors_json = json_encode($riskAnalysis['risk_factors']);
        $recommendations_json = json_encode($riskAnalysis['recommendations']);

        if (
            $stmt->execute([
                $contract_name,
                $case_id,
                $contract_type,
                $description,
                $file_name,
                $riskAnalysis['risk_level'],
                $riskAnalysis['risk_score'],
                $risk_factors_json,
                $recommendations_json,
                $riskAnalysis['analysis_summary']
            ])
        ) {
            $success_message = "Contract uploaded successfully! AI Risk Analysis Completed.";
            if (!empty($image_path)) {
                try {
                    $dq = $db->prepare("INSERT INTO documents (name, case_id, file_path, uploaded_at) VALUES (?, ?, ?, NOW())");
                    $dq->execute(['Contract Image: ' . $contract_name, $case_id, $image_path]);
                } catch (PDOException $e) {
                    // ignore
                }
            }
        } else {
            $error_message = "Failed to upload contract.";
        }
    }

    // Handle Maintenance Operations
    if (isset($_POST['add_maintenance'])) {
        $item_name = $_POST['maintenance_item'] ?? '';
        $description = $_POST['maintenance_description'] ?? '';
        $maintenance_date = $_POST['maintenance_date'] ?? '';
        $assigned_staff = $_POST['maintenance_staff'] ?? '';
        $contact_number = $_POST['maintenance_contact'] ?? '';
        $status = $_POST['maintenance_status'] ?? 'pending';

        if ($item_name && $description && $maintenance_date && $assigned_staff) {
            try {
                $stmt = $db->prepare("INSERT INTO maintenance_logs (item_name, description, maintenance_date, assigned_staff, contact_number, status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$item_name, $description, $maintenance_date, $assigned_staff, $contact_number, $status])) {
                    $success_message = "Maintenance log added successfully!";
                } else {
                    $error_message = "Failed to add maintenance log.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }

    if (isset($_POST['delete_maintenance'])) {
        $maintenance_id = intval($_POST['maintenance_id'] ?? 0);
        if ($maintenance_id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM maintenance_logs WHERE id = ?");
                if ($stmt->execute([$maintenance_id])) {
                    $success_message = "Maintenance log deleted successfully!";
                } else {
                    $error_message = "Failed to delete maintenance log.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }

    // Handle PDF Export (Idinagdag para sa PDF Report na may Password)
    if (isset($_POST['action']) && $_POST['action'] === 'export_pdf') {
        $password = 'legal2025'; // Password para sa PDF Report (Simulasyon)

        // Kunin ang lahat ng data ng kontrata para sa ulat
        $query = "SELECT name as contract_name, risk_level, risk_score, analysis_summary FROM contracts ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $contracts_to_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- SIMULASYON NG PDF GENERATION (Dahil hindi available ang external libraries) ---

        // I-set ang headers para sa pag-download ng file (ginamit ang .txt para sa simulation)
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="Legal_Contracts_Report_Protected.txt"');

        // Mag-output ng simpleng text na nagsasabi na nag-generate ng protected file
        echo "========================================================\n";
        echo "== NAKA-PROTEKTANG PDF REPORT NG KONTRATA (SIMULASYON) ==\n";
        echo "========================================================\n\n";
        echo "Ipinagbabawal ang pagtingin nang walang pahintulot.\n";
        echo "Ito ay naglalaman ng sensitibong legal na impormasyon.\n\n";
        echo "========================================================\n";
        echo "PASSWORD SA PAGBUKAS NG PDF (Ito ang kailangan mo sa totoong PDF): " . $password . "\n";
        echo "========================================================\n\n";

        echo "Kontrata sa Report:\n";
        foreach ($contracts_to_report as $contract) {
            echo "- " . $contract['contract_name'] . " (Risk: " . $contract['risk_level'] . ", Score: " . $contract['risk_score'] . "/100)\n";
            echo "  Buod ng Pagsusuri: " . $contract['analysis_summary'] . "\n";
        }

        exit;
    }

    // Add supporting document for a contract (saves into documents table using contract's case_id)
    if (isset($_POST['add_contract_document'])) {
        $contractId = intval($_POST['contract_id'] ?? 0);
        $docName = $_POST['doc_name'] ?? '';
        if ($contractId > 0 && $docName !== '') {
            // Fetch case_id for linking
            $stmt = $db->prepare("SELECT case_id FROM contracts WHERE id = ? LIMIT 1");
            $stmt->execute([$contractId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $caseId = $row['case_id'] ?? '';

            $file_path = '';
            if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/contracts/docs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $tmp = $_FILES['doc_file']['tmp_name'];
                $orig = $_FILES['doc_file']['name'];
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $fname = uniqid('cdoc_') . '.' . $ext;
                $dest = $upload_dir . $fname;
                if (move_uploaded_file($tmp, $dest))
                    $file_path = $dest;
            }

            $q = "INSERT INTO documents (name, case_id, file_path, uploaded_at) VALUES (?, ?, ?, NOW())";
            $s = $db->prepare($q);
            if ($s->execute([$docName, $caseId, $file_path])) {
                $success_message = "Contract document uploaded successfully!";
            } else {
                $error_message = "Failed to upload contract document.";
            }
        } else {
            $error_message = "Invalid contract or missing document name.";
        }
    }
}

$employees = [];
$contracts = [];
try {
    $api_employees = fetchAllEmployees(5);
    if (is_array($api_employees)) {
        foreach ($api_employees as $emp) {
            $jobTitle = 'N/A';
            if (isset($emp['employment_details']) && is_array($emp['employment_details'])) {
                $jobTitle = $emp['employment_details']['job_title'] ?? 'N/A';
            }
            $employees[] = [
                'id' => $emp['id'],
                'employee_id' => $emp['employee_id'] ?? ('EMN' . $emp['id']),
                'name' => ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''),
                'position' => $jobTitle,
                'email' => $emp['email'] ?? 'N/A',
                'phone' => $emp['contact_number'] ?? $emp['phone'] ?? 'N/A'
            ];
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching employees from API: " . $e->getMessage();
}

// Fetch contracts from database
try {
    $query = "SELECT * FROM contracts ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $error_message = "Error fetching contracts: " . $exception->getMessage();
}

// Fetch local employees from contacts table and merge
try {
    $local_emp_stmt = $db->query("SELECT * FROM contacts");
    $has_local_employees = false;
    while ($row = $local_emp_stmt->fetch(PDO::FETCH_ASSOC)) {
        $has_local_employees = true;
        // Direct addition for now, simplified logic
        $employees[] = [
            'id' => $row['id'],
            'employee_id' => 'LCL-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'name' => $row['name'],
            'position' => $row['role'] ?? 'N/A',
            'email' => $row['email'] ?? 'N/A',
            'phone' => $row['phone'] ?? 'N/A'
        ];
    }
    
    // If no employees found, add sample data
    if (!$has_local_employees && empty($employees)) {
        $employees = [
            [
                'id' => 1,
                'employee_id' => 'EMP001',
                'name' => 'John Doe',
                'position' => 'Legal Manager',
                'email' => 'john.doe@company.com',
                'phone' => '123-456-7890'
            ],
            [
                'id' => 2,
                'employee_id' => 'EMP002',
                'name' => 'Jane Smith',
                'position' => 'Legal Assistant',
                'email' => 'jane.smith@company.com',
                'phone' => '098-765-4321'
            ],
            [
                'id' => 3,
                'employee_id' => 'EMP003',
                'name' => 'Robert Johnson',
                'position' => 'Compliance Officer',
                'email' => 'robert.johnson@company.com',
                'phone' => '555-123-4567'
            ]
        ];
    }
} catch (PDOException $e) {
    // If query fails, add sample data
    $employees = [
        [
            'id' => 1,
            'employee_id' => 'EMP001',
            'name' => 'John Doe',
            'position' => 'Legal Manager',
            'email' => 'john.doe@company.com',
            'phone' => '123-456-7890'
        ],
        [
            'id' => 2,
            'employee_id' => 'EMP002',
            'name' => 'Jane Smith',
            'position' => 'Legal Assistant',
            'email' => 'jane.smith@company.com',
            'phone' => '098-765-4321'
        ]
    ];
}

// NEW: Fetch documents and billing (with fallbacks) and build risk summary
$documents = [];
try {
    $query = "SELECT id, name, case_id, file_path, uploaded_at, risk_level, risk_score, analysis_date, ai_analysis FROM documents ORDER BY uploaded_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // fallback demo data if query fails
    $documents = [
        ['id' => 1, 'name' => 'Employment Contract.pdf', 'case_id' => 'C-001', 'file_path' => 'uploads/documents/Employment Contract.pdf', 'uploaded_at' => '2023-05-20 12:00:00', 'risk_level' => 'unknown', 'risk_score' => null, 'analysis_date' => null, 'ai_analysis' => null],
        ['id' => 2, 'name' => 'Supplier Agreement.docx', 'case_id' => 'C-002', 'file_path' => 'uploads/documents/Supplier Agreement.docx', 'uploaded_at' => '2023-06-25 12:00:00', 'risk_level' => 'unknown', 'risk_score' => null, 'analysis_date' => null, 'ai_analysis' => null]
    ];
}

// Risk summary with normalized casing
$riskCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($contracts as $c) {
    $lvl_raw = $c['risk_level'] ?? 'Low';
    $lvl = ucfirst(strtolower($lvl_raw));
    if (isset($riskCounts[$lvl])) {
        $riskCounts[$lvl]++;
    } else {
        $riskCounts['Low']++;
    }
}
$totalContracts = count($contracts);
$highPct = $totalContracts ? round(($riskCounts['High'] / $totalContracts) * 100, 1) : 0;
$mediumPct = $totalContracts ? round(($riskCounts['Medium'] / $totalContracts) * 100, 1) : 0;
$lowPct = $totalContracts ? round(($riskCounts['Low'] / $totalContracts) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <title>Legal Management - Atiéra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Montserrat:wght@300;400&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="../assets/css/legalmanagement.css?v=1" media="none"
        onload="if(media!='all')media='all'">

    <style>
        /* Center all table header and cell content within this module */
        .data-table th,
        .data-table td {
            text-align: center !important;
            vertical-align: middle;
        }

        /* Modal Base Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background: white;
            border-radius: 28px;
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-close {
            position: absolute;
            top: 25px;
            right: 25px;
            background: #f1f5f9;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #64748b;
            font-size: 22px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: #ef4444;
            transform: scale(1.1) rotate(90deg);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Risk Badges */
        .risk-badge {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .risk-high {
            background: #fee2e2 !important;
            color: #ef4444 !important;
            border: 1px solid #fecaca !important;
        }

        .risk-medium {
            background: #fef3c7 !important;
            color: #f59e0b !important;
            border: 1px solid #fde68a !important;
        }

        .risk-low {
            background: #dcfce7 !important;
            color: #22c55e !important;
            border: 1px solid #bbf7d0 !important;
        }

        /* When password modal is active, hide everything except the password modal */
        .pwd-focus *:not(#passwordModal):not(#passwordModal *) {
            opacity: 0 !important;
            pointer-events: none !important;
            user-select: none !important;
            transition: opacity .08s linear;
        }

        /* Ensure password modal always on top */
        #passwordModal {
            z-index: 99999 !important;
        }

        .back-btn {
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 0;
            text-align: center;
            font-size: 10px;
            width: 80px;
        }

        /* Fixed: Remove double container effect in modals */
        #employeeFormContainer .form-container,
        #documentFormContainer .form-container,
        #invoiceFormContainer .form-container,
        #contractFormContainer .form-container,
        .form-container.modal-inner {
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: block !important;
            width: 100% !important;
            background-color: transparent !important;
        }

        /* Blur Effect Styles */
        .blurred-content {
            filter: blur(8px);
            user-select: none;
            pointer-events: none;
            transition: all 0.5s ease;
        }

        .reveal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            z-index: 10;
            border-radius: 8px;
        }

        .reveal-btn {
            padding: 12px 24px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s;
        }

        .reveal-btn:hover {
            transform: scale(1.05);
            background: #34495e;
        }

        /* Enforce white text on primary actions to fix visibility issues */
        button[type="submit"],
        .btn-primary {
            color: #ffffff !important;
        }

        /* Added: Scrollable table container to handle large datasets */
        .table-scroll-container {
            width: 100%;
            max-height: 450px;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-top: 10px;
            background: #fff;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Ensure forms in tables don't disrupt layout */
        .data-table form {
            display: inline-block !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .action-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }

        .action-btn {
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        /* Loading Overlay Styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0d1b3e;
            /* Matches login background */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999999;
            transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loading-overlay iframe {
            width: 100%;
            height: 100%;
            border: none;
            overflow: hidden;
            background: transparent;
        }

        /* Prevent scroll during loading */
        body:not(.loaded) {
            overflow: hidden !important;
        }

        /* Modal Styles */
        .premium-modal {
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <iframe src="../animation/loading.html" allowtransparency="true"></iframe>
    </div>

    <!-- Login Screen -->
    <div class="login-container" id="loginScreen">
        <div class="login-form">
            <div style="text-align: center; margin-bottom: 25px;">
                <img src="../assets/image/logo2.png" alt="Logo" style="width: 200px; height: auto;">
            </div>
            <h2>Legal Management System</h2>
            <p>Enter your PIN to access the system</p>
            <div class="pin-input">
                <input type="password" maxlength="1" class="pin-digit" id="pin1" disabled>
                <input type="password" maxlength="1" class="pin-digit" id="pin2" disabled>
                <input type="password" maxlength="1" class="pin-digit" id="pin3" disabled>
                <input type="password" maxlength="1" class="pin-digit" id="pin4" disabled>
            </div>
            <button class="login-btn" id="loginBtn" disabled>Login</button>
            <div class="error-message" id="errorMessage">Invalid PIN. Please try again.</div>
        </div>
    </div>

    <!-- Dashboard -->
    <div class="dashboard" id="dashboard">
        <div class="header">
            <div class="container">
                <div class="header-content">
                    <div class="logo">Legal Management System</div>
                    <div class="user-info">
                        <span>Welcome, Admin</span>
                        <?php if ($isSuperAdmin): ?>
                            <a href="../Super-admin/Dashboard.php" class="logout-btn" id="backDashboardBtn"
                                style="text-decoration: none;">
                                <i class="fas fa-arrow-left"></i>
                                Back
                            </a>
                        <?php else: ?>
                            <button type="button" class="logout-btn" id="backDashboardBtn"
                                onclick="window.location.replace('../Modules/dashboard.php')">
                                <span class="icon-img-placeholder">⏻</span>
                                logout
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="nav-tabs">
                <div class="nav-tab active" data-target="employees">Employees</div>
                <div class="nav-tab" data-target="internal">Internal</div>
                <div class="nav-tab" data-target="external">External</div>
                <div class="nav-tab" data-target="documents">Documents</div>
                <div class="nav-tab" data-target="contracts">Contracts</div>
                <div class="nav-tab" data-target="maintenance">Maintenance</div>
                <div class="nav-tab" data-target="risk_analysis">Risk Analysis</div>
            </div>

            <div class="content-section" id="employees">
                <h2 class="section-title">Employee Information</h2>
                <button class="add-btn" id="addEmployeeBtn">
                    <i>+</i> Add Employee
                </button>

                <!-- Add Employee Form -->
                <div class="form-container" id="employeeForm" style="display: none;">
                    <h3>Add Employee</h3>
                    <form method="POST" id="employeeFormData">
                        <div class="form-group">
                            <label for="employeeName">Name</label>
                            <input type="text" id="employeeName" name="employee_name" class="form-control"
                                placeholder="Enter employee name" required>
                        </div>
                        <div class="form-group">
                            <label for="employeePosition">Position</label>
                            <input type="text" id="employeePosition" name="employee_position" class="form-control"
                                placeholder="Enter position" required>
                        </div>
                        <div class="form-group">
                            <label for="employeeEmail">Email</label>
                            <input type="email" id="employeeEmail" name="employee_email" class="form-control"
                                placeholder="Enter email" required>
                        </div>
                        <div class="form-group">
                            <label for="employeePhone">Phone</label>
                            <input type="text" id="employeePhone" name="employee_phone" class="form-control"
                                placeholder="Enter phone number" required>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="cancel-btn" id="cancelEmployeeBtn">Cancel</button>
                            <button type="submit" class="save-btn" name="save_employee" id="saveEmployeeBtn">Save
                                Employee</button>
                        </div>
                    </form>
                </div>

                <!-- Employees Table wrapped in scroll container -->
                <div class="table-scroll-container">
                    <table class="data-table premium-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeesTableBody">
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id'] ?? ('E-' . str_pad($employee['id'], 3, '0', STR_PAD_LEFT))); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                                    <td>
                                        <div class="action-container">
                                            <button class="action-btn view-btn" data-type="employee-view"
                                                data-emp='<?php echo htmlspecialchars(json_encode($employee)); ?>'>
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>
                                            <?php if ($isSuperAdmin): ?>
                                                <button class="action-btn edit-btn"
                                                    style="background:#f59e0b; color:white; border:none; border-radius:8px; padding:6px 12px;"
                                                    onclick='editEmployee(<?php echo json_encode($employee); ?>)'>
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </button>
                                                <form method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                                    <input type="hidden" name="employee_id"
                                                        value="<?php echo $employee['id']; ?>">
                                                    <button type="submit" name="delete_employee" class="action-btn delete-btn"
                                                        style="background:#ef4444; color:white; border:none; border-radius:8px; padding:6px 12px;">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Internal Section -->
            <div class="content-section" id="internal">
                <div class="section-header">
                    <h2 class="section-title">Internal Documents & Policies</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="add-btn" onclick="generateInternalPDF()" style="background: #8b5cf6;">
                            <i class="fa-solid fa-file-pdf"></i> Generate PDF Report
                        </button>
                        <button class="add-btn" onclick="showAddContractModal()">
                            <i>+</i> Add Internal Doc
                        </button>
                    </div>
                </div>

                <!-- Internal Legal Management Tabs -->
                <div class="internal-tabs-container" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                        <button class="legal-tab-btn active" onclick="filterLegalDocs(this, 'policies')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: #3b82f6; color: white; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-book-open" style="margin-right: 8px;"></i> Policies & Handbook
                        </button>
                        <button class="legal-tab-btn" onclick="filterLegalDocs(this, 'labor')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-users" style="margin-right: 8px;"></i> Labor Relations
                        </button>
                        <button class="legal-tab-btn" onclick="filterLegalDocs(this, 'compliance')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-scale-balanced" style="margin-right: 8px;"></i> Internal Compliance
                        </button>
                        <button class="legal-tab-btn" onclick="filterLegalDocs(this, 'governance')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-building-columns" style="margin-right: 8px;"></i> Corporate Governance
                        </button>
                        <button class="legal-tab-btn" onclick="filterLegalDocs(this, 'risk')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-shield-halved" style="margin-right: 8px;"></i> Risk Management
                        </button>
                    </div>
                </div>

                <div style="position: relative;">
                    <div id="internalSectionContent" class="blurred-content">
                        <div class="table-scroll-container">
                            <table class="data-table premium-table">
                                <thead>
                                    <tr>
                                        <th>Policy Name</th>
                                        <th>Case ID</th>
                                        <th>Risk Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $internalDocs = array_filter($contracts, function ($c) {
                                        return (isset($c['contract_type']) && $c['contract_type'] === 'Internal');
                                    });
                                    if (!empty($internalDocs)):
                                        foreach ($internalDocs as $doc):
                                            // Auto-categorize based on name
                                            $nameLower = strtolower($doc['name']);
                                            $docCategory = 'policies'; // Default
                                    
                                            if (strpos($nameLower, 'policy') !== false || strpos($nameLower, 'handbook') !== false) {
                                                $docCategory = 'policies';
                                            } elseif (strpos($nameLower, 'labor') !== false || strpos($nameLower, 'contract') !== false || strpos($nameLower, 'disciplinary') !== false || strpos($nameLower, 'employee') !== false) {
                                                $docCategory = 'labor';
                                            } elseif (strpos($nameLower, 'compliance') !== false || strpos($nameLower, 'rule') !== false || strpos($nameLower, 'guide') !== false || strpos($nameLower, 'procedure') !== false) {
                                                $docCategory = 'compliance';
                                            } elseif (strpos($nameLower, 'governance') !== false || strpos($nameLower, 'board') !== false || strpos($nameLower, 'bylaw') !== false || strpos($nameLower, 'resolution') !== false) {
                                                $docCategory = 'governance';
                                            } elseif (strpos($nameLower, 'risk') !== false || strpos($nameLower, 'audit') !== false) {
                                                $docCategory = 'risk';
                                            }
                                            ?>
                                            <tr class="internal-doc-row" data-category="<?php echo $docCategory; ?>">
                                                <td><a href="javascript:void(0)" class="clickable-name"
                                                        onclick="showLegalDetails('<?php echo addslashes($doc['name']); ?>', '<?php echo addslashes($doc['case_id']); ?>', '<?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>', 'Internal', 'Compliance')"><?php echo htmlspecialchars($doc['name']); ?></a>
                                                </td>
                                                <td><?php echo htmlspecialchars($doc['case_id']); ?></td>
                                                <td>
                                                    <span
                                                        class="risk-badge risk-<?php echo strtolower($doc['risk_level'] ?? 'low'); ?>">
                                                        <?php echo htmlspecialchars($doc['risk_level'] ?? 'Low'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-container">
                                                        <button class="action-btn view-btn"
                                                            onclick="showLegalDetails('<?php echo addslashes($doc['name']); ?>', '<?php echo addslashes($doc['case_id']); ?>', '<?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>', 'Internal', 'Compliance')"><i
                                                                class="fa-solid fa-eye"></i> View</button>
                                                        <button class="action-btn analyze-btn"
                                                            onclick="showLegalAnalysis('<?php echo addslashes($doc['name']); ?>', 'Internal')"><i
                                                                class="fa-solid fa-wand-magic-sparkles"></i> Analyze</button>

                                                        <?php if ($isSuperAdmin): ?>
                                                            <button class="action-btn edit-btn"
                                                                style="background:#f59e0b; color:white; border:none; border-radius:8px; padding:6px 12px;"
                                                                onclick='editLegalRecord(<?php echo json_encode($doc); ?>, "contract")'>
                                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                                            </button>
                                                            <form method="POST"
                                                                onsubmit="return confirm('Delete this internal document?');">
                                                                <input type="hidden" name="contract_id"
                                                                    value="<?php echo $doc['id']; ?>">
                                                                <button type="submit" name="delete_contract"
                                                                    class="action-btn delete-btn"
                                                                    style="background:#ef4444; color:white; border:none; border-radius:8px; padding:6px 12px;">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="4">No internal documents found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="internalRevealOverlay" class="reveal-overlay">
                        <button class="reveal-btn"
                            onclick="withPasswordGate(() => { document.getElementById('internalSectionContent').classList.remove('blurred-content'); document.getElementById('internalRevealOverlay').style.display='none'; })">
                            <i class="fa-solid fa-lock"></i> Click to Reveal Documents
                        </button>
                    </div>
                </div>
            </div>

            <!-- External Section -->
            <div class="content-section" id="external">
                <div class="section-header">
                    <h2 class="section-title">External Agreements</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="add-btn" onclick="generateExternalPDF()" style="background: #10b981;">
                            <i class="fa-solid fa-file-pdf"></i> Generate PDF Report
                        </button>
                        <button class="add-btn" onclick="showAddContractModal()">
                            <i>+</i> Add External Contract
                        </button>
                    </div>
                </div>

                <!-- External Legal Management Tabs -->
                <div class="external-tabs-container" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                        <button class="ext-tab-btn active" onclick="filterExternalDocs(this, 'supplier')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: #10b981; color: white; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-file-contract" style="margin-right: 8px;"></i> Supplier Contracts
                        </button>
                        <button class="ext-tab-btn" onclick="filterExternalDocs(this, 'govt')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-landmark" style="margin-right: 8px;"></i> Govt Relations
                        </button>
                        <button class="ext-tab-btn" onclick="filterExternalDocs(this, 'lawsuits')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-gavel" style="margin-right: 8px;"></i> Lawsuits & Disputes
                        </button>
                        <button class="ext-tab-btn" onclick="filterExternalDocs(this, 'compliance')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-clipboard-check" style="margin-right: 8px;"></i> Regulatory Compliance
                        </button>
                        <button class="ext-tab-btn" onclick="filterExternalDocs(this, 'consumer')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-user-shield" style="margin-right: 8px;"></i> Consumer Protection
                        </button>
                    </div>
                </div>

                <div style="position: relative;">
                    <div id="externalSectionContent" class="blurred-content">
                        <div class="table-scroll-container">
                            <table class="data-table premium-table">
                                <thead>
                                    <tr>
                                        <th>Agreement Name</th>
                                        <th>Case ID</th>
                                        <th>Risk Level</th>
                                        <th>Expiry Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $externalDocs = array_filter($contracts, function ($c) {
                                        return (isset($c['contract_type']) && $c['contract_type'] === 'External');
                                    });
                                    if (!empty($externalDocs)):
                                        foreach ($externalDocs as $doc):
                                            // Auto-categorize based on name
                                            $nameLower = strtolower($doc['name']);
                                            $docCategory = 'supplier'; // Default
                                    
                                            if (strpos($nameLower, 'supplier') !== false || strpos($nameLower, 'supply') !== false || strpos($nameLower, 'partner') !== false || strpos($nameLower, 'agreement') !== false || strpos($nameLower, 'nda') !== false) {
                                                $docCategory = 'supplier';
                                            } elseif (strpos($nameLower, 'govt') !== false || strpos($nameLower, 'bir') !== false || strpos($nameLower, 'sec') !== false || strpos($nameLower, 'dole') !== false || strpos($nameLower, 'permit') !== false || strpos($nameLower, 'tax') !== false) {
                                                $docCategory = 'govt';
                                            } elseif (strpos($nameLower, 'lawsuit') !== false || strpos($nameLower, 'dispute') !== false || strpos($nameLower, 'case') !== false || strpos($nameLower, 'settlement') !== false || strpos($nameLower, 'litigation') !== false) {
                                                $docCategory = 'lawsuits';
                                            } elseif (strpos($nameLower, 'regulatory') !== false || strpos($nameLower, 'compliance') !== false || strpos($nameLower, 'privacy') !== false || strpos($nameLower, 'dpa') !== false) {
                                                $docCategory = 'compliance';
                                            } elseif (strpos($nameLower, 'consumer') !== false || strpos($nameLower, 'customer') !== false || strpos($nameLower, 'waiver') !== false || strpos($nameLower, 'guest') !== false) {
                                                $docCategory = 'consumer';
                                            }
                                            ?>
                                            <tr class="external-doc-row" data-category="<?php echo $docCategory; ?>">
                                                <td><a href="javascript:void(0)" class="clickable-name"
                                                        onclick="showLegalDetails('<?php echo addslashes($doc['name']); ?>', '<?php echo addslashes($doc['case_id']); ?>', '<?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>', 'External', 'Vendor')"><?php echo htmlspecialchars($doc['name']); ?></a>
                                                </td>
                                                <td><?php echo htmlspecialchars($doc['case_id']); ?></td>
                                                <td>
                                                    <span
                                                        class="risk-badge risk-<?php echo strtolower($doc['risk_level'] ?? 'low'); ?>">
                                                        <?php echo htmlspecialchars($doc['risk_level'] ?? 'Low'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y-m-d', strtotime($doc['created_at'] . ' +1 year')); ?>
                                                </td>
                                                <td>
                                                    <div class="action-container">
                                                        <button class="action-btn view-btn"
                                                            onclick="showLegalDetails('<?php echo addslashes($doc['name']); ?>', '<?php echo addslashes($doc['case_id']); ?>', '<?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>', 'External', 'Vendor')"><i
                                                                class="fa-solid fa-eye"></i> View</button>
                                                        <button class="action-btn analyze-btn"
                                                            onclick="showLegalAnalysis('<?php echo addslashes($doc['name']); ?>', 'External')"><i
                                                                class="fa-solid fa-wand-magic-sparkles"></i> Analyze</button>

                                                        <?php if ($isSuperAdmin): ?>
                                                            <button class="action-btn edit-btn"
                                                                style="background:#f59e0b; color:white; border:none; border-radius:8px; padding:6px 12px;"
                                                                onclick='editLegalRecord(<?php echo json_encode($doc); ?>, "contract")'>
                                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                                            </button>
                                                            <form method="POST"
                                                                onsubmit="return confirm('Delete this external agreement?');">
                                                                <input type="hidden" name="contract_id"
                                                                    value="<?php echo $doc['id']; ?>">
                                                                <button type="submit" name="delete_contract"
                                                                    class="action-btn delete-btn"
                                                                    style="background:#ef4444; color:white; border:none; border-radius:8px; padding:6px 12px;">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="5">No external agreements found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="externalRevealOverlay" class="reveal-overlay">
                        <button class="reveal-btn"
                            onclick="withPasswordGate(() => { document.getElementById('externalSectionContent').classList.remove('blurred-content'); document.getElementById('externalRevealOverlay').style.display='none'; })">
                            <i class="fa-solid fa-lock"></i> Click to Reveal Agreements
                        </button>
                    </div>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="content-section" id="documents">
                <div class="section-header">
                    <h2 class="section-title">Case Documents</h2>
                    <button class="add-btn" onclick="showAddDocumentModal()">
                        <i>+</i> Upload Document
                    </button>
                </div>
                <div class="table-scroll-container">
                    <table class="data-table premium-table">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Case</th>
                                <th>Date Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="documentsTableBody">
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($doc['file_path'])): ?>
                                                <a href="#" class="view-pdf-link text-blue-600 hover:underline"
                                                    data-pdf-type="document"
                                                    data-pdf-content='<?php echo htmlspecialchars(json_encode($doc)); ?>'><?php echo htmlspecialchars($doc['name']); ?></a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($doc['name']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['case_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($doc['uploaded_at'] ?? 'now'))); ?>
                                        </td>
                                        <td>
                                            <div class="action-container">
                                                <button class="action-btn download-btn" data-type="doc-download"
                                                    data-pdf-type="document"
                                                    data-pdf-content='<?php echo htmlspecialchars(json_encode($doc)); ?>'
                                                    style="background:linear-gradient(135deg, #059669 0%, #10b981 100%); color:#fff; border:none; border-radius:12px; padding:8px 16px; font-weight:700; box-shadow:0 4px 12px rgba(5,150,105,0.2);">
                                                    <i class="fa-solid fa-file-pdf"></i> Download
                                                </button>
                                                <?php if ($isSuperAdmin): ?>
                                                    <form method="POST"
                                                        onsubmit="return confirm('Permanently delete this document?');">
                                                        <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                        <button type="submit" name="delete_document" class="action-btn delete-btn"
                                                            style="background:#ef4444; color:white; border:none; border-radius:12px; padding:8px 16px; font-weight:700;">
                                                            <i class="fa-solid fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No documents found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contracts Section -->
            <div class="content-section" id="contracts">
                <div class="section-header">
                    <h2 class="section-title">Contract Management</h2>
                    <button class="add-btn" onclick="showAddContractModal()">
                        <i>+</i> Add Contract
                    </button>
                </div>
                <div class="table-scroll-container">
                    <table class="data-table premium-table">
                        <thead>
                            <tr>
                                <th>Contract Name</th>
                                <th>Case ID</th>
                                <th>Type</th>
                                <th>Risk Level</th>
                                <th>Risk Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($contracts)): ?>
                                <?php foreach ($contracts as $contract): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contract['name']); ?></td>
                                        <td><?php echo htmlspecialchars($contract['case_id']); ?></td>
                                        <td><?php echo htmlspecialchars($contract['contract_type'] ?? 'External'); ?></td>
                                        <td>
                                            <span
                                                class="risk-badge risk-<?php echo strtolower($contract['risk_level'] ?? 'low'); ?>">
                                                <?php echo htmlspecialchars($contract['risk_level'] ?? 'Low'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($contract['risk_score'] ?? 0); ?>/100</td>
                                        <td>
                                            <div class="action-container">
                                                <button class="action-btn view-btn"
                                                    onclick="showContractDetails(<?php echo $contract['id']; ?>)">
                                                    <i class="fa-solid fa-eye"></i> View
                                                </button>
                                                <?php if ($isSuperAdmin): ?>
                                                    <button class="action-btn edit-btn"
                                                        style="background:#f59e0b; color:white; border:none; border-radius:8px; padding:6px 12px;"
                                                        onclick='editLegalRecord(<?php echo json_encode($contract); ?>, "contract")'>
                                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Delete this contract?');">
                                                        <input type="hidden" name="contract_id"
                                                            value="<?php echo $contract['id']; ?>">
                                                        <button type="submit" name="delete_contract" class="action-btn delete-btn"
                                                            style="background:#ef4444; color:white; border:none; border-radius:8px; padding:6px 12px;">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No contracts found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Maintenance Section -->
            <div class="content-section" id="maintenance">
                <div class="section-header">
                    <h2 class="section-title">Maintenance Management</h2>
                    <button class="add-btn" onclick="showAddMaintenanceModal()">
                        <i>+</i> Add Maintenance Log
                    </button>
                </div>

                <!-- Maintenance Table -->
                <div class="table-scroll-container">
                    <table class="data-table premium-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Assigned Staff</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch maintenance logs from database
                            try {
                                $maintenance_query = "SELECT * FROM maintenance_logs ORDER BY maintenance_date DESC";
                                $maintenance_stmt = $db->query($maintenance_query);
                                $maintenance_logs = $maintenance_stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                $maintenance_logs = [];
                            }
                            ?>
                            <?php if (!empty($maintenance_logs)): ?>
                                <?php foreach ($maintenance_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('m/d/Y', strtotime($log['maintenance_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['assigned_staff']); ?></td>
                                        <td><?php echo htmlspecialchars($log['contact_number'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($log['status']); ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-container">
                                                <button class="action-btn view-btn"
                                                    onclick="viewMaintenanceLog(<?php echo $log['id']; ?>)">
                                                    <i class="fa-solid fa-eye"></i> View
                                                </button>
                                                <?php if ($isSuperAdmin): ?>
                                                    <button class="action-btn edit-btn"
                                                        style="background:#f59e0b; color:white; border:none; border-radius:8px; padding:6px 12px;"
                                                        onclick="editMaintenanceLog(<?php echo $log['id']; ?>)">
                                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Delete this maintenance log?');">
                                                        <input type="hidden" name="maintenance_id"
                                                            value="<?php echo $log['id']; ?>">
                                                        <button type="submit" name="delete_maintenance"
                                                            class="action-btn delete-btn"
                                                            style="background:#ef4444; color:white; border:none; border-radius:8px; padding:6px 12px;">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                        <div style="color: #718096; font-style: italic;">
                                            <i class="fa-regular fa-clipboard"
                                                style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                            No maintenance logs found.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Risk Analysis Section -->
            <div class="content-section" id="risk_analysis">
                <div class="section-header">
                    <h2 class="section-title">Risk Analysis Dashboard</h2>
                </div>

                <!-- Stats Cards Grid -->
                <div class="risk-stats-grid">
                    <div class="stat-card total">
                        <div class="stat-icon"><i class="fa-solid fa-file-contract"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Total Contracts</span>
                            <span class="stat-value"><?php echo $totalContracts; ?></span>
                        </div>
                    </div>
                    <div class="stat-card high">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">High Risk</span>
                            <span class="stat-value"><?php echo $riskCounts['High']; ?></span>
                            <span class="stat-meta"><?php echo $highPct; ?>% of total</span>
                        </div>
                    </div>
                    <div class="stat-card medium">
                        <div class="stat-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Medium Risk</span>
                            <span class="stat-value"><?php echo $riskCounts['Medium']; ?></span>
                            <span class="stat-meta"><?php echo $mediumPct; ?>% of total</span>
                        </div>
                    </div>
                    <div class="stat-card low">
                        <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Low Risk</span>
                            <span class="stat-value"><?php echo $riskCounts['Low']; ?></span>
                            <span class="stat-meta"><?php echo $lowPct; ?>% of total</span>
                        </div>
                    </div>
                </div>

                <div class="risk-analysis-layout">
                    <div class="chart-container-wrapper">
                        <h3 class="subsection-title"><i class="fa-solid fa-chart-simple"></i> Risk Distribution</h3>
                        <div class="chart-area" id="chartArea"
                            style="height: 320px; width: 100%; position: relative; background: #ffffff; border-radius: 16px; border: 1px solid #f1f5f9; padding: 20px; display: block; overflow: hidden;">
                            <canvas id="riskDistributionChart" width="600" height="320"
                                style="width: 100%; height: 100%; opacity: 1;"></canvas>
                        </div>
                    </div>

                    <div class="high-risk-list-wrapper">
                        <h3 class="subsection-title">Top High-Risk Contracts</h3>
                        <div id="analysisResults">
                            <?php
                            $highContracts = array_filter($contracts, function ($c) {
                                return (isset($c['risk_level']) && strtolower($c['risk_level']) === 'high');
                            });
                            if (!empty($highContracts)): ?>
                                <div class="high-risk-items">
                                    <?php foreach (array_slice($highContracts, 0, 5) as $hc): ?>
                                        <div class="risk-item"
                                            style="flex-direction: column; align-items: flex-start; gap: 12px; padding: 20px; background: #ffffff; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-radius: 16px; margin-bottom: 20px;">
                                            <div
                                                style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                                                <div class="risk-item-info">
                                                    <span class="risk-item-name"
                                                        style="font-size: 1.05rem; color: #0f172a; font-weight: 700; display: block; text-align: left !important;"><?php echo htmlspecialchars($hc['contract_name'] ?? $hc['name'] ?? 'Untitled'); ?></span>
                                                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                                                        <span
                                                            style="font-size: 0.7rem; color: #64748b; background: #f1f5f9; padding: 3px 10px; border-radius: 6px; font-weight: 600;"><?php echo htmlspecialchars($hc['case_id'] ?? 'N/A'); ?></span>
                                                    </div>
                                                </div>
                                                <div class="risk-item-score">
                                                    <span class="score-badge"
                                                        style="padding: 6px 14px; font-size: 0.85rem; background: #fee2e2; color: #ef4444; font-weight: 800; border: 1px solid #fecaca; border-radius: 8px;"><?php echo htmlspecialchars($hc['risk_score'] ?? 'N/A'); ?>/100</span>
                                                </div>
                                            </div>

                                            <?php if (!empty($hc['analysis_summary'])): ?>
                                                <div class="risk-ai-summary"
                                                    style="background: #f8fafc; padding: 14px; border-radius: 12px; width: 100%; border-left: 4px solid #ef4444; margin-top: 4px; text-align: left !important;">
                                                    <p
                                                        style="margin: 0; font-size: 0.85rem; color: #334155; line-height: 1.6; text-align: left !important;">
                                                        <i class="fa-solid fa-robot" style="color: #6366f1; margin-right: 8px;"></i>
                                                        <strong>AI Result:</strong>
                                                        <?php echo htmlspecialchars($hc['analysis_summary']); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <div style="display: flex; gap: 10px; margin-top: 8px; width: 100%;">
                                                <button class="action-btn analyze-btn" data-type="contract-analyze"
                                                    data-contract='<?php echo htmlspecialchars(json_encode($hc)); ?>'
                                                    style="flex: 1; padding: 8px; font-size: 12px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-weight: 600; border-radius: 8px; cursor: pointer;">
                                                    Full Report
                                                </button>
                                                <button class="action-btn download-btn" data-type="contract-download"
                                                    data-pdf-type="contract"
                                                    data-pdf-content='<?php echo htmlspecialchars(json_encode($hc)); ?>'
                                                    style="flex: 1; background: #059669; color: #fff; border: none; border-radius: 8px; padding: 8px; font-weight: 600; font-size: 12px; cursor: pointer;">
                                                    Download PDF
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fa-solid fa-check-circle"
                                        style="font-size: 48px; margin-bottom: 20px; color: #10b981;"></i>
                                    <p>No high-risk contracts detected. All contracts are within acceptable risk parameters.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- Contract Form Modal -->
    <div id="contractFormModal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeModal('contractFormModal')">&times;</button>
            <div id="contractFormContainer">
                <h3>Add Contract</h3>
                <form method="POST" enctype="multipart/form-data" id="contractFormData">
                    <div class="form-group">
                        <label>Contract Name</label>
                        <input type="text" name="contract_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Case ID</label>
                        <input type="text" name="contract_case" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contract Type</label>
                        <select name="contract_type" class="form-control" required>
                            <option value="Internal">Internal</option>
                            <option value="External" selected>External</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="contract_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Contract File (PDF/DOC)</label>
                        <input type="file" name="contract_file" class="form-control" accept=".pdf,.doc,.docx" required>
                    </div>
                    <div class="form-group">
                        <label>Cover Image (Optional)</label>
                        <input type="file" name="contract_image" class="form-control" accept="image/*">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn"
                            onclick="closeModal('contractFormModal')">Cancel</button>
                        <button type="submit" class="save-btn" name="add_contract">Upload Contract</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Employee Form Modal -->
    <div id="employeeFormModal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeModal('employeeFormModal')">&times;</button>
            <div id="employeeFormContainer">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Document Form Modal -->
    <div id="documentFormModal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeModal('documentFormModal')">&times;</button>
            <div id="documentFormContainer">
                <h3>Upload Document</h3>
                <form method="POST" enctype="multipart/form-data" id="documentFormData">
                    <div class="form-group">
                        <label>Document Name</label>
                        <input type="text" name="doc_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Case ID</label>
                        <input type="text" name="doc_case" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>File</label>
                        <input type="file" name="doc_file" class="form-control" accept=".pdf,.doc,.docx" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn"
                            onclick="closeModal('documentFormModal')">Cancel</button>
                        <button type="submit" class="save-btn" name="add_document">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Maintenance Form Modal -->
    <div id="maintenanceFormModal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeModal('maintenanceFormModal')">&times;</button>
            <div id="maintenanceFormContainer">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Employee Info Modal -->
    <div id="employeeInfoModal" class="modal">
        <div class="modal-content premium-modal">
            <div class="modal-header">
                <button type="button" class="modal-close" onclick="closeModal('employeeInfoModal')">&times;</button>
                <div class="header-content">
                    <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
                    <div>
                        <h2 id="employeeInfoTitle">Employee Profile</h2>
                        <span id="employeeRoleBadge" class="role-badge">Legal Team</span>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="employeeSensitiveData" class="blurred-content">
                    <div class="info-grid">
                        <div class="info-row">
                            <label>Full Name</label>
                            <div id="display_emp_name">-</div>
                        </div>
                        <div class="info-row">
                            <label>Position</label>
                            <div id="display_emp_position">-</div>
                        </div>
                        <div class="info-row">
                            <label>Email Address</label>
                            <div id="display_emp_email">-</div>
                        </div>
                        <div class="info-row">
                            <label>Phone Number</label>
                            <div id="display_emp_phone">-</div>
                        </div>
                    </div>
                </div>
                <div id="employeeRevealOverlay" class="reveal-overlay">
                    <button class="reveal-btn" id="employeeRevealBtn">
                        <i class="fa-solid fa-lock"></i> Enter PIN to Reveal
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeModal('employeeInfoModal')">Close</button>
                    <button type="button" class="save-btn" id="modalDownloadEmpPdf">
                        <i class="fa-solid fa-file-pdf"></i> Download Official Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal Detail Modal -->
    <div id="legalDetailModal" class="modal">
        <div class="modal-content premium-modal">
            <div class="modal-header">
                <button type="button" class="modal-close" onclick="closeModal('legalDetailModal')">&times;</button>
                <div class="header-content">
                    <div class="avatar"><i class="fa-solid fa-file-contract"></i></div>
                    <div>
                        <h2 id="legalDetailTitle">Document Details</h2>
                        <span id="legalDetailCategory" class="role-badge">CATEGORY</span>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="legalDetailContent" class="blurred-content">
                    <div class="info-grid">
                        <div class="info-row">
                            <label>Document Name</label>
                            <div id="legalDetailName">-</div>
                        </div>
                        <div class="info-row">
                            <label id="legalDetailSecondaryLabel">Secondary</label>
                            <div id="legalDetailSecondary">-</div>
                        </div>
                        <div class="info-row">
                            <label>Effective/Expiry Date</label>
                            <div id="legalDetailDate">-</div>
                        </div>
                    </div>
                </div>
                <div id="legalDetailRevealOverlay" class="reveal-overlay">
                    <button class="reveal-btn" onclick="revealLegalDetails()">
                        <i class="fa-solid fa-lock"></i> Enter PIN to View Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal Analysis Modal -->
    <div id="legalAnalysisModal" class="modal">
        <div class="modal-content premium-modal">
            <div class="modal-header">
                <button type="button" class="modal-close" onclick="closeModal('legalAnalysisModal')">&times;</button>
                <div class="header-content">
                    <div class="avatar"><i class="fa-solid fa-robot"></i></div>
                    <div>
                        <h2>AI Risk Analysis Report</h2>
                        <span id="analysisTargetType" class="role-badge">TYPE</span>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="legalAnalysisContent" class="blurred-content">
                    <div class="info-grid">
                        <div class="info-row text-center">
                            <label>Confidence Score</label>
                            <div class="confidence-score">94%</div>
                        </div>
                        <div class="info-row">
                            <h4 id="analysisTargetName">-</h4>
                            <div id="analysisSummaryText" class="summary-box">
                                AI analysis suggests this document is highly compliant with standard legal framework.
                            </div>
                        </div>
                        <div class="info-row">
                            <label>Key Findings</label>
                            <ul id="analysisKeyFindings">
                                <li>No restrictive clauses detected.</li>
                                <li>Liability terms are clearly defined and balanced.</li>
                                <li>Renewal and termination policies are standard.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div id="legalAnalysisRevealOverlay" class="reveal-overlay">
                    <button class="reveal-btn" onclick="revealLegalAnalysis()">
                        <i class="fa-solid fa-lock"></i> Enter PIN to View Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Legal Modal -->
    <div id="editLegalModal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close" onclick="closeModal('editLegalModal')">&times;</button>
            <h2>Edit Legal Record</h2>
            <form method="POST" id="editLegalForm">
                <input type="hidden" name="edit_id" id="edit_legal_id">
                <input type="hidden" name="edit_type" id="edit_legal_type">
                <div class="form-group">
                    <label>Record Name</label>
                    <input type="text" name="edit_name" id="edit_legal_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Case ID Reference</label>
                    <input type="text" name="edit_case_id" id="edit_legal_case_id" class="form-control" required>
                </div>
                <div id="dynamic_edit_fields"></div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal('editLegalModal')">Cancel</button>
                    <button type="submit" name="update_legal_record" class="save-btn">Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>Enter Security PIN</h3>
            <form id="passwordForm">
                <div class="pin-input">
                    <input type="password" maxlength="1" class="pin-digit">
                    <input type="password" maxlength="1" class="pin-digit">
                    <input type="password" maxlength="1" class="pin-digit">
                    <input type="password" maxlength="1" class="pin-digit">
                </div>
                <div class="error-message" id="pwdError" style="display: none;">Invalid PIN</div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="pwdCancel">Cancel</button>
                    <button type="submit" class="save-btn">Verify</button>
                </div>
            </form>
        </div>
    </div>



    <script>
        const APP_CORRECT_PIN = '<?php echo $archivePin; ?>';

        // Global variables
        let riskChartRef = null;

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize tabs
            initTabs();

            // Initialize chart
            initRiskChart();

            // Initialize login functionality
            initLogin();

            // Initialize event listeners
            initEventListeners();

            // Initialize tab filtering
            initTabFiltering();
        });

        // Tab functionality
        function initTabs() {
            const tabs = document.querySelectorAll('.nav-tab');
            const sections = document.querySelectorAll('.content-section');

            tabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');

                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Show target section
                    sections.forEach(section => {
                        section.style.display = 'none';
                        if (section.id === targetId) {
                            section.style.display = 'block';
                        }
                    });

                    // Re-initialize chart if target is risk analysis
                    if (targetId === 'risk_analysis') {
                        setTimeout(initRiskChart, 50);
                    }
                });
            });
        }

        // Risk Chart initialization
        function initRiskChart() {
            const canvas = document.getElementById('riskDistributionChart');
            if (!canvas || typeof Chart === 'undefined') return;

            const chartData = [
                <?php echo (int) ($riskCounts['High'] ?? 0); ?>,
                <?php echo (int) ($riskCounts['Medium'] ?? 0); ?>,
                <?php echo (int) ($riskCounts['Low'] ?? 0); ?>
            ];

            const ctx = canvas.getContext('2d');
            if (riskChartRef) riskChartRef.destroy();

            riskChartRef = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['High Risk', 'Medium Risk', 'Low Risk'],
                    datasets: [{
                        label: 'Contracts',
                        data: chartData,
                        backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                        borderColor: ['#ffffff', '#ffffff', '#ffffff'],
                        borderWidth: 2,
                        borderRadius: 10,
                        barPercentage: 0.55
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#94a3b8', font: { size: 11 } },
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#475569', font: { weight: 'bold', size: 12 } }
                        }
                    }
                }
            });
        }

        // Login functionality
        function initLogin() {
            const pinInputs = document.querySelectorAll('.pin-digit');
            const loginBtn = document.getElementById('loginBtn');
            const loginScreen = document.getElementById('loginScreen');
            const dashboard = document.getElementById('dashboard');
            const errorMessage = document.getElementById('errorMessage');

            if (!pinInputs.length) return;

            // Enable inputs
            pinInputs.forEach(input => input.disabled = false);
            if (loginBtn) loginBtn.disabled = false;

            // Focus first input
            pinInputs[0].focus();

            // Input handling
            pinInputs.forEach((input, index) => {
                input.addEventListener('input', function () {
                    if (this.value && index < pinInputs.length - 1) {
                        pinInputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        pinInputs[index - 1].focus();
                    }
                });
            });

            // Login button handler
            if (loginBtn) {
                loginBtn.addEventListener('click', function () {
                    const pin = Array.from(pinInputs).map(input => input.value).join('');

                    if (pin === APP_CORRECT_PIN) {
                        loginScreen.style.display = 'none';
                        dashboard.style.display = 'block';
                    } else {
                        errorMessage.style.display = 'block';
                        pinInputs.forEach(input => input.value = '');
                        pinInputs[0].focus();
                    }
                });
            }
        }

        // Event listeners
        function initEventListeners() {
            // Modal close handlers
            document.querySelectorAll('.modal-close, .cancel-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const modal = this.closest('.modal');
                    if (modal) modal.style.display = 'none';
                });
            });

            // Back button
            const backBtn = document.getElementById('backDashboardBtn');
            if (backBtn) {
                backBtn.addEventListener('click', function (e) {
                    if (!this.href) {
                        e.preventDefault();
                        window.location.replace('../Modules/dashboard.php');
                    }
                });
            }

            // Add employee button
            const addEmployeeBtn = document.getElementById('addEmployeeBtn');
            if (addEmployeeBtn) {
                addEmployeeBtn.addEventListener('click', function () {
                    showAddEmployeeModal();
                });
            }
        }

        // Tab filtering
        function initTabFiltering() {
            // Initialize internal tab filtering
            const firstInternalTab = document.querySelector('.legal-tab-btn.active');
            if (firstInternalTab) {
                const match = firstInternalTab.getAttribute('onclick').match(/, '([^']+)'/);
                if (match && match[1]) {
                    filterLegalDocs(firstInternalTab, match[1]);
                }
            }

            // Initialize external tab filtering
            const firstExternalTab = document.querySelector('.ext-tab-btn.active');
            if (firstExternalTab) {
                const match = firstExternalTab.getAttribute('onclick').match(/, '([^']+)'/);
                if (match && match[1]) {
                    filterExternalDocs(firstExternalTab, match[1]);
                }
            }
        }

        // Tab filtering functions
        function filterLegalDocs(btn, category) {
            const container = btn.closest('.internal-tabs-container');
            const buttons = container.querySelectorAll('.legal-tab-btn');

            buttons.forEach(b => {
                b.classList.remove('active');
                b.style.background = 'white';
                b.style.color = '#64748b';
            });

            btn.classList.add('active');
            btn.style.background = '#3b82f6';
            btn.style.color = 'white';

            const rows = document.querySelectorAll('.internal-doc-row');
            rows.forEach(row => {
                row.style.display = row.dataset.category === category ? '' : 'none';
            });
        }

        function filterExternalDocs(btn, category) {
            const container = btn.closest('.external-tabs-container');
            const buttons = container.querySelectorAll('.ext-tab-btn');

            buttons.forEach(b => {
                b.classList.remove('active');
                b.style.background = 'white';
                b.style.color = '#64748b';
            });

            btn.classList.add('active');
            btn.style.background = '#10b981';
            btn.style.color = 'white';

            const rows = document.querySelectorAll('.external-doc-row');
            rows.forEach(row => {
                row.style.display = row.dataset.category === category ? '' : 'none';
            });
        }

        // Modal functions
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = 'none';
        }

        function showAddEmployeeModal() {
            const form = document.getElementById('employeeForm');
            const container = document.getElementById('employeeFormContainer');

            // Reset form
            form.reset();
            const saveBtn = document.getElementById('saveEmployeeBtn');
            saveBtn.innerText = 'Save Employee';
            saveBtn.name = 'save_employee';

            // Remove hidden ID if exists
            const idInput = document.querySelector('input[name="employee_id"]');
            if (idInput) idInput.remove();

            // Move form to modal if not already there
            if (form && container && !container.contains(form)) {
                container.appendChild(form);
                form.style.display = 'block';
            }

            showModal('employeeFormModal');
        }

        function showAddDocumentModal() {
            showModal('documentFormModal');
        }

        function showAddContractModal() {
            showModal('contractFormModal');
        }

        function showAddMaintenanceModal() {
            // Similar to showAddEmployeeModal, but for maintenance
            showModal('maintenanceFormModal');
        }

        // Edit employee function
        window.editEmployee = function (emp) {
            const form = document.getElementById('employeeForm');
            const container = document.getElementById('employeeFormContainer');

            // Populate form fields
            document.getElementById('employeeName').value = emp.name || '';
            document.getElementById('employeePosition').value = emp.position || '';
            document.getElementById('employeeEmail').value = emp.email || '';
            document.getElementById('employeePhone').value = emp.phone || '';

            // Ensure hidden ID field exists
            let idInput = document.querySelector('input[name="employee_id"]');
            if (!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'employee_id';
                document.getElementById('employeeFormData').appendChild(idInput);
            }
            idInput.value = emp.id;

            // Change submit button text
            const saveBtn = document.getElementById('saveEmployeeBtn');
            saveBtn.innerText = 'Update Employee';
            saveBtn.name = 'save_employee';

            // Move form to modal if not already there
            if (form && container && !container.contains(form)) {
                container.appendChild(form);
                form.style.display = 'block';
            }

            showModal('employeeFormModal');
        };

        // Edit legal record function - FIXED VERSION
        window.editLegalRecord = function (data, type) {
            const modal = document.getElementById('editLegalModal');
            if (!modal) {
                console.error('Edit modal not found');
                return;
            }

            document.getElementById('edit_legal_id').value = data.id || '';
            document.getElementById('edit_legal_type').value = type || '';
            document.getElementById('edit_legal_name').value = data.name || data.contract_name || '';
            document.getElementById('edit_legal_case_id').value = data.case_id || '';

            const dynamicFields = document.getElementById('dynamic_edit_fields');
            if (dynamicFields) {
                dynamicFields.innerHTML = '';

                if (type === 'contract') {
                    dynamicFields.innerHTML = `
                        <div class="form-group">
                            <label>Resource Description</label>
                            <textarea name="edit_description" class="form-control" rows="3">${data.description || ''}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Legal Classification</label>
                            <select name="edit_contract_type" class="form-control">
                                <option value="Internal" ${(data.contract_type || '') === 'Internal' ? 'selected' : ''}>Internal (Policies/SOP)</option>
                                <option value="External" ${(data.contract_type || '') === 'External' ? 'selected' : ''}>External (Agreements/NDA)</option>
                            </select>
                        </div>
                    `;
                }
            }

            showModal('editLegalModal');
        };

        // Legal details functions
        window.showLegalDetails = function (name, secondary, date, type, secondaryLabel) {
            document.getElementById('legalDetailName').textContent = name;
            document.getElementById('legalDetailCategory').textContent = type;
            document.getElementById('legalDetailSecondary').textContent = secondary;
            document.getElementById('legalDetailDate').textContent = date;
            document.getElementById('legalDetailSecondaryLabel').textContent = secondaryLabel;

            // Reset Blur State
            document.getElementById('legalDetailContent').classList.add('blurred-content');
            document.getElementById('legalDetailRevealOverlay').style.display = 'flex';

            showModal('legalDetailModal');
        };

        function revealLegalDetails() {
            withPasswordGate(() => {
                document.getElementById('legalDetailContent').classList.remove('blurred-content');
                document.getElementById('legalDetailRevealOverlay').style.display = 'none';
            });
        }

        window.showLegalAnalysis = function (name, type) {
            document.getElementById('analysisTargetName').textContent = name;
            document.getElementById('analysisTargetType').textContent = type;

            const summaryText = document.getElementById('analysisSummaryText');
            const findingsList = document.getElementById('analysisKeyFindings');

            if (type === 'Internal') {
                summaryText.style.background = '#f0fdf4';
                summaryText.style.borderLeftColor = '#22c55e';
                summaryText.querySelector('p').textContent = 'Internal policy review: High alignment with organizational standards and compliance requirements.';
                findingsList.innerHTML = `
                    <li>Clear definitions of employee responsibilities.</li>
                    <li>Compliance with local labor laws verified.</li>
                    <li>Update procedures are well-documented.</li>
                `;
            } else {
                summaryText.style.background = '#fffbeb';
                summaryText.style.borderLeftColor = '#f59e0b';
                summaryText.querySelector('p').textContent = 'External agreement review: Moderate liability exposure identified. Recommendation: review section 4.2 for clarification.';
                findingsList.innerHTML = `
                    <li>Standard vendor obligations met.</li>
                    <li>Liability cap slightly exceeds industry standard.</li>
                    <li>Indemnification clauses are broad.</li>
                `;
            }

            // Reset Blur State
            document.getElementById('legalAnalysisContent').classList.add('blurred-content');
            document.getElementById('legalAnalysisRevealOverlay').style.display = 'flex';

            showModal('legalAnalysisModal');
        };

        function revealLegalAnalysis() {
            withPasswordGate(() => {
                document.getElementById('legalAnalysisContent').classList.remove('blurred-content');
                document.getElementById('legalAnalysisRevealOverlay').style.display = 'none';
            });
        }

        // Password gate functionality
        window.withPasswordGate = function (callback) {
            const modal = document.getElementById('passwordModal');
            const form = document.getElementById('passwordForm');
            const error = document.getElementById('pwdError');
            const cancel = document.getElementById('pwdCancel');
            const digits = modal.querySelectorAll('.pin-digit');

            if (!modal) {
                callback();
                return;
            }

            // Reset modal
            digits.forEach(d => d.value = '');
            if (error) error.style.display = 'none';
            modal.style.display = 'flex';
            digits[0].focus();

            // Focus management
            digits.forEach((digit, idx) => {
                digit.oninput = (e) => {
                    if (digit.value && idx < digits.length - 1) digits[idx + 1].focus();
                };
                digit.onkeydown = (e) => {
                    if (e.key === 'Backspace' && !digit.value && idx > 0) digits[idx - 1].focus();
                };
            });

            // Handle submission
            form.onsubmit = (e) => {
                e.preventDefault();
                const pin = Array.from(digits).map(d => d.value).join('');
                if (pin === APP_CORRECT_PIN) {
                    modal.style.display = 'none';
                    callback();
                } else {
                    if (error) error.style.display = 'block';
                    digits.forEach(d => d.value = '');
                    digits[0].focus();
                }
            };

            cancel.onclick = () => {
                modal.style.display = 'none';
            };
        };

        // PDF generation functions
        function generateInternalPDF() {
            withPasswordGate(() => {
                const internalDocs = <?php
                $internalDocs = array_filter($contracts, function ($c) {
                    return (isset($c['contract_type']) && $c['contract_type'] === 'Internal');
                });
                echo json_encode(array_values($internalDocs));
                ?>;

                const contentHTML = `
                    <div style="font-family: Arial, sans-serif; padding: 20px;">
                        <h1 style="color: #8b5cf6; text-align: center; margin-bottom: 30px;">Internal Documents & Policies Report</h1>
                        <p style="text-align: center; color: #666; margin-bottom: 30px;">Generated on ${new Date().toLocaleDateString()}</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                            <thead>
                                <tr style="background: #8b5cf6; color: white;">
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Policy Name</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Case ID</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${internalDocs.map(doc => `
                                    <tr>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${doc.name}</td>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${doc.case_id}</td>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${new Date(doc.created_at).toLocaleDateString()}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; color: #666; font-size: 12px;">
                            <p>Confidential Internal Documents Report</p>
                            <p>Total Documents: ${internalDocs.length}</p>
                        </div>
                    </div>
                `;

                generatePDFFromData('Internal Documents Report', contentHTML, 'Internal_Documents_Report.pdf');
            });
        }

        function generateExternalPDF() {
            withPasswordGate(() => {
                const externalDocs = <?php
                $externalDocs = array_filter($contracts, function ($c) {
                    return (isset($c['contract_type']) && $c['contract_type'] === 'External');
                });
                echo json_encode(array_values($externalDocs));
                ?>;

                const contentHTML = `
                    <div style="font-family: Arial, sans-serif; padding: 20px;">
                        <h1 style="color: #10b981; text-align: center; margin-bottom: 30px;">External Agreements Report</h1>
                        <p style="text-align: center; color: #666; margin-bottom: 30px;">Generated on ${new Date().toLocaleDateString()}</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                            <thead>
                                <tr style="background: #10b981; color: white;">
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Agreement Name</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Case ID</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Expiry Date</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${externalDocs.map(doc => `
                                    <tr>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${doc.name}</td>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${doc.case_id}</td>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${new Date(doc.created_at).setFullYear(new Date(doc.created_at).getFullYear() + 1).toLocaleDateString()}</td>
                                        <td style="border: 1px solid #ddd; padding: 10px;">${new Date(doc.created_at).toLocaleDateString()}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; color: #666; font-size: 12px;">
                            <p>External Agreements & Contracts Report</p>
                            <p>Total Agreements: ${externalDocs.length}</p>
                        </div>
                    </div>
                `;

                generatePDFFromData('External Agreements Report', contentHTML, 'External_Agreements_Report.pdf');
            });
        }

        // PDF generation utility
        function generatePDFFromData(title, contentHTML, filename) {
            const element = document.createElement('div');
            element.style.padding = '20px';
            element.style.fontFamily = 'Arial, sans-serif';
            element.innerHTML = `
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
                    <h1 style="color: #2c3e50; margin: 0;">Legal Management System</h1>
                    <h2 style="color: #3498db; margin: 5px 0 0;">${title}</h2>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Generated on: ${new Date().toLocaleString()}</p>
                </div>
                <div style="color: #334155; line-height: 1.6;">
                    ${contentHTML}
                </div>
                <div style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 0.8rem; text-align: center; color: #94a3b8;">
                    © ${new Date().getFullYear()} Hotel & Restaurant Legal Management System. All rights reserved.
                </div>
            `;

            const opt = {
                margin: 15,
                filename: filename || 'Legal_Document.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save();
        }

        // Loading animation with safety timeout
        (function () {
            let loaderHidden = false;
            const hideLoader = function () {
                if (loaderHidden) return;
                loaderHidden = true;
                const loader = document.getElementById('loadingOverlay');
                if (loader) {
                    loader.style.opacity = '0';
                    setTimeout(() => {
                        loader.style.display = 'none';
                    }, 800);
                }
                document.body.classList.add('loaded');
            };

            // Hide after all resources load with a slight delay for better UX
            window.addEventListener('load', () => {
                setTimeout(hideLoader, 1500);
            });

            // Safety timeout: auto-hide after 4 seconds even if resources are slow
            setTimeout(hideLoader, 4000);
        })();
    </script>
</body>

</html>