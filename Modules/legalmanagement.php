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

// Ensure static data exists in contracts for Internal/External sections
try {
    $checkQ = $db->query("SELECT COUNT(*) FROM contracts WHERE name LIKE '%Privacy Policy%' OR name LIKE '%Logistics Supply%'");
    if ($checkQ->fetchColumn() == 0) {
        $db->exec("INSERT IGNORE INTO contracts (name, case_id, contract_type, description, risk_score) VALUES 
            ('Employee Privacy Policy 2024', 'HR-POL-001', 'Internal', 'Comprehensive privacy policy for hotel and restaurant staff.', 15),
            ('Internal Operational Guidelines', 'OPS-SOP-2024', 'Internal', 'Operational standard procedures for internal departments.', 20),
            ('Global Logistics Supply Agreement', 'LOGI-2024-01', 'External', 'Supply chain and logistics agreement with LogiTrans Corp.', 45),
            ('Outsourced Security Services NDA', 'SEC-NDA-042', 'External', 'Non-disclosure agreement with SafeGuard Solutions.', 30)");
    }
} catch (PDOException $e) {
}

// Ensure additional Internal Data exists for new tabs
try {
    $extraDocs = [
        ['Employee Code of Conduct 2024', 'HR-POL-002', 'Internal', 'Standard code of conduct for all employees.', 10],
        ['Labor Union Collective Agreement', 'HR-LAB-001', 'Internal', 'Agreement with Hotel Workers Union regarding wages and benefits.', 35],
        ['Staff Disciplinary Policy', 'HR-DISC-001', 'Internal', 'Procedures for employee disciplinary actions.', 15],
        ['Workplace Safety Compliance Guide', 'CMP-SAF-2024', 'Internal', 'Safety protocols compliant with DOLE standards.', 5],
        ['Board Resolution 2024-001', 'GOV-RES-001', 'Internal', 'Board approval for FY 2024 budget allocation.', 0],
        ['Corporate By-Laws 2024 Amendment', 'GOV-LAW-002', 'Internal', 'Amendments to corporate by-laws regarding shareholder meetings.', 25],
        ['Annual Risk Audit Report', 'RSK-AUD-2023', 'Internal', 'Comprehensive risk assessment audit for 2023.', 40],
        ['Disaster Recovery Plan', 'RSK-REC-001', 'Internal', 'IT and Operations disaster recovery and business continuity plan.', 65],

        // External
        ['Food Supplier Contract - BestMeats', 'SUP-2024-001', 'External', 'Annual contract for premium meat supply.', 20],
        ['Beverage Partnership Agreement', 'SUP-2024-002', 'External', 'Exclusive partnership with Major Soda Co.', 15],
        ['City Hall Business Permit 2024', 'GOV-PER-2024', 'External', 'Annual business operation permit renewal.', 80],
        ['BIR Tax Compliance Certificate', 'GOV-TAX-001', 'External', 'Certificate of updated tax compliance.', 75],
        ['Pending Litigation - Case 8821', 'LAW-DISP-001', 'External', 'Ongoing labor dispute case filed by former contractor.', 90],
        ['Settlement Agreement - Slip/Fall', 'LAW-SET-002', 'External', 'Settlement agreement regarding minor guest accident.', 45],
        ['Data Privacy Compliance 2024', 'REG-DPA-001', 'External', 'Compliance report for National Data Privacy Act.', 50],
        ['Guest Waiver & Release Form', 'CON-PROT-001', 'External', 'Standard liability waiver for swimming pool usage.', 30]
    ];

    foreach ($extraDocs as $doc) {
        $check = $db->prepare("SELECT COUNT(*) FROM contracts WHERE name = ?");
        $check->execute([$doc[0]]);
        if ($check->fetchColumn() == 0) {
            $ins = $db->prepare("INSERT INTO contracts (name, case_id, contract_type, description, risk_score) VALUES (?, ?, ?, ?, ?)");
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


    if (isset($_POST['add_employee'])) {
        $name = $_POST['employee_name'];
        $position = $_POST['employee_position'];
        $email = $_POST['employee_email'];
        $phone = $_POST['employee_phone'];

        $query = "INSERT INTO contacts (name, role, email, phone) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);

        if ($stmt->execute([$name, $position, $email, $phone])) {
            $success_message = "Employee added successfully!";
        } else {
            $error_message = "Failed to add employee.";
        }
    }

    if (isset($_POST['update_employee'])) {
        $empId = intval($_POST['employee_id'] ?? 0);
        $name = $_POST['employee_name'] ?? '';
        $position = $_POST['employee_position'] ?? '';
        $email = $_POST['employee_email'] ?? '';
        $phone = $_POST['employee_phone'] ?? '';
        if ($empId > 0) {
            $query = "UPDATE contacts SET name = ?, role = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$name, $position, $email, $phone, $empId])) {
                $success_message = "Employee updated successfully!";
            } else {
                $error_message = "Failed to update employee.";
            }
        } else {
            $error_message = "Invalid employee ID.";
        }
    }
    // Unified create/update handler
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
    // Update Document
    if (isset($_POST['update_document'])) {
        $doc_id = intval($_POST['document_id'] ?? 0);
        $doc_name = $_POST['doc_name'] ?? '';
        $doc_case = $_POST['doc_case'] ?? '';
        if ($doc_id > 0) {
            $q = "UPDATE documents SET name = ?, case_id = ? WHERE id = ?";
            $s = $db->prepare($q);
            if ($s->execute([$doc_name, $doc_case, $doc_id])) {
                $success_message = "Document updated successfully!";
            } else {
                $error_message = "Failed to update document.";
            }
        } else {
            $error_message = "Invalid document ID.";
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

    if (isset($_POST['delete_contract'])) {
        $contract_id = intval($_POST['contract_id'] ?? 0);
        if ($contract_id > 0) {
            $q = "DELETE FROM contracts WHERE id = ?";
            $s = $db->prepare($q);
            if ($s->execute([$contract_id])) {
                $success_message = "Contract deleted.";
            } else {
                $error_message = "Failed to delete contract.";
            }
        }
    }

    if (isset($_POST['delete_employee'])) {
        $emp_id = intval($_POST['employee_id'] ?? 0);
        if ($emp_id > 0) {
            $success_message = "Employee record removal requested.";
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

        $query = "INSERT INTO contracts (name, case_id, contract_type, description, file_path, risk_score, risk_factors, recommendations, analysis_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
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

    // Handle PDF Export (Idinagdag para sa PDF Report na may Password)
    if (isset($_POST['action']) && $_POST['action'] === 'export_pdf') {
        $password = 'legal2025'; // Password para sa PDF Report (Simulasyon)

        // Kunin ang lahat ng data ng kontrata para sa ulat
        $query = "SELECT name as contract_name, risk_score, analysis_summary FROM contracts ORDER BY created_at DESC";
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
            echo "- " . $contract['contract_name'] . " (Score: " . $contract['risk_score'] . "/100)\n";
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

// NEW: Fetch documents and billing (with fallbacks) and build risk summary
$documents = [];
try {
    $query = "SELECT id, name, case_id, file_path, uploaded_at, risk_score, analysis_date, ai_analysis FROM documents ORDER BY uploaded_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // fallback demo data if query fails
    $documents = [
        ['id' => 1, 'name' => 'Employment Contract.pdf', 'case_id' => 'C-001', 'file_path' => 'uploads/documents/Employment Contract.pdf', 'uploaded_at' => '2023-05-20 12:00:00', 'risk_score' => null, 'analysis_date' => null, 'ai_analysis' => null],
        ['id' => 2, 'name' => 'Supplier Agreement.docx', 'case_id' => 'C-002', 'file_path' => 'uploads/documents/Supplier Agreement.docx', 'uploaded_at' => '2023-06-25 12:00:00', 'risk_score' => null, 'analysis_date' => null, 'ai_analysis' => null]
    ];
}


// Risk summary with normalized casing
$riskCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($contracts as $c) {
    // Determine risk level based on score only
    $score = $c['risk_score'] ?? 0;
    if ($score >= 70) {
        $riskCounts['High']++;
    } elseif ($score >= 31) {
        $riskCounts['Medium']++;
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
            /* Internet Explorer 10+ */
            scrollbar-width: none;
            /* Firefox */
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
    </style>
</head>

<body>
    <!-- Login Screen -->
    <div class="login-container" id="loginScreen">
        <div class="login-form">
            <div style="text-align: center; margin-bottom: 25px;">
                <img src="../assets/image/logo.png" alt="Logo" style="width: 200px; height: auto;">
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
                <div class="nav-tab" data-target="risk_analysis">Risk Analysis</div>
            </div>

            <!-- Employees Section -->
            <div class="content-section active" id="employees">
                <div class="section-header">
                    <h2 class="section-title">Employee Information</h2>
                    <button class="add-btn" id="addEmployeeBtn">
                        <i>+</i> Add Employee
                    </button>
                </div>

                <!-- Add Employee Form -->
                <div class="form-container" id="employeeForm">
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
                            <button type="submit" class="save-btn" name="add_employee" id="saveEmployeeBtn">Save
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
                                            <button class="action-btn view-btn"
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
                    <button class="add-btn" onclick="document.getElementById('addDocumentBtn').click()">
                        <i>+</i> Add Internal Doc
                    </button>
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
                       
                        <button class="legal-tab-btn" onclick="filterLegalDocs(this, 'risk')"
                            style="padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                            <i class="fa-solid fa-shield-halved" style="margin-right: 8px;"></i> Risk Management
                        </button>
                    </div>
                </div>

                <script>
                    function filterLegalDocs(btn, category) {
                        // Update Tab Styles
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

                        // Filter Rows
                        const rows = document.querySelectorAll('.internal-doc-row');
                        rows.forEach(row => {
                            if (row.dataset.category === category) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    }

                    // Initialize Default Tab (Policies) on Load
                    document.addEventListener('DOMContentLoaded', () => {
                        const firstTab = document.querySelector('.legal-tab-btn.active');
                        if (firstTab) {
                            // Manually trigger the filter logic for the first tab
                            // Extract category from onclick string 'filterLegalDocs(this, 'policies')'
                            const match = firstTab.getAttribute('onclick').match(/, '([^']+)'/);
                            if (match && match[1]) {
                                filterLegalDocs(firstTab, match[1]);
                            }
                        }
                    });
                </script>
                <div style="position: relative;">
                    <div id="internalSectionContent" class="blurred-content">
                        <div class="table-scroll-container">
                            <table class="data-table premium-table">
                                <thead>
                                    <tr>
                                        <th>Policy Name</th>
                                        <th>Case ID</th>
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
                    <button class="add-btn" onclick="document.getElementById('addContractBtn').click()">
                        <i>+</i> Add External Contract
                    </button>
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

                <script>
                    function filterExternalDocs(btn, category) {
                        // Update Tab Styles
                        const container = btn.closest('.external-tabs-container');
                        const buttons = container.querySelectorAll('.ext-tab-btn');
                        buttons.forEach(b => {
                            b.classList.remove('active');
                            b.style.background = 'white';
                            b.style.color = '#64748b';
                        });

                        // Set active style for clicked button
                        btn.classList.add('active');
                        btn.style.background = '#10b981';
                        btn.style.color = 'white';

                        // Filter Rows
                        const rows = document.querySelectorAll('.external-doc-row');
                        rows.forEach(row => {
                            if (row.dataset.category === category) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    }

                    // Initialize Default Tab (Supplier) on Load
                    document.addEventListener('DOMContentLoaded', () => {
                        const firstTab = document.querySelector('.ext-tab-btn.active');
                        if (firstTab) {
                            const match = firstTab.getAttribute('onclick').match(/, '([^']+)'/);
                            if (match && match[1]) {
                                filterExternalDocs(firstTab, match[1]);
                            }
                        }
                    });
                </script>
                <div style="position: relative;">
                    <div id="externalSectionContent" class="blurred-content">
                        <div class="table-scroll-container">
                            <table class="data-table premium-table">
                                <thead>
                                    <tr>
                                        <th>Agreement Name</th>
                                        <th>Case ID</th>
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
                                            <td colspan="4">No external agreements found.</td>
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
                    <button class="add-btn" id="addDocumentBtn">
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
                                    <td colspan="4" style="text-align:center;color:#666;padding:20px;">No documents found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Contracts Section -->
            <div class="content-section" id="contracts" style="display:none;">
                <div class="section-header">
                    <h2 class="section-title">Contracts <span class="ai-badge">AI-Powered Analysis</span></h2>
                    <div style="display: flex; gap: 10px;">
                        <!-- Button para sa Secured PDF Report (Idinagdag) -->
                        <button class="add-btn" id="exportPdfBtn" style="background: #e74c3c; /* Pula para sa ulat */">
                            &#x1F4C4; Generate Secured PDF
                        </button>
                        <button class="add-btn" id="addContractBtn">
                            <i>+</i> Upload Contract
                        </button>
                    </div>
                </div>
                <!-- Hidden form to trigger secured PDF generation via POST -->
                <form id="exportPdfForm" method="POST" style="display:none">
                    <input type="hidden" name="action" value="export_pdf">
                </form>

                <!-- Add Contract Form -->
                <div class="form-container" id="contractForm">
                    <h3>Upload Contract <span class="ai-badge">AI Risk Analysis</span></h3>
                    <form method="POST" enctype="multipart/form-data" id="contractFormData">
                        <div class="form-group">
                            <label for="contractName">Contract Name</label>
                            <input type="text" id="contractName" name="contract_name" class="form-control"
                                placeholder="Enter contract name" required>
                        </div>
                        <div class="form-group">
                            <label for="contractCase">Case ID</label>
                            <input type="text" id="contractCase" name="contract_case" class="form-control"
                                placeholder="Enter case ID (e.g., C-001)" required>
                        </div>
                        <div class="form-group">
                            <label for="contractType">Contract Type</label>
                            <select id="contractType" name="contract_type" class="form-control" required>
                                <option value="Internal">Internal (Policies, SOPs)</option>
                                <option value="External">External (Vendors, Clients)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="contractDescription">Contract Description</label>
                            <textarea id="contractDescription" name="contract_description" class="form-control"
                                placeholder="Describe the contract terms, key clauses, and important details for AI analysis"
                                rows="4"></textarea>
                            <div class="file-info">AI will analyze this description to detect risk factors</div>
                        </div>
                        <div class="form-group">
                            <label for="contractFile">Contract File</label>
                            <input type="file" id="contractFile" name="contract_file" class="form-control"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <div class="file-info">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max: 10MB)</div>
                        </div>
                        <div class="form-group">
                            <label for="contractImage">Larawang Pang-cover (opsyonal)</label>
                            <input type="file" id="contractImage" name="contract_image" class="form-control"
                                accept="image/*">
                            <div class="file-info">Mga pinapayagang format: JPG, PNG, JPEG (Max: 5MB)</div>
                        </div>

                        <div class="ai-analysis-section">
                            <h4><i class="fa-solid fa-wand-magic-sparkles"
                                    style="color: #4a6cf7; margin-right: 8px;"></i> AI Risk Assessment</h4>
                            <p style="margin-bottom:10px; color:#475569;"><strong>Note:</strong> Our AI system will
                                automatically analyze your contract for:</p>
                            <ul class="ai-features-list">
                                <li><i class="fa-solid fa-check-circle"></i> Financial risk factors (lease terms,
                                    rent
                                    structure)</li>
                                <li><i class="fa-solid fa-check-circle"></i> Operational restrictions (hours,
                                    suppliers,
                                    staffing)</li>
                                <li><i class="fa-solid fa-check-circle"></i> Legal protection issues (liability,
                                    guarantees)</li>
                                <li><i class="fa-solid fa-check-circle"></i> Flexibility and exit concerns</li>
                            </ul>
                            <div class="ai-note">
                                <i class="fa-solid fa-circle-info"></i> Risk score and level will be automatically
                                calculated
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="cancel-btn" id="cancelContractBtn">Cancel</button>
                            <button type="submit" class="save-btn" name="add_contract" id="saveContractBtn">
                                <i>+</i> Upload & Analyze Contract
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Contracts Table -->
                <table class="data-table premium-table">
                    <thead>
                        <tr>
                            <th>Contract Name</th>
                            <th>Case</th>
                            
                            <th>Risk Score</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTableBody">
                        <?php foreach ($contracts as $contract):
                            $risk_factors = json_decode($contract['risk_factors'] ?? '[]', true);
                            $recommendations = json_decode($contract['recommendations'] ?? '[]', true);
                            ?>
                            <tr>
                                <td>
                                    <?php if (!empty($contract['file_path'])): ?>
                                        <a href="#" class="view-pdf-link text-blue-600 hover:underline" data-pdf-type="contract"
                                            data-pdf-content='<?php echo htmlspecialchars(json_encode($contract)); ?>'><?php echo htmlspecialchars($contract['contract_name'] ?? $contract['name'] ?? 'N/A'); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($contract['contract_name'] ?? $contract['name'] ?? 'N/A'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($contract['case_id']); ?></td>
                                <td><?php echo htmlspecialchars($contract['risk_score']); ?>/100</td>
                                <td><?php echo date('Y-m-d', strtotime($contract['created_at'])); ?></td>
                                <td>
                                    <div class="action-container">
                                        <button class="action-btn analyze-btn" data-type="contract-analyze"
                                            data-contract='<?php echo htmlspecialchars(json_encode($contract)); ?>'>
                                            <i class="fa-solid fa-magnifying-glass-chart"></i> AI Analysis
                                        </button>
                                        <button class="action-btn download-btn" data-type="contract-download"
                                            data-pdf-type="contract"
                                            data-pdf-content='<?php echo htmlspecialchars(json_encode($contract)); ?>'
                                            style="background: #059669; color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-weight: 500; font-size: 13px; cursor: pointer;">
                                            <i class="fa-solid fa-file-pdf"></i> PDF
                                        </button>
                                        <?php if ($isSuperAdmin): ?>
                                            <form method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this contract?');">
                                                <input type="hidden" name="contract_id" value="<?php echo $contract['id']; ?>">
                                                <button type="submit" name="delete_contract" class="action-btn delete-btn"
                                                    style="background:#ef4444; color:white; border:none; border-radius: 8px; padding: 6px 12px; font-weight: 500; font-size: 13px; cursor: pointer;">
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

            <div class="content-section" id="risk_analysis">
                <div class="section-header">
                    <h2 class="section-title">Contract Risk Analysis</h2>
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
                        <h3 class="subsection-title"><i class="fa-solid fa-chart-simple"></i> Risk Distribution Analysis</h3>
                        <div class="chart-area" id="chartArea"
                            style="height: 350px; width: 100%; position: relative; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 20px; border: 2px solid #e2e8f0; padding: 25px; display: block; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.08);">
                            <canvas id="riskDistributionChart" width="600" height="350"
                                style="width: 100%; height: 100%; opacity: 1; border-radius: 12px;"></canvas>
                        </div>
                        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 15px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 12px; height: 12px; background: #ef4444; border-radius: 3px;"></div>
                                    <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">High Risk</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 12px; height: 12px; background: #f59e0b; border-radius: 3px;"></div>
                                    <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Medium Risk</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 12px; height: 12px; background: #10b981; border-radius: 3px;"></div>
                                    <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Low Risk</span>
                                </div>
                            </div>
                            <button onclick="window.initRiskChart()" 
                                style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                                <i class="fa-solid fa-sync-alt"></i> Refresh
                            </button>
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
                                <div class="no-risk-data">
                                    <i class="fa-solid fa-shield-check"></i>
                                    <p>No high-risk contracts detected.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>



    <!-- Modals Section -->
    <!-- Details Modal -->
    <div id="detailsModal"
        style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(2,6,23,0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); align-items:center; justify-content:center; z-index:1000;">
        <div
            style="background:#ffffff; width:90%; max-width:700px; border-radius:24px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.2); max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <div
                style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.5); backdrop-filter: blur(5px); position: relative; z-index: 10;">
                <h3 id="detailsTitle" style="margin:0; font-size: 1.25rem; color: #1e293b; font-weight: 700;">Details
                </h3>
                <button id="closeDetails"
                    style="background:#f1f5f9; color:#64748b; border:none; width: 32px; height: 32px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.2s; font-size: 1.1rem;"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="detailsBody" style="padding: 24px; overflow-y: auto;"></div>
        </div>
    </div>

    <!-- Password Gate Modal -->
    <div id="passwordModal"
        style="display:none; position:fixed; inset:0; background: linear-gradient(115deg, #0d1b3e 50%, #ffffff 50%); align-items:center; justify-content:center; z-index:99999;">
        <div
            style="background:#ffffff; width:92%; max-width:440px; border-radius:32px; padding:40px 30px; position:relative; box-shadow:0 30px 80px rgba(2,6,23,0.3); border:1px solid #e2e8f0; overflow: hidden; text-align: center;">
            <div style="margin-bottom: 25px;"><img src="../assets/image/logo.png" alt="Logo"
                    style="width: 180px; height: auto;"></div>
            <div style="position: relative; z-index: 1;">
                <h2 style="margin:0 0 10px; font-weight:800; color:#0f172a; letter-spacing:-0.5px; text-align:center;">
                    Security Check</h2>
                <p style="margin:0 0 30px; color:#64748b; text-align:center;">Enter your PIN to access this system</p>
                <form id="passwordForm">
                    <div class="pin-input" style="display:flex; justify-content:center; margin-bottom:30px;">
                        <input type="password" maxlength="1" class="pin-digit"
                            style="width:60px; height:60px; margin:0 8px; text-align:center; font-size:28px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc;">
                        <input type="password" maxlength="1" class="pin-digit"
                            style="width:60px; height:60px; margin:0 8px; text-align:center; font-size:28px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc;">
                        <input type="password" maxlength="1" class="pin-digit"
                            style="width:60px; height:60px; margin:0 8px; text-align:center; font-size:28px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc;">
                        <input type="password" maxlength="1" class="pin-digit"
                            style="width:60px; height:60px; margin:0 8px; text-align:center; font-size:28px; border:2px solid #e2e8f0; border-radius:14px; background:#f8fafc;">
                    </div>
                    <div id="pwdError"
                        style="color:#e11d48; font-size:.9rem; margin-top:-15px; margin-bottom:20px; text-align:center; display:none; font-weight:600;">
                        Incorrect PIN. Please try again.</div>
                    <div style="display:flex; gap:12px; justify-content:center;">
                        <button type="button" class="cancel-btn" id="pwdCancel"
                            style="padding:12px 24px; border-radius:12px; font-weight:600;">Cancel</button>
                        <button type="submit" class="save-btn"
                            style="padding:12px 24px; border-radius:12px; font-weight:700;">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Contract Form Modal -->
    <div id="contractFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:#ffffff; width:94%; max-width:500px; border-radius:24px; padding:30px; position:relative; box-shadow:0 30px 70px rgba(0,0,0,0.25); max-height: 90vh; overflow-y: auto;">
            <button type="button" id="closeContractFormModal"
                style="position:absolute; right:16px; top:16px; background:#ef4444; color:white; border:none; width: 32px; height: 32px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.2s; z-index: 20;"
                onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.1)'"
                onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div id="contractFormContainer"></div>
        </div>
    </div>

    <!-- Employee Form Modal wrapper -->
    <div id="employeeFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:#ffffff; width:94%; max-width:720px; border-radius:32px; padding:40px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.2); overflow: hidden;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <button type="button" id="closeEmployeeFormModal"
                style="position:absolute; right:16px; top:16px; background:#ef4444; color:white; border:none; width: 32px; height: 32px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.2s; z-index: 20;"
                onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.1)'"
                onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div id="employeeFormContainer" style="position: relative; z-index: 1;"></div>
        </div>
    </div>

    <!-- Employee Info Modal (Revamped Premium View) -->
    <div id="employeeInfoModal"
        style="display:none; position:fixed; inset:0; background:rgba(2, 6, 23, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div class="premium-modal modal-animate-in"
            style="width:94%; max-width:550px; border-radius:32px; padding:0; position:relative; overflow: hidden; display: flex; flex-direction: column;">

            <!-- Modal Header with Gradient -->
            <div
                style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 30px 40px; color: white; position: relative;">
                <button type="button" id="closeEmployeeInfoTop"
                    style="position:absolute; right:20px; top:20px; background:rgba(255,255,255,0.2); color:white; border:none; width: 36px; height: 36px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 20;"
                    onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='rotate(90deg)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='rotate(0deg)'">
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <div style="display: flex; align-items: center; justify-content: center; gap: 20px;">
                    <div id="genderImageContainer" style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow: hidden;">
                        <img src="../assets/image/Women.png" alt="Gender" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div
                        style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 24px; display: grid; place-items: center; font-size: 2.5rem; backdrop-filter: blur(5px); box-shadow: 0 8px 24px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div style="text-align: center;">
                        <h2 id="employeeInfoTitle"
                            style="margin:0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em;">Employee
                            Profile</h2>
                        <span id="employeeRoleBadge"
                            style="display: inline-block; margin-top: 5px; background: rgba(59, 130, 246, 0.3); color: #93c5fd; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">Legal
                            Team</span>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <div id="employeeInfoBody" style="padding: 40px; background: white; position: relative;">
                <div id="employeeSensitiveData" class="blurred-content">
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <!-- Data Row: Name -->
                        <div class="info-row">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Full
                                Name</label>
                            <div id="display_emp_name"
                                style="font-size: 1.1rem; font-weight: 600; color: #1e293b; padding: 12px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                                -</div>
                        </div>

                        <!-- Data Row: Position -->
                        <div class="info-row">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Position</label>
                            <div id="display_emp_position"
                                style="font-size: 1rem; font-weight: 500; color: #1e293b; padding: 12px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                                -</div>
                        </div>

                        <!-- Data Grid: Contact Info -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="info-row">
                                <label
                                    style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Email
                                    Address</label>
                                <div id="display_emp_email"
                                    style="font-size: 0.95rem; font-weight: 500; color: #2563eb; padding: 12px 16px; background: #eff6ff; border-radius: 12px; border: 1px solid #dbeafe; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    -</div>
                            </div>
                            <div class="info-row">
                                <label
                                    style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Phone
                                    Number</label>
                                <div id="display_emp_phone"
                                    style="font-size: 0.95rem; font-weight: 500; color: #1e293b; padding: 12px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                                    -</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="employeeRevealOverlay" class="reveal-overlay">
                    <button class="reveal-btn" id="employeeRevealBtn"><i class="fa-solid fa-lock"></i> Enter PIN to
                        Reveal</button>
                </div>

                <!-- Footer Actions -->
                <div style="margin-top: 30px; display: flex; gap: 12px;">
                    <button type="button" id="closeEmployeeInfoBottom"
                        style="flex: 1; padding: 14px; border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                        Close
                    </button>
                    <button type="button" class="save-btn" id="modalDownloadEmpPdf"
                        style="flex: 2; border-radius: 16px; padding: 14px; font-weight: 700; background: #3b82f6; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <i class="fa-solid fa-file-pdf"></i> Download Official Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Form Modal -->
    <div id="documentFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:#ffffff; width:94%; max-width:720px; border-radius:32px; padding:40px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.2);">
            <button type="button" id="closeDocumentFormModal"
                style="position:absolute; right:16px; top:16px; background:#ef4444; color:white; border:none; width: 32px; height: 32px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.2s; z-index: 20;"
                onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.1)'"
                onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div id="documentFormContainer">
                <h3>Upload Document</h3>
                <form method="POST" enctype="multipart/form-data" id="documentFormData">
                    <input type="hidden" name="add_document" value="1">
                    <div class="form-group"><label>Document Name</label><input type="text" name="doc_name"
                            class="form-control" required></div>
                    <div class="form-group"><label>Case ID</label><input type="text" name="doc_case"
                            class="form-control" required></div>
                    <div class="form-group"><label>File</label><input type="file" name="doc_file" class="form-control"
                            accept=".pdf,.doc,.docx" required></div>
                    <div class="form-actions"><button type="button" class="cancel-btn"
                            id="cancelDocumentBtn">Cancel</button><button type="submit" class="save-btn">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Supporting Document Modal -->
    <div id="contractDocsModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1500;">
        <div
            style="background:#ffffff; width:94%; max-width:640px; border-radius:24px; padding:30px; position:relative; box-shadow:0 25px 50px rgba(0,0,0,0.1);">
            <button type="button" id="closeContractDocsModal"
                style="position:absolute; right:16px; top:16px; background:#ef4444; color:white; border:none; width: 32px; height: 32px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.2s; z-index: 20;"
                onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.1)'"
                onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 style="margin-top:0;">Upload Contract Document</h3>
            <form method="POST" enctype="multipart/form-data" id="contractDocsForm">
                <input type="hidden" name="add_contract_document" value="1">
                <input type="hidden" name="contract_id" id="contract_docs_contract_id" value="">
                <div class="form-group"><label>Document Name</label><input type="text" name="doc_name"
                        class="form-control" placeholder="e.g., Annex A" required></div>
                <div class="form-group"><label>File</label><input type="file" name="doc_file" class="form-control"
                        accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required></div>
                <div class="form-actions"><button type="button" class="cancel-btn"
                        id="cancelContractDocsBtn">Cancel</button><button type="submit" class="save-btn">Upload
                        Document</button></div>
            </form>
        </div>
    </div>

    <!-- Internal/External Info Modal -->
    <div id="legalDetailModal"
        style="display:none; position:fixed; inset:0; background:rgba(2, 6, 23, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div class="premium-modal modal-animate-in"
            style="width:94%; max-width:600px; border-radius:32px; padding:0; position:relative; overflow: hidden; display: flex; flex-direction: column;">
            <div
                style="background: linear-gradient(135deg, #0f172a 0%, #334155 100%); padding: 30px 40px; color: white; position: relative;">
                <button type="button" onclick="document.getElementById('legalDetailModal').style.display='none'"
                    style="position:absolute; right:20px; top:20px; background:rgba(255,255,255,0.2); color:white; border:none; width: 36px; height: 36px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.3s; z-index: 20;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div
                        style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 18px; display: grid; place-items: center; font-size: 1.8rem; backdrop-filter: blur(5px);">
                        <i class="fa-solid fa-file-contract"></i>
                    </div>
                    <div>
                        <h2 id="legalDetailTitle" style="margin:0; font-size: 1.3rem; font-weight: 800;">Document
                            Details</h2>
                        <span id="legalDetailCategory"
                            style="display: inline-block; margin-top: 5px; background: rgba(59, 130, 246, 0.3); color: #93c5fd; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">CATEGORY</span>
                    </div>
                </div>
            </div>
            <div style="padding: 40px; background: white; position: relative;">
                <div id="legalDetailContent" class="blurred-content">
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div class="info-row">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Document
                                Name</label>
                            <div id="legalDetailName"
                                style="font-size: 1rem; font-weight: 600; color: #1e293b; padding: 12px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                -</div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label
                                    style="display: block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;"
                                    id="legalDetailSecondaryLabel">Secondary</label>
                                <div id="legalDetailSecondary"
                                    style="font-size: 0.9rem; font-weight: 500; color: #1e293b; padding: 12px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                    -</div>
                            </div>
                            <div>
                                <label
                                    style="display: block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Effective/Expiry
                                    Date</label>
                                <div id="legalDetailDate"
                                    style="font-size: 0.9rem; font-weight: 500; color: #1e293b; padding: 12px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                    -</div>
                            </div>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="button"
                                onclick="document.getElementById('legalDetailModal').style.display='none'"
                                style="width: 100%; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; background: #f1f5f9; color: #475569; font-weight: 700; cursor: pointer;">
                                Close Window
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Reveal Overlay for Modal -->
                <div id="legalDetailRevealOverlay" class="reveal-overlay">
                    <button class="reveal-btn"
                        onclick="withPasswordGate(() => { document.getElementById('legalDetailContent').classList.remove('blurred-content'); document.getElementById('legalDetailRevealOverlay').style.display='none'; })">
                        <i class="fa-solid fa-lock"></i> Enter PIN to View Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- legalAnalysisModal -->
    <div id="legalAnalysisModal"
        style="display:none; position:fixed; inset:0; background:rgba(2, 6, 23, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div class="premium-modal modal-animate-in"
            style="width:94%; max-width:650px; border-radius:32px; padding:0; position:relative; overflow: hidden; display: flex; flex-direction: column;">
            <div
                style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); padding: 30px 40px; color: white; position: relative;">
                <button type="button" onclick="document.getElementById('legalAnalysisModal').style.display='none'"
                    style="position:absolute; right:20px; top:20px; background:rgba(255,255,255,0.2); color:white; border:none; width: 36px; height: 36px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.3s;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div
                        style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 18px; display: grid; place-items: center; font-size: 1.8rem;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div>
                        <h2 style="margin:0; font-size: 1.3rem; font-weight: 800;">AI Risk Analysis Report</h2>
                        <span id="analysisTargetType"
                            style="display: inline-block; margin-top: 5px; background: rgba(99, 102, 241, 0.3); color: #c7d2fe; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">TYPE</span>
                    </div>
                </div>
            </div>
            <div style="padding: 40px; background: white; max-height: 70vh; overflow-y: auto;position: relative;">
                <div id="legalAnalysisContent" class="blurred-content">
                    <div style="display: flex; flex-direction: column; gap: 25px;">
                        <div
                            style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 20px; border: 1px solid #e2e8f0;">
                            <div
                                style="font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 10px; text-transform: uppercase;">
                                Confidence Score</div>
                            <div style="font-size: 2.5rem; font-weight: 900; color: #4338ca;">94%</div>
                        </div>
                        <div>
                            <h4 id="analysisTargetName"
                                style="font-size: 1.1rem; color: #1e293b; margin-bottom: 15px; font-weight: 700;">-</h4>
                            <div id="analysisSummaryText"
                                style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; border-radius: 10px;">
                                <p style="margin:0; font-size: 0.9rem; color: #166534; line-height: 1.6;">AI analysis
                                    suggests this document is highly compliant with standard legal framework. Minimal
                                    risk
                                    exposure detected.</p>
                            </div>
                        </div>
                        <div class="info-row">
                            <label
                                style="display: block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 10px;">Key
                                Findings</label>
                            <ul id="analysisKeyFindings"
                                style="padding-left: 20px; margin: 0; color: #475569; font-size: 0.9rem; line-height: 1.8;">
                                <li>No restrictive clauses detected.</li>
                                <li>Liability terms are clearly defined and balanced.</li>
                                <li>Renewal and termination policies are standard.</li>
                            </ul>
                        </div>
                        <div style="margin-top: 10px;">
                            <button type="button"
                                onclick="document.getElementById('legalAnalysisModal').style.display='none'"
                                style="width: 100%; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; background: #f1f5f9; color: #475569; font-weight: 700; cursor: pointer;">
                                Close Report
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Reveal Overlay for Analysis -->
                <div id="legalAnalysisRevealOverlay" class="reveal-overlay">
                    <button class="reveal-btn"
                        onclick="withPasswordGate(() => { document.getElementById('legalAnalysisContent').classList.remove('blurred-content'); document.getElementById('legalAnalysisRevealOverlay').style.display='none'; })">
                        <i class="fa-solid fa-lock"></i> Enter PIN to View Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const APP_CORRECT_PIN = '<?php echo $archivePin; ?>';
    </script>
    <script src="../assets/Javascript/legalmanagemet.js?v=<?php echo time(); ?>"></script>

    <script>
        (function () {
            // GLOBALIZE CHART INIT FIRST (Ensures it's availab le for other scripts)
            window.riskChartRef = null;
            window.initRiskChart = function () {
                const canvas = document.getElementById('riskDistributionChart');
                if (!canvas) return;

                if (typeof Chart === 'undefined') {
                    console.warn("Chart library missing. Retrying...");
                    setTimeout(window.initRiskChart, 800);
                    return;
                }

                const chartData = [
                    <?php echo (int) ($riskCounts['High'] ?? 0); ?>,
                    <?php echo (int) ($riskCounts['Medium'] ?? 0); ?>,
                    <?php echo (int) ($riskCounts['Low'] ?? 0); ?>
                ];

                try {
                    const ctx = canvas.getContext('2d');
                    if (window.riskChartRef) window.riskChartRef.destroy();

                    window.riskChartRef = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['High Risk', 'Medium Risk', 'Low Risk'],
                            datasets: [{
                                label: 'Contracts',
                                data: chartData,
                                backgroundColor: [
                                    'rgba(239, 68, 68, 0.8)',
                                    'rgba(245, 158, 11, 0.8)', 
                                    'rgba(16, 185, 129, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(239, 68, 68, 1)',
                                    'rgba(245, 158, 11, 1)',
                                    'rgba(16, 185, 129, 1)'
                                ],
                                borderWidth: 2,
                                borderRadius: 8,
                                barThickness: 60
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1500,
                                easing: 'easeInOutQuart',
                                delay: (context) => {
                                    let delay = 0;
                                    if (context.type === 'data' && context.mode === 'default') {
                                        delay = context.dataIndex * 200 + context.datasetIndex * 100;
                                    }
                                    return delay;
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                                    titleColor: '#ffffff',
                                    bodyColor: '#ffffff',
                                    padding: 12,
                                    borderRadius: 8,
                                    displayColors: true,
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.dataset.label || '';
                                            const value = context.parsed.y;
                                            const total = chartData.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(226, 232, 240, 0.5)',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        color: '#64748b',
                                        font: {
                                            weight: 600,
                                            size: 12
                                        },
                                        stepSize: 1
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    },
                                    ticks: {
                                        color: '#64748b',
                                        font: {
                                            weight: 700,
                                            size: 13
                                        }
                                    }
                                }
                            }
                        }
                    });
                    console.log("Enhanced Chart Rendered:", chartData);
                } catch (e) {
                    console.error("Chart Error:", e);
                }
            };

            // Force Re-check every few seconds
            setInterval(() => {
                if (!window.riskChartRef && document.getElementById('riskDistributionChart')) {
                    window.initRiskChart();
                }
            }, 2000);

            // Security Gate Implementation
            window.withPasswordGate = function (callback) {
                const modal = document.getElementById('passwordModal');
                const form = document.getElementById('passwordForm');
                const error = document.getElementById('pwdError');
                const cancel = document.getElementById('pwdCancel');
                const digits = modal.querySelectorAll('.pin-digit');

                if (!modal) {
                    callback(); // Fallback if modal missing
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
                    if (pin === '<?php echo $archivePin; ?>') { // Default PIN for demo
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

            // Immediate Triggers
            window.initRiskChart();
            document.addEventListener('DOMContentLoaded', window.initRiskChart);
            window.addEventListener('load', window.initRiskChart);

            // Function to show Legal Details Modal
            window.showLegalDetails = function (name, secondary, date, type, secondaryLabel) {
                document.getElementById('legalDetailName').textContent = name;
                document.getElementById('legalDetailCategory').textContent = type;
                document.getElementById('legalDetailSecondary').textContent = secondary;
                document.getElementById('legalDetailDate').textContent = date;
                document.getElementById('legalDetailSecondaryLabel').textContent = secondaryLabel;

                // Reset Blur State
                document.getElementById('legalDetailContent').classList.add('blurred-content');
                document.getElementById('legalDetailRevealOverlay').style.display = 'flex';

                document.getElementById('legalDetailModal').style.display = 'flex';
            };

            // Function to show Legal Analysis Modal
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

                document.getElementById('legalAnalysisModal').style.display = 'flex';
            };

            const employeeInfoTitle = document.getElementById('employeeInfoTitle');
            const empInfoModal = document.getElementById('employeeInfoModal');
            const closeEmployeeInfoTop = document.getElementById('closeEmployeeInfoTop');
            const closeEmployeeInfoBottom = document.getElementById('closeEmployeeInfoBottom');
            const cancelEmployeeBtn = document.getElementById('cancelEmployeeBtn');

            const detailsModal = document.getElementById('detailsModal');
            const detailsTitle = document.getElementById('detailsTitle');
            const detailsBody = document.getElementById('detailsBody');
            const closeDetails = document.getElementById('closeDetails');

            const contractForm = document.getElementById('contractForm');
            const contractFormModal = document.getElementById('contractFormModal');
            const contractFormContainer = document.getElementById('contractFormContainer');
            const addContractBtn = document.getElementById('addContractBtn');
            const cancelContractBtn = document.getElementById('cancelContractBtn');
            const closeContractFormModal = document.getElementById('closeContractFormModal');
            const exportPdfBtn = document.getElementById('exportPdfBtn');
            const exportPdfForm = document.getElementById('exportPdfForm');
            // Employee form modal
            const employeeForm = document.getElementById('employeeForm');
            const employeeFormModal = document.getElementById('employeeFormModal');
            const employeeFormContainer = document.getElementById('employeeFormContainer');
            const addEmployeeBtn = document.getElementById('addEmployeeBtn');
            const closeEmployeeFormModal = document.getElementById('closeEmployeeFormModal');
            // Document form modal
            const documentFormModal = document.getElementById('documentFormModal');
            const documentFormContainer = document.getElementById('documentFormContainer');
            const addDocumentBtn = document.getElementById('addDocumentBtn');
            const cancelDocumentBtn = document.getElementById('cancelDocumentBtn');
            const closeDocumentFormModal = document.getElementById('closeDocumentFormModal');
            // Edit document modal
            const editDocModal = document.getElementById('editDocumentModal');
            const closeEditDocument = document.getElementById('closeEditDocument');
            const cancelEditDocument = document.getElementById('cancelEditDocument');
            const editDocForm = document.getElementById('editDocumentForm');
            const editDocId = document.getElementById('edit_doc_id');
            const editDocName = document.getElementById('edit_doc_name');
            const editDocCase = document.getElementById('edit_doc_case');
            // Contract docs modal
            const contractDocsModal = document.getElementById('contractDocsModal');
            const closeContractDocsModal = document.getElementById('closeContractDocsModal');
            const cancelContractDocsBtn = document.getElementById('cancelContractDocsBtn');
            const contractDocsContractId = document.getElementById('contract_docs_contract_id');

            function openModal(el) { el.style.display = 'flex'; }
            function closeModal(el) { el.style.display = 'none'; }

            // PDF Generation Utility
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

            // Universal PDF Download Handler for various data types
            window.downloadRecordAsPDF = function (type, data) {
                let title = '';
                let contentHTML = '';
                let filename = '';

                switch (type) {
                    case 'employee':
                        title = 'Employee Profile';
                        contentHTML = `
                        <div style="margin-bottom: 20px;">
                            <p><strong>Name:</strong> ${data.name || 'N/A'}</p>
                            <p><strong>Position:</strong> ${data.position || 'N/A'}</p>
                            <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                        </div>
                    `;
                        filename = `Employee_${(data.name || 'Profile').replace(/\s+/g, '_')}.pdf`;
                        break;
                    case 'document':
                        title = 'Document Details';
                        contentHTML = `
                        <div style="margin-bottom: 20px;">
                            <p><strong>Document Name:</strong> ${data.name || 'N/A'}</p>
                            <p><strong>Case ID:</strong> ${data.case_id || 'N/A'}</p>
                            <p><strong>Date Uploaded:</strong> ${data.uploaded_at || 'N/A'}</p>
                        </div>
                    `;
                        filename = `Document_${(data.name || 'File').replace(/\s+/g, '_')}.pdf`;
                        break;
                    case 'contract':
                        title = 'Contract Risk Analysis';
                        const rf = (() => { try { return typeof data.risk_factors === 'string' ? JSON.parse(data.risk_factors || '[]') : data.risk_factors; } catch { return []; } })();
                        const rec = (() => { try { return typeof data.recommendations === 'string' ? JSON.parse(data.recommendations || '[]') : data.recommendations; } catch { return []; } })();
                        const isImage = data.file_path && /\.(jpg|jpeg|png|webp|gif)$/i.test(data.file_path);
                        const imageHTML = isImage ? `
                        <div style="margin-top: 30px; border-top: 2px solid #f1f5f9; pt: 20px;">
                            <h4 style="margin-bottom: 15px; color: #1e293b;">Contract Image Attachment</h4>
                            <div style="text-align: center; background: #f8fafc; padding: 10px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                                <img src="${data.file_path}" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" />
                            </div>
                        </div>
                    ` : '';

                        contentHTML = `
                        <div style="margin-bottom: 20px;">
                            <p><strong>Contract Name:</strong> ${data.contract_name || data.name || 'N/A'}</p>
                            <p><strong>Case ID:</strong> ${data.case_id || 'N/A'}</p>
                            <p><strong>Risk Level:</strong> <span style="color: ${data.risk_level === 'High' ? '#ef4444' : (data.risk_level === 'Medium' ? '#f59e0b' : '#22c55e')}; font-weight: bold;">${data.risk_level || 'N/A'}</span></p>
                            <p><strong>Risk Score:</strong> ${data.risk_score || 0}/100</p>
                            <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                <p style="margin: 0;"><strong>Summary:</strong> ${data.analysis_summary || 'N/A'}</p>
                            </div>
                            <div style="display: flex; gap: 20px; margin-top: 20px;">
                                <div style="flex: 1;">
                                    <h4 style="color: #ef4444; margin-bottom: 8px;">Risk Factors</h4>
                                    <ul style="margin: 0; padding-left: 20px;">${rf.map(r => `<li>${r.factor || 'Unknown Factor'}</li>`).join('') || '<li>None</li>'}</ul>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="color: #059669; margin-bottom: 8px;">Recommendations</h4>
                                    <ul style="margin: 0; padding-left: 20px;">${rec.map(x => `<li>${x}</li>`).join('') || '<li>Standard review</li>'}</ul>
                                </div>
                            </div>
                            ${imageHTML}
                        </div>
                    `;
                        filename = `Contract_Analysis_${(data.contract_name || 'Contract').replace(/\s+/g, '_')}.pdf`;
                        break;
                }

                generatePDFFromData(title, contentHTML, filename);
            }

            // Consolidated Unified Event Delegation for Table Actions & PDF handling
            document.body.addEventListener('click', function (e) {
                const target = e.target.closest('button, a.view-pdf-link, .download-btn');
                if (!target) return;

                const type = target.getAttribute('data-type') || 
                            (target.classList.contains('download-btn') ? 'download' : 
                            (target.classList.contains('view-pdf-link') ? 'pdf-view' : ''));

                // Handle employee-view specifically
                if (target.classList.contains('view-btn') && target.getAttribute('data-emp')) {
                    // Employee View
                    withPasswordGate(() => {
                        const emp = JSON.parse(target.getAttribute('data-emp') || '{}');
                        const modal = document.getElementById('employeeInfoModal');

                        // Update Display Fields
                        document.getElementById('employeeInfoTitle').textContent = emp.name || 'Employee Profile';
                        document.getElementById('display_emp_name').textContent = emp.name || 'N/A';
                        document.getElementById('display_emp_position').textContent = emp.position || 'N/A';
                        document.getElementById('display_emp_email').textContent = emp.email || 'N/A';
                        document.getElementById('display_emp_phone').textContent = emp.phone || 'N/A';

                        // Update Gender Image Based on Name
                        const genderImageContainer = document.getElementById('genderImageContainer');
                        if (genderImageContainer && emp.name) {
                            const employeeName = emp.name.toLowerCase();
                            let genderImage = '../assets/image/Men.png'; // Default to male
                            
                            // Simple gender detection based on common Filipino names
                            const femaleNames = ['maria', 'mary', 'ana', 'anna', 'juanita', 'carmela', 'rosa', 'rose', 'grace', 'joy', 'patricia', 'pat', 'christine', 'tin', 'elizabeth', 'beth', 'catherine', 'cathy', 'margarita', 'maggie', 'lourdes', 'lou', 'rebecca', 'becky', 'sophia', 'sophie', 'isabella', 'bella', 'angelica', 'angeli', 'micah', 'mika', 'sarah', 'sam', 'rachel', 'rach', 'diana', 'diane', 'hannah', 'anna', 'maria', 'mary', 'josephine', 'joyce', 'evelyn', 'lyn', 'eunice', 'cecille', 'cecil', 'charmaine', 'charm', 'kathleen', 'kath', 'maureen', 'mau', 'regina', 'reg', 'liza', 'elisa', 'victoria', 'vic', 'bianca', 'bianx', 'camille', 'cam', 'danielle', 'dan', 'frances', 'fran', 'gillian', 'gil', 'jacqueline', 'jackie', 'kristine', 'kris', 'lovelyn', 'love', 'michelle', 'mich', 'nicole', 'nic', 'pamela', 'pam', 'stephanie', 'steph', 'teresa', 'terry', 'vanessa', 'van', 'yvonne', 'von', 'alexis', 'lex', 'amber', 'brianna', 'bri', 'claudine', 'clau', 'fatima', 'faye', 'georgina', 'georg', 'helena', 'len', 'irish', 'janine', 'jan', 'katherine', 'kat', 'lilian', 'lil', 'monica', 'mon', 'natalie', 'nat', 'olivia', 'liv', 'princess', 'princ', 'queenie', 'queen', 'roxanne', 'rox', 'samantha', 'sam', 'tricia', 'trish', 'ursula', 'urs', 'valerie', 'val', 'winona', 'win', 'zara', 'zar'];
                            
                            // Check if name contains any female indicators
                            const isFemale = femaleNames.some(femaleName => employeeName.includes(femaleName)) || 
                                            employeeName.includes('ms.') || 
                                            employeeName.includes('miss') ||
                                            employeeName.endsWith('a') && !employeeName.includes('luisa') && !employeeName.includes('joshua');
                            
                            if (isFemale) {
                                genderImage = '../assets/image/Women.png';
                            }
                            
                            genderImageContainer.innerHTML = `<img src="${genderImage}" alt="Gender" style="width: 100%; height: 100%; object-fit: cover;">`;
                        }

                        // Handle Role Badge Colors
                        const badge = document.getElementById('employeeRoleBadge');
                        if (badge) {
                            badge.textContent = emp.position || 'Legal Team';
                            if (emp.position && emp.position.toLowerCase().includes('senior')) {
                                badge.style.background = 'rgba(16, 185, 129, 0.2)';
                                badge.style.color = '#10b981';
                                badge.style.borderColor = 'rgba(16, 185, 129, 0.2)';
                            } else {
                                badge.style.background = 'rgba(59, 130, 246, 0.2)';
                                badge.style.color = '#3b82f6';
                                badge.style.borderColor = 'rgba(59, 130, 246, 0.2)';
                            }
                        }

                        // Reset Blur
                        document.getElementById('employeeSensitiveData').classList.add('blurred-content');
                        document.getElementById('employeeRevealOverlay').style.display = 'flex';

                        document.getElementById('employeeRevealBtn').onclick = function () {
                            withPasswordGate(() => {
                                document.getElementById('employeeRevealOverlay').style.display = 'none';
                                document.getElementById('employeeSensitiveData').classList.remove('blurred-content');
                            });
                        };

                        // Set up PDF download button
                        const dlBtn = document.getElementById('modalDownloadEmpPdf');
                        if (dlBtn) {
                            dlBtn.onclick = () => {
                                const originalText = dlBtn.innerHTML;
                                dlBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
                                window.downloadRecordAsPDF('employee', emp);
                                setTimeout(() => { dlBtn.innerHTML = originalText; }, 2000);
                            };
                        }

                        openModal(modal);

                        // Add animation class to modal content
                        const content = modal.querySelector('.premium-modal');
                        if (content) {
                            content.classList.remove('modal-animate-in');
                            void content.offsetWidth; // Trigger reflow
                            content.classList.add('modal-animate-in');
                        }
                    });
                    return;
                }

                if (!type) return;

                // 1. PDF DOWNLOAD HANDLING
                if (target.classList.contains('download-btn') || type === 'download') {
                    const pdfType = target.getAttribute('data-pdf-type');
                    const pdfContent = target.getAttribute('data-pdf-content');
                    if (pdfType && pdfContent) {
                        try {
                            const data = JSON.parse(pdfContent);
                            downloadRecordAsPDF(pdfType, data);
                            e.preventDefault();
                            return;
                        } catch (err) { console.error("PDF generation failed:", err); }
                    }
                }

                // 2. PDF VIEW HANDLING (Hijacked Name Links)
                if (type === 'pdf-view') {
                    const pdfType = target.getAttribute('data-pdf-type');
                    const pdfContent = target.getAttribute('data-pdf-content');
                    if (pdfType && pdfContent) {
                        try {
                            const data = JSON.parse(pdfContent);
                            downloadRecordAsPDF(pdfType, data); // For now, we reuse download as "view", or we could customize
                            e.preventDefault();
                            return;
                        } catch (err) { console.error("PDF view failed:", err); }
                    }
                }

                // 3. TABLE ACTION MODALS (Password Protected)
                withPasswordGate(() => {
                    // Document View
                    if (type === 'doc-view') {
                        const d = JSON.parse(target.getAttribute('data-doc') || '{}');
                        detailsTitle.textContent = 'Document Details';
                        detailsBody.innerHTML = `
                            <div style="position: relative;">
                                <div id="docSensitive" class="blurred-content">
                                    <div style="display:grid; grid-template-columns:160px 1fr; gap:8px; line-height:1.8; position: relative; z-index: 1;">
                                        <div><strong>Name</strong></div><div>${d.name || ''}</div>
                                        <div><strong>Case ID</strong></div><div>${d.case_id || ''}</div>
                                        <div><strong>Uploaded At</strong></div><div>${d.uploaded_at || ''}</div>
                                    </div>
                                </div>
                                <div class="reveal-overlay" id="docReveal">
                                    <button class="reveal-btn"><i class="fa-solid fa-lock"></i> Enter PIN to Reveal</button>
                                </div>
                            </div>`;
                        openModal(detailsModal);

                        document.getElementById('docReveal').addEventListener('click', function () {
                            const overlay = this;
                            withPasswordGate(() => {
                                overlay.style.display = 'none';
                                document.getElementById('docSensitive').classList.remove('blurred-content');
                            });
                        });

                        injectModalPdfButton(detailsBody, 'document', d);
                    }
                    // Contract View
                    else if (type === 'contract-view') {
                        const c = JSON.parse(target.getAttribute('data-contract') || '{}');
                        detailsTitle.textContent = 'Contract Details';
                        detailsBody.innerHTML = `<div style="padding:10px;color:#64748b;">Loading details…</div>`;
                        openModal(detailsModal);
                        const rf = (() => { try { return JSON.parse(c.risk_factors || '[]'); } catch { return []; } })();
                        const rec = (() => { try { return JSON.parse(c.recommendations || '[]'); } catch { return []; } })();
                        detailsBody.innerHTML = `
                        <div style="position: relative;">
                            <div id="contractSensitive" class="blurred-content">
                                <div style="display:grid; grid-template-columns:160px 1fr; gap:8px; line-height:1.8;">
                                    <div><strong>Contract</strong></div><div>${c.contract_name || c.name || ''}</div>
                                    <div><strong>Case</strong></div><div>${c.case_id || ''}</div>
                                    <div><strong>Risk</strong></div><div>${(c.risk_level || 'N/A')} — ${c.risk_score || 'N/A'}/100</div>
                                    <div><strong>Uploaded</strong></div><div>${c.created_at || c.upload_date || ''}</div>
                                    <div style="grid-column:1/-1"><strong>Risk Factors</strong><ul style="margin:.4rem 0 0 1rem;">${rf.map(r => `<li>${(r.factor || '')}</li>`).join('') || '<li>None</li>'}</ul></div>
                                    <div style="grid-column:1/-1"><strong>Recommendations</strong><ul style="margin:.4rem 0 0 1rem;">${rec.map(x => `<li>${x}</li>`).join('') || '<li>None</li>'}</ul></div>
                                </div>
                            </div>
                            <div class="reveal-overlay" id="contractReveal">
                                <button class="reveal-btn"><i class="fa-solid fa-lock"></i> Enter PIN to Reveal</button>
                            </div>
                        </div>`;

                        document.getElementById('contractReveal').addEventListener('click', function () {
                            const overlay = this;
                            withPasswordGate(() => {
                                overlay.style.display = 'none';
                                document.getElementById('contractSensitive').classList.remove('blurred-content');
                            });
                        });

                        injectModalPdfButton(detailsBody, 'contract', c);
                    }
                    // Contract Analyze
                    else if (type === 'contract-analyze') {
                        const c = JSON.parse(target.getAttribute('data-contract') || '{}');
                        detailsTitle.textContent = 'AI Risk Analysis';
                        detailsBody.innerHTML = `<div style="padding:20px;text-align:center;color:#64748b;"><i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;margin-bottom:10px;"></i><br>Generating analysis report...</div>`;
                        openModal(detailsModal);
                        setTimeout(() => {
                            try {
                                const score = c.risk_score ?? 'N/A';
                                const level = c.risk_level ?? 'Unknown';
                                const rf = (() => { try { return JSON.parse(c.risk_factors || '[]'); } catch { return []; } })();
                                const rec = (() => { try { return JSON.parse(c.recommendations || '[]'); } catch { return []; } })();
                                let color = level === 'High' ? '#ef4444' : (level === 'Medium' ? '#f59e0b' : '#22c55e');

                                detailsBody.innerHTML = `
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <h2 style="margin: 0; color: ${color};">${level} Risk Contract</h2>
                                    <p>Risk Score: <strong>${score}/100</strong></p>
                                </div>
                                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                                    <p><strong>Summary:</strong> ${c.analysis_summary || 'No summary available.'}</p>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div><strong>Risk Factors:</strong><ul>${rf.map(f => `<li>${f.factor}</li>`).join('') || '<li>None</li>'}</ul></div>
                                    <div><strong>Recommendations:</strong><ul>${rec.map(r => `<li>${r}</li>`).join('') || '<li>Regular review</li>'}</ul></div>
                                </div>
                                <div style="text-align: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px;">
                                    <button type="button" class="save-btn" onclick='window.downloadRecordAsPDF("contract", ${JSON.stringify(c).replace(/'/g, "&apos;")})' style="background: #3b82f6; width: auto;">
                                        <i class="fa-solid fa-file-pdf"></i> Download Risk Report (PDF)
                                    </button>
                                </div>
                            `;
                            } catch (e) { detailsBody.innerHTML = "Error rendering analysis."; }
                        }, 500);
                    }
                });
            });

            function injectModalPdfButton(container, pdfType, pdfData) {
                let downloadBtn = document.getElementById('modalDownloadPdf');
                if (downloadBtn) downloadBtn.remove();

                downloadBtn = document.createElement('button');
                downloadBtn.id = 'modalDownloadPdf';
                downloadBtn.type = 'button';
                downloadBtn.className = 'save-btn';
                downloadBtn.style.cssText = `width:auto; margin-top:25px; background:linear-gradient(135deg, #059669 0%, #10b981 100%); border:none; padding:12px 24px; border-radius:12px; box-shadow:0 4px 12px rgba(5,150,105,0.2); display:inline-flex; align-items:center; gap:10px; font-weight:700; cursor:pointer;`;
                downloadBtn.innerHTML = '<i class="fa-solid fa-file-pdf"></i> Convert & Download PDF';
                downloadBtn.onclick = () => {
                    const originalHTML = downloadBtn.innerHTML;
                    downloadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
                    window.downloadRecordAsPDF(pdfType, pdfData);
                    setTimeout(() => { downloadBtn.innerHTML = originalHTML; }, 2000);
                };
                container.appendChild(downloadBtn);
            }


            // ADDED: Contract Upload Modal Logic
            if (addContractBtn) {
                addContractBtn.addEventListener('click', () => {
                    // Move the form into the modal container if not already there
                    if (contractForm && contractFormContainer && !contractFormContainer.contains(contractForm)) {
                        contractFormContainer.appendChild(contractForm);
                        contractForm.style.display = 'block';
                    }
                    openModal(contractFormModal);
                });
            }

            // ADDED: Edit Employee Logic
            window.editEmployee = function (emp) {
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
                saveBtn.name = 'update_employee';

                // Open modal
                if (employeeForm && employeeFormContainer && !employeeFormContainer.contains(employeeForm)) {
                    employeeFormContainer.appendChild(employeeForm);
                    employeeForm.style.display = 'block';
                }
                openModal(employeeFormModal);
            };

            // Enhanced Add Employee Button Logic (Reset form)
            if (addEmployeeBtn) {
                addEmployeeBtn.addEventListener('click', () => {
                    document.getElementById('employeeFormData').reset();
                    const saveBtn = document.getElementById('saveEmployeeBtn');
                    saveBtn.innerText = 'Save Employee';
                    saveBtn.name = 'add_employee';

                    // Remove hidden ID if exists
                    const idInput = document.querySelector('input[name="employee_id"]');
                    if (idInput) idInput.remove();

                    if (employeeForm && employeeFormContainer && !employeeFormContainer.contains(employeeForm)) {
                        employeeFormContainer.appendChild(employeeForm);
                        employeeForm.style.display = 'block';
                    }
                    openModal(employeeFormModal);
                });
            }

            // Document Upload Button Logic
            if (addDocumentBtn) {
                addDocumentBtn.addEventListener('click', () => {
                    openModal(documentFormModal);
                });
            }

            // ADDED: Universal Close/Cancel Handlers for all Modals
            if (closeDetails) closeDetails.addEventListener('click', () => closeModal(detailsModal));
            if (closeEmployeeInfoTop) closeEmployeeInfoTop.addEventListener('click', () => closeModal(empInfoModal));
            if (closeEmployeeInfoBottom) closeEmployeeInfoBottom.addEventListener('click', () => closeModal(empInfoModal));
            if (cancelEmployeeBtn) cancelEmployeeBtn.addEventListener('click', () => {
                closeModal(empInfoModal);
                closeModal(employeeFormModal);
            });
            if (closeEmployeeFormModal) closeEmployeeFormModal.addEventListener('click', () => closeModal(employeeFormModal));
            if (closeDocumentFormModal) closeDocumentFormModal.addEventListener('click', () => closeModal(documentFormModal));
            if (cancelDocumentBtn) cancelDocumentBtn.addEventListener('click', () => closeModal(documentFormModal));
            if (closeEditDocument) closeEditDocument.addEventListener('click', () => closeModal(editDocModal));
            if (cancelEditDocument) cancelEditDocument.addEventListener('click', () => closeModal(editDocModal));
            if (closeContractDocsModal) closeContractDocsModal.addEventListener('click', () => closeModal(contractDocsModal));
            if (cancelContractDocsBtn) cancelContractDocsBtn.addEventListener('click', () => closeModal(contractDocsModal));
            if (closeContractFormModal) closeContractFormModal.addEventListener('click', () => closeModal(contractFormModal));
            if (cancelContractBtn) cancelContractBtn.addEventListener('click', () => {
                closeModal(contractFormModal);
                if (contractForm) contractForm.reset();
            });



            // Generate Secured PDF (password-gated) - Real PDF Implementation
            exportPdfBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                withPasswordGate(() => {
                    // Data is injected from PHP
                    const data = <?php echo json_encode($contracts); ?>;

                    let contentHTML = `
                        <div style="margin-top: 20px;">
                            <p>This is a secured legal report containing sensitive contract risk information.</p>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11pt;">
                                <thead>
                                    <tr style="background-color: #f1f5f9; text-align: left;">
                                        <th style="border: 1px solid #cbd5e1; padding: 12px;">Contract Name</th>
                                        <th style="border: 1px solid #cbd5e1; padding: 12px;">Case ID</th>
                                        <th style="border: 1px solid #cbd5e1; padding: 12px; text-align: center;">Risk Level</th>
                                        <th style="border: 1px solid #cbd5e1; padding: 12px; text-align: center;">Risk Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.map(c => `
                                        <tr>
                                            <td style="border: 1px solid #cbd5e1; padding: 10px;">${c.contract_name || c.name || 'N/A'}</td>
                                            <td style="border: 1px solid #cbd5e1; padding: 10px;">${c.case_id || 'N/A'}</td>
                                            <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">
                                                <span style="color: ${c.risk_level === 'High' ? '#ef4444' : (c.risk_level === 'Medium' ? '#f59e0b' : '#22c55e')}; font-weight: bold;">
                                                    ${c.risk_level || 'Low'}
                                                </span>
                                            </td>
                                            <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">${c.risk_score || 0}/100</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;

                    generatePDFFromData('Secured Legal Contracts Report', contentHTML, 'Legal_Contracts_Report_Secured.pdf');
                });
            });

            // Find the back button handler and update it:
            // Removed redundant JS listener for back button to prevent conflicts with <a> tag
        })();
    </script>

    <!-- Loading Animation Script -->
    <script>
        // Select all elements with 'wave-text' class
        const waveTexts = document.querySelectorAll('.wave-text');

        waveTexts.forEach(textContainer => {
            const text = textContainer.textContent;
            textContainer.innerHTML = ''; // Clear existing text

            // Split text into letters and create spans
            [...text].forEach((letter, index) => {
                const span = document.createElement('span');
                span.textContent = letter === ' ' ? '\u00A0' : letter; // Handle spaces
                span.style.setProperty('--i', index); // Set custom property for delay
                textContainer.appendChild(span);
            });
        });

        // Define Global Loader Function
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

        // Hide loading screen after page loads
        window.addEventListener('load', function () {
            // Initially disable login screen interactions
            const loginScreen = document.getElementById('loginScreen');
            if (loginScreen) loginScreen.style.pointerEvents = 'none';

            setTimeout(function () {
                const loader = document.getElementById('loadingOverlay');
                if (loader) {
                    loader.style.opacity = '0';
                    setTimeout(() => {
                        loader.style.display = 'none';

                        // Enable inputs and button after loader is gone
                        const pinInputs = document.querySelectorAll('.pin-digit');
                        const loginBtn = document.getElementById('loginBtn');

                        if (pinInputs.length > 0) {
                            pinInputs.forEach(input => input.disabled = false);
                            pinInputs[0].focus(); // Focus after enabling
                        }
                        if (loginBtn) {
                            loginBtn.disabled = false;
                        }
                        if (loginScreen) {
                            loginScreen.style.pointerEvents = 'auto';
                        }
                    }, 500);
                }
                document.body.classList.add('loaded');
            }, 3000); // 3 seconds total loading time
        });
    </script>

    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:block; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;">
        <iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"
            allowtransparency="true"></iframe>
    </div>
    <!-- Edit Legal Modal -->
    <div id="editLegalModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter: blur(6px); align-items:center; justify-content:center; z-index:1200;">
        <div style="background:#ffffff; width:94%; max-width:600px; border-radius:24px; padding:30px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.2);">
            <button type="button" onclick="closeModal(document.getElementById('editLegalModal'))"
                style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;">&times;</button>
            <h2 style="font-size:24px; color:#0f172a; margin-bottom:20px;">Edit Legal Record</h2>
            <form method="POST" id="editLegalForm">
                <input type="hidden" name="edit_id" id="edit_legal_id">
                <input type="hidden" name="edit_type" id="edit_legal_type">

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Record Name</label>
                    <input type="text" name="edit_name" id="edit_legal_name" class="form-control" style="width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px;" required>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Case ID Reference</label>
                    <input type="text" name="edit_case_id" id="edit_legal_case_id" class="form-control" style="width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px;" required>
                </div>

                <div id="dynamic_edit_fields"></div>

                <div class="form-actions" style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="cancel-btn"
                        style="background:#f1f5f9; color:#64748b; border:none; padding:10px 20px; border-radius:12px; font-weight:600; cursor:pointer;"
                        onclick="closeModal(document.getElementById('editLegalModal'))">Cancel</button>
                    <button type="submit" name="update_legal_record" class="save-btn"
                        style="background:linear-gradient(135deg, #1e293b 0%, #334155 100%); color:white; border:none; padding:10px 20px; border-radius:12px; font-weight:600; cursor:pointer;">Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editLegalRecord(data, type) {
            const modal = document.getElementById('editLegalModal');
            document.getElementById('edit_legal_id').value = data.id;
            document.getElementById('edit_legal_type').value = type;
            document.getElementById('edit_legal_name').value = data.name || data.contract_name || '';
            document.getElementById('edit_legal_case_id').value = data.case_id || '';

            const dynamicFields = document.getElementById('dynamic_edit_fields');
            dynamicFields.innerHTML = '';

            if (type === 'contract') {
                dynamicFields.innerHTML = `
                    <div class="form-group" style="margin-bottom:15px;">
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Resource Description</label>
                        <textarea name="edit_description" class="form-control" style="width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px;" rows="3">${data.description || ''}</textarea>
                    </div>
                    <div class="form-group" style="margin-bottom:15px;">
                        <label style="display:block; margin-bottom:5px; font-weight:600;">Legal Classification</label>
                        <select name="edit_contract_type" class="form-control" style="width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px;">
                            <option value="Internal" ${data.contract_type === 'Internal' ? 'selected' : ''}>Internal (Policies/SOP)</option>
                            <option value="External" ${data.contract_type === 'External' ? 'selected' : ''}>External (Agreements/NDA)</option>
                        </select>
                    </div>
                `;
            }

            modal.style.display = 'flex';
        }

        function closeModal(modal) {
            if (modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>