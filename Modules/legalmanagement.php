<?php
// config.php
class Database
{
    private $host = "127.0.0.1";
    private $db_name = "admin_new";
    private $username = "admin_new";
    private $password = "123";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
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
    $database = new Database();
    $db = $database->getConnection();

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
    // Add Invoice
    if (isset($_POST['add_invoice'])) {
        $inv_number = $_POST['invoice_number'] ?? '';
        $client = $_POST['client'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $due_date = $_POST['due_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'pending';
        $q = "INSERT INTO billing (invoice_number, client_name, amount, due_date, status) VALUES (?, ?, ?, ?, ?)";
        $s = $db->prepare($q);
        if ($s->execute([$inv_number, $client, $amount, $due_date, $status])) {
            $success_message = "Invoice created successfully!";
        } else {
            $error_message = "Failed to create invoice.";
        }
    }
    // Pay invoice (set to paid)
    if (isset($_POST['pay_invoice'])) {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if ($invoice_id > 0) {
            $q = "UPDATE billing SET status = 'paid' WHERE id = ?";
            $s = $db->prepare($q);
            if ($s->execute([$invoice_id])) {
                $success_message = "Invoice has been marked as PAID.";
            } else {
                $error_message = "Payment failed. Try again.";
            }
        } else {
            $error_message = "Invalid invoice ID.";
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

        $query = "INSERT INTO contracts (name, case_id, description, file_path, risk_level, risk_score, risk_factors, recommendations, analysis_summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);

        $risk_factors_json = json_encode($riskAnalysis['risk_factors']);
        $recommendations_json = json_encode($riskAnalysis['recommendations']);

        if (
            $stmt->execute([
                $contract_name,
                $case_id,
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

// Fetch employees from database
$database = new Database();
$db = $database->getConnection();
$employees = [];
$contracts = [];

try {
    $query = "SELECT id, name, role as position, email, phone FROM contacts";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $error_message = "Error fetching employees: " . $exception->getMessage();
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

$billing = [];
try {
    $query = "SELECT id, invoice_number, client_name as client, amount, due_date, status FROM billing ORDER BY due_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $billing = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // fallback demo data
    $billing = [
        ['invoice_number' => 'INV-001', 'client' => 'Hotel Management', 'amount' => 2500, 'due_date' => '2023-07-15', 'status' => 'paid'],
        ['invoice_number' => 'INV-002', 'client' => 'Restaurant Owner', 'amount' => 1800, 'due_date' => '2023-08-05', 'status' => 'pending']
    ];
}

// Risk summary with normalized casing
$riskCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($contracts as $c) {
    $lvl = ucfirst(strtolower($c['risk_level'] ?? 'Low'));
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
    <title>Legal Management System - Hotel & Restaurant</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="../assets/css/legalmanagement.css?v=<?php echo time(); ?>">

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
                <input type="password" maxlength="1" class="pin-digit" id="pin1">
                <input type="password" maxlength="1" class="pin-digit" id="pin2">
                <input type="password" maxlength="1" class="pin-digit" id="pin3">
                <input type="password" maxlength="1" class="pin-digit" id="pin4">
            </div>
            <button class="login-btn" id="loginBtn">Login</button>
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
                        <button type="button" class="logout-btn" id="backDashboardBtn"
                            onclick="window.location.href='../Modules/facilities-reservation.php'">
                            <span class="icon-img-placeholder">⏻</span> logout
                        </button>
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
                <div class="nav-tab" data-target="documents">Documents</div>
                <div class="nav-tab" data-target="billing">Billing</div>
                <div class="nav-tab" data-target="contracts">Contracts</div>
                <div class="nav-tab" data-target="risk_analysis">Risk Analysis</div>
                <div class="nav-tab" data-target="members">Members</div>
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

                <!-- Employees Table -->
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
                                <td>E-<?php echo str_pad($employee['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                                <td>
                                    <button class="action-btn view-btn" data-type="employee-view"
                                        data-emp='<?php echo htmlspecialchars(json_encode($employee)); ?>'>View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Documents Section -->
            <div class="content-section" id="documents">
                <div class="section-header">
                    <h2 class="section-title">Case Documents</h2>
                    <button class="add-btn" id="addDocumentBtn">
                        <i>+</i> Upload Document
                    </button>
                </div>
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
                                            <a href="#" class="view-pdf-link text-blue-600 hover:underline" data-pdf-type="document"
                                                data-pdf-content='<?php echo htmlspecialchars(json_encode($doc)); ?>'><?php echo htmlspecialchars($doc['name']); ?></a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($doc['name']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($doc['case_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($doc['uploaded_at'] ?? 'now'))); ?>
                                    </td>
                                    <td>
                                        <button class="action-btn download-btn" data-type="doc-download"
                                            data-pdf-type="document"
                                            data-pdf-content='<?php echo htmlspecialchars(json_encode($doc)); ?>'
                                            style="background:linear-gradient(135deg, #059669 0%, #10b981 100%); color:#fff; border:none; border-radius:12px; padding:8px 16px; font-weight:700; box-shadow:0 4px 12px rgba(5,150,105,0.2);">
                                            <i class="fa-solid fa-file-pdf"></i> Download PDF
                                        </button>
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

            <div class="content-section" id="billing">
                <div class="section-header">
                    <h2 class="section-title">Billing & Invoices</h2>
                    <button class="add-btn" id="addInvoiceBtn">
                        <i>+</i> Create Invoice
                    </button>
                </div>
                <table class="data-table premium-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="billingTableBody">
                        <?php if (!empty($billing)): ?>
                            <?php foreach ($billing as $b): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($b['invoice_number'] ?? $b['id']); ?></td>
                                    <td><?php echo htmlspecialchars($b['client'] ?? 'N/A'); ?></td>
                                    <td>₱<?php echo number_format($b['amount'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars(!empty($b['due_date']) ? date('Y-m-d', strtotime($b['due_date'])) : 'N/A'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst($b['status'] ?? 'unknown')); ?></td>
                                    <td>
                                        <button class="action-btn view-btn" data-type="invoice-view"
                                            data-invoice='<?php echo htmlspecialchars(json_encode($b)); ?>'>View</button>
                                        <button class="action-btn download-btn" data-type="invoice-download"
                                            data-pdf-type="billing"
                                            data-pdf-content='<?php echo htmlspecialchars(json_encode($b)); ?>'
                                            style="background:#0284c7;color:#fff;border-radius:8px;padding:6px 10px;border:none;cursor:pointer;font-size:12px;">Download
                                            PDF</button>
                                        <button class="action-btn"
                                            style="background:#16a34a;color:#fff;border-radius:8px;padding:6px 10px;"
                                            data-type="invoice-pay"
                                            data-invoice='<?php echo htmlspecialchars(json_encode($b)); ?>'>Pay</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#666;padding:20px;">No billing records
                                    found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                            <th>Risk Level</th>
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
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($contract['risk_level']); ?>">
                                        <?php echo htmlspecialchars($contract['risk_level']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($contract['risk_score']); ?>/100</td>
                                <td><?php echo date('Y-m-d', strtotime($contract['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn analyze-btn" data-type="contract-analyze"
                                        data-contract='<?php echo htmlspecialchars(json_encode($contract)); ?>'>AI
                                        Risk Analysis</button>
                                    <button class="action-btn download-btn" data-type="contract-download"
                                        data-pdf-type="contract"
                                        data-pdf-content='<?php echo htmlspecialchars(json_encode($contract)); ?>'
                                        style="background: #059669; color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-weight: 500; font-size: 13px; cursor: pointer;">
                                        Download PDF
                                    </button>
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
                        <h3 class="subsection-title"><i class="fa-solid fa-chart-simple"></i> Risk Distribution</h3>
                        <div class="chart-area" id="chartArea"
                            style="height: 320px; width: 100%; position: relative; background: #fafafa; border-radius: 16px; border: 1px solid #f1f5f9; padding: 15px; display: flex; align-items: center; justify-content: center;">
                            <canvas id="riskDistributionChart"
                                 style="display: block; box-sizing: border-box; height: 100% !important; width: 100% !important; opacity: 1; transition: opacity 0.5s ease;"></canvas>
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

            <div class="content-section" id="members">
                <div class="section-header">
                    <h2 class="section-title">Team Members</h2>
                    <button class="add-btn" id="addMemberBtn">
                        <i>+</i> Add Member
                    </button>
                </div>
                <table class="data-table premium-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody">
                        <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                                    <td>
                                        <button class="action-btn view-btn" data-type="employee-view"
                                            data-emp='<?php echo htmlspecialchars(json_encode($employee)); ?>'>View</button>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <script src="../assets/Javascript/legalmanagemet.js?v=<?php echo time(); ?>"></script>

    <!-- Details Modal -->
    <div id="detailsModal"
        style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(2,6,23,0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1000;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:90%; max-width:700px; border-radius:24px; position:relative; box-shadow:0 25px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2); max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">

            <div
                style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.5); backdrop-filter: blur(5px); position: relative; z-index: 10;">
                <h3 id="detailsTitle" style="margin:0; font-size: 1.25rem; color: #1e293b; font-weight: 700;">
                    Details
                </h3>
                <button id="closeDetails"
                    style="background:#f1f5f9; color:#64748b; border:none; width: 32px; height: 32px; border-radius: 50%; cursor:pointer; display: grid; place-items: center; transition: all 0.2s; font-size: 1.1rem;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="detailsBody" style="padding: 24px; overflow-y: auto;">
                <!-- Fallback content shown if no dynamic content is provided -->
                <form id="genericModalForm" style="display:block">
                    <div
                        style="padding:12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; margin-bottom:10px;">
                        <h4 style="margin:0 0 6px;">Quick Note</h4>
                        <p style="margin:0 0 8px; color:#475569;">Enter an optional note then submit. This is a
                            default
                            content view that appears when no specific details are loaded.</p>
                        <textarea name="note" rows="3" class="form-control glass-input"
                            placeholder="Type your note here…" style="width:100%;"></textarea>
                    </div>
                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="submit" class="save-btn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Password Gate Modal -->
    <div id="passwordModal"
        style="display:none; position:fixed; inset:0; background: linear-gradient(115deg, #0d1b3e 50%, #ffffff 50%); align-items:center; justify-content:center; z-index:99999;">
        <div
            style="background:#ffffff; width:92%; max-width:440px; border-radius:32px; padding:40px 30px; position:relative; box-shadow:0 30px 80px rgba(2,6,23,0.3); border:1px solid #e2e8f0; overflow: hidden; text-align: center;">
            <div style="margin-bottom: 25px;">
                <img src="../assets/image/logo.png" alt="Logo" style="width: 180px; height: auto;">
            </div>

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


    <!-- Contract Form Modal wrapper -->
    <div id="contractFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(15px); width:94%; max-width:500px; border-radius:24px; padding:20px; position:relative; box-shadow:0 30px 70px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.3); max-height: 90vh; overflow-y: auto;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <button type="button" id="closeContractFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="contractFormContainer">
                <!-- The existing contract form will be moved here dynamically -->
            </div>
        </div>
    </div>
    <!-- Employee Form Modal wrapper -->
    <div id="employeeFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2); overflow: hidden;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <button type="button" id="closeEmployeeFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="employeeFormContainer" style="position: relative; z-index: 1;"></div>
        </div>
    </div>
    <!-- Document Form Modal wrapper -->
    <div id="documentFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2); overflow: hidden;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <button type="button" id="closeDocumentFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="documentFormContainer" style="position: relative; z-index: 1;">
                <h3>Upload Document</h3>
                <form method="POST" enctype="multipart/form-data" id="documentFormData">
                    <input type="hidden" name="add_document" value="1">
                    <div class="form-group">
                        <label for="doc_name">Document Name</label>
                        <input type="text" id="doc_name" name="doc_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="doc_case">Case ID</label>
                        <input type="text" id="doc_case" name="doc_case" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="doc_file">File</label>
                        <input type="file" id="doc_file" name="doc_file" class="form-control" accept=".pdf,.doc,.docx"
                            required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn" id="cancelDocumentBtn">Cancel</button>
                        <button type="submit" class="save-btn">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit Document Modal -->
    <!-- Invoice Form Modal wrapper -->
    <div id="invoiceFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2); overflow: hidden;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <button type="button" id="closeInvoiceFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="invoiceFormContainer" style="position: relative; z-index: 1;">
                <h3>Create Invoice</h3>
                <form method="POST" id="invoiceFormData">
                    <input type="hidden" name="add_invoice" value="1">
                    <div class="form-group">
                        <label for="inv_number">Invoice #</label>
                        <input type="text" id="inv_number" name="invoice_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_client">Client</label>
                        <input type="text" id="inv_client" name="client" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_amount">Amount</label>
                        <input type="number" step="0.01" id="inv_amount" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_due">Due Date</label>
                        <input type="date" id="inv_due" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_status">Status</label>
                        <select id="inv_status" name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn" id="cancelInvoiceBtn">Cancel</button>
                        <button type="submit" class="save-btn">Save Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Pay confirmation modal -->
    <div id="payConfirmModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1600;">
        <div
            style="background:#ffffff; width:92%; max-width:440px; border-radius:32px; padding:35px; position:relative; box-shadow:0 25px 60px rgba(0,0,0,0.1); border:1px solid #e2e8f0;">
            <h3 style="margin:0 0 8px;">Confirm Payment</h3>
            <p id="payConfirmText" style="margin:0 0 14px; color:#475569;">Do you want to pay this invoice?</p>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="cancel-btn" id="cancelPayBtn">No</button>
                <form method="POST" id="payInvoiceForm" style="margin:0;">
                    <input type="hidden" name="pay_invoice" value="1">
                    <input type="hidden" name="invoice_id" id="pay_invoice_id" value="">
                    <button type="submit" class="save-btn" style="background:#16a34a;">Yes, Pay</button>
                </form>
            </div>
        </div>
    </div>
    <!-- Contract: Upload Supporting Document Modal -->
    <div id="contractDocsModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1500;">
        <div
            style="background:#ffffff; width:94%; max-width:640px; border-radius:24px; padding:30px; position:relative; box-shadow:0 25px 50px rgba(0,0,0,0.1); border:1px solid #e2e8f0;">
            <button type="button" id="closeContractDocsModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer;">Close</button>
            <h3 style="margin-top:0;">Upload Contract Document</h3>
            <form method="POST" enctype="multipart/form-data" id="contractDocsForm">
                <input type="hidden" name="add_contract_document" value="1">
                <input type="hidden" name="contract_id" id="contract_docs_contract_id" value="">
                <div class="form-group">
                    <label for="contract_doc_name">Document Name</label>
                    <input type="text" id="contract_doc_name" name="doc_name" class="form-control"
                        placeholder="e.g., Annex A, Addendum, Scanned Signature" required>
                </div>
                <div class="form-group">
                    <label for="contract_doc_file">File</label>
                    <input type="file" id="contract_doc_file" name="doc_file" class="form-control"
                        accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="cancelContractDocsBtn">Cancel</button>
                    <button type="submit" class="save-btn">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Document Modal -->
    <!-- Invoice Form Modal wrapper -->
    <div id="invoiceFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2); overflow: hidden;">
            <!-- Internal Logo Watermark -->
            <img src="../assets/image/logo.png" alt="Logo Watermark"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.03; pointer-events: none; z-index: 0;">
            <button type="button" id="closeInvoiceFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="invoiceFormContainer" style="position: relative; z-index: 1;">
                <h3>Create Invoice</h3>
                <form method="POST" id="invoiceFormData">
                    <input type="hidden" name="add_invoice" value="1">
                    <div class="form-group">
                        <label for="inv_number">Invoice #</label>
                        <input type="text" id="inv_number" name="invoice_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_client">Client</label>
                        <input type="text" id="inv_client" name="client" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_amount">Amount</label>
                        <input type="number" step="0.01" id="inv_amount" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_due">Due Date</label>
                        <input type="date" id="inv_due" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="inv_status">Status</label>
                        <select id="inv_status" name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn" id="cancelInvoiceBtn">Cancel</button>
                        <button type="submit" class="save-btn">Save Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Pay confirmation modal -->
    <div id="payConfirmModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1600;">
        <div
            style="background:#ffffff; width:92%; max-width:440px; border-radius:32px; padding:35px; position:relative; box-shadow:0 25px 60px rgba(0,0,0,0.1); border:1px solid #e2e8f0;">
            <h3 style="margin:0 0 8px;">Confirm Payment</h3>
            <p id="payConfirmText" style="margin:0 0 14px; color:#475569;">Do you want to pay this invoice?</p>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="cancel-btn" id="cancelPayBtn">No</button>
                <form method="POST" id="payInvoiceForm" style="margin:0;">
                    <input type="hidden" name="pay_invoice" value="1">
                    <input type="hidden" name="invoice_id" id="pay_invoice_id" value="">
                    <button type="submit" class="save-btn" style="background:#16a34a;">Yes, Pay</button>
                </form>
            </div>
        </div>
    </div>
    <!-- Contract: Upload Supporting Document Modal -->
    <div id="contractDocsModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1500;">
        <div
            style="background:#ffffff; width:94%; max-width:640px; border-radius:24px; padding:30px; position:relative; box-shadow:0 25px 50px rgba(0,0,0,0.1); border:1px solid #e2e8f0;">
            <button type="button" id="closeContractDocsModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer;">Close</button>
            <h3 style="margin-top:0;">Upload Contract Document</h3>
            <form method="POST" enctype="multipart/form-data" id="contractDocsForm">
                <input type="hidden" name="add_contract_document" value="1">
                <input type="hidden" name="contract_id" id="contract_docs_contract_id" value="">
                <div class="form-group">
                    <label for="contract_doc_name">Document Name</label>
                    <input type="text" id="contract_doc_name" name="doc_name" class="form-control"
                        placeholder="e.g., Annex A, Addendum, Scanned Signature" required>
                </div>
                <div class="form-group">
                    <label for="contract_doc_file">File</label>
                    <input type="file" id="contract_doc_file" name="doc_file" class="form-control"
                        accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="cancelContractDocsBtn">Cancel</button>
                    <button type="submit" class="save-btn">Upload Document</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modals Section -->
    <!-- Details Modal -->
    <div id="detailsModal"
        style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(2,6,23,0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1000;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:90%; max-width:700px; border-radius:24px; position:relative; box-shadow:0 25px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2); max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
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
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(15px); width:94%; max-width:500px; border-radius:24px; padding:20px; position:relative; box-shadow:0 30px 70px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.3); max-height: 90vh; overflow-y: auto;">
            <button type="button" id="closeContractFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="contractFormContainer"></div>
        </div>
    </div>

    <!-- Employee Info Modal -->
    <div id="employeeInfoModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2);">
            <button type="button" id="closeEmployeeInfo"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <h3 id="employeeInfoTitle">Employee Profile</h3>
            <form id="employeeInfoForm">
                <input type="hidden" id="info_emp_id">
                <div class="form-group"><label>Name</label><input type="text" id="info_emp_name" class="form-control">
                </div>
                <div class="form-group"><label>Position</label><input type="text" id="info_emp_position"
                        class="form-control"></div>
                <div class="form-group"><label>Email</label><input type="email" id="info_emp_email"
                        class="form-control"></div>
                <div class="form-group"><label>Phone</label><input type="text" id="info_emp_phone" class="form-control">
                </div>
                <div class="form-actions" style="display:none;">
                    <button type="button" class="cancel-btn" id="cancelEmployeeInfo">Cancel</button>
                    <button type="submit" class="save-btn">Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Document Form Modal -->
    <div id="documentFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2);">
            <button type="button" id="closeDocumentFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
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

    <!-- Invoice Form Modal -->
    <div id="invoiceFormModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1150;">
        <div
            style="background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); width:94%; max-width:720px; border-radius:32px; padding:35px; position:relative; box-shadow:0 30px 60px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2);">
            <button type="button" id="closeInvoiceFormModal"
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; z-index: 10;">Close</button>
            <div id="invoiceFormContainer">
                <h3>Create Invoice</h3>
                <form method="POST" id="invoiceFormData">
                    <input type="hidden" name="add_invoice" value="1">
                    <div class="form-group"><label>Invoice #</label><input type="text" name="invoice_number"
                            class="form-control" required></div>
                    <div class="form-group"><label>Client</label><input type="text" name="client" class="form-control"
                            required></div>
                    <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount"
                            class="form-control" required></div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date"
                            class="form-control" required></div>
                    <div class="form-group"><label>Status</label><select name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select></div>
                    <div class="form-actions"><button type="button" class="cancel-btn"
                            id="cancelInvoiceBtn">Cancel</button><button type="submit" class="save-btn">Save
                            Invoice</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pay Confirmation Modal -->
    <div id="payConfirmModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items:center; justify-content:center; z-index:1600;">
        <div
            style="background:#ffffff; width:92%; max-width:440px; border-radius:32px; padding:35px; position:relative; box-shadow:0 25px 60px rgba(0,0,0,0.1);">
            <h3>Confirm Payment</h3>
            <p id="payConfirmText" style="margin:0 0 14px; color:#475569;">Do you want to pay this invoice?</p>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="cancel-btn" id="cancelPayBtn">No</button>
                <form method="POST" id="payInvoiceForm" style="margin:0;">
                    <input type="hidden" name="pay_invoice" value="1">
                    <input type="hidden" name="invoice_id" id="pay_invoice_id" value="">
                    <button type="submit" class="save-btn" style="background:#16a34a;">Yes, Pay</button>
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
                style="position:absolute; right:12px; top:12px; background:#e74c3c; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer;">Close</button>
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

    <script src="../assets/Javascript/legalmanagemet.js?v=<?php echo time(); ?>"></script>

    <script>
        (function () {
            const cancelEmpInfo = document.getElementById('cancelEmployeeInfo');
            const empInfoForm = document.getElementById('employeeInfoForm');
            const infoId = document.getElementById('info_emp_id');
            const infoName = document.getElementById('info_emp_name');
            const infoPos = document.getElementById('info_emp_position');
            const infoEmail = document.getElementById('info_emp_email');
            const infoPhone = document.getElementById('info_emp_phone');
            const employeeInfoTitle = document.getElementById('employeeInfoTitle');
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
            const closeEditDoc = document.getElementById('closeEditDocument');
            const cancelEditDoc = document.getElementById('cancelEditDocument');
            const editDocForm = document.getElementById('editDocumentForm');
            const editDocId = document.getElementById('edit_doc_id');
            const editDocName = document.getElementById('edit_doc_name');
            const editDocCase = document.getElementById('edit_doc_case');
            // Invoice form modal
            const invoiceFormModal = document.getElementById('invoiceFormModal');
            const addInvoiceBtn = document.getElementById('addInvoiceBtn');
            const closeInvoiceFormModal = document.getElementById('closeInvoiceFormModal');
            const cancelInvoiceBtn = document.getElementById('cancelInvoiceBtn');
            // Pay modal
            const payConfirmModal = document.getElementById('payConfirmModal');
            const cancelPayBtn = document.getElementById('cancelPayBtn');
            const payInvoiceId = document.getElementById('pay_invoice_id');
            const payConfirmText = document.getElementById('payConfirmText');
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
                    case 'billing':
                        title = 'Invoice Summary';
                        contentHTML = `
                        <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px;">
                            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Invoice #${data.invoice_number || data.id}</h3>
                            <p><strong>Client:</strong> ${data.client || 'N/A'}</p>
                            <p><strong>Amount:</strong> ₱${Number(data.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                            <p><strong>Due Date:</strong> ${data.due_date || 'N/A'}</p>
                            <p><strong>Status:</strong> <span style="color: ${data.status === 'paid' ? '#059669' : '#dc2626'}; font-weight: bold;">${data.status.toUpperCase()}</span></p>
                        </div>
                    `;
                        filename = `Invoice_${data.invoice_number || data.id}.pdf`;
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

                const type = target.getAttribute('data-type') || (target.classList.contains('download-btn') ? 'download' : (target.classList.contains('view-pdf-link') ? 'pdf-view' : ''));
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
                    // Employee/Member View/Edit
                    // Employee View
                    if (type === 'employee-view') {
                        const emp = JSON.parse(target.getAttribute('data-emp') || '{}');
                        employeeInfoTitle.textContent = 'Employee Information';
                        infoId.value = emp.id || '';
                        infoName.value = emp.name || '';
                        infoPos.value = emp.position || '';
                        infoEmail.value = emp.email || '';
                        infoPhone.value = emp.phone || '';
                        [infoName, infoPos, infoEmail, infoPhone].forEach(i => { if (i) { i.readOnly = true; i.disabled = false; i.classList.add('glass-input'); } });
                        const actions = empInfoForm.querySelector('.form-actions');
                        if (actions) actions.style.display = 'none';
                        openModal(empInfoModal);
                        injectModalPdfButton(empInfoForm, 'employee', emp);
                    }
                    // Document View
                    else if (type === 'doc-view') {
                        const d = JSON.parse(target.getAttribute('data-doc') || '{}');
                        detailsTitle.textContent = 'Document Details';
                        detailsBody.innerHTML = `
                            <div style="display:grid; grid-template-columns:160px 1fr; gap:8px; line-height:1.8; position: relative; z-index: 1;">
                                <div><strong>Name</strong></div><div>${d.name || ''}</div>
                                <div><strong>Case ID</strong></div><div>${d.case_id || ''}</div>
                                <div><strong>Uploaded At</strong></div><div>${d.uploaded_at || ''}</div>
                            </div>`;
                        openModal(detailsModal);
                        injectModalPdfButton(detailsBody, 'document', d);
                    }
                    // Invoice View
                    else if (type === 'invoice-view') {
                        const inv = JSON.parse(target.getAttribute('data-invoice') || '{}');
                        detailsTitle.textContent = 'Invoice Details';
                        detailsBody.innerHTML = `
                      <div style="display:grid; grid-template-columns:160px 1fr; gap:8px; line-height:1.8;">
                        <div><strong>Invoice #</strong></div><div>${inv.invoice_number || inv.id || ''}</div>
                        <div><strong>Client</strong></div><div>${inv.client || ''}</div>
                        <div><strong>Amount</strong></div><div>₱${Number(inv.amount || 0).toFixed(2)}</div>
                        <div><strong>Due Date</strong></div><div>${inv.due_date || ''}</div>
                        <div><strong>Status</strong></div><div>${(inv.status || '').toString().toUpperCase()}</div>
                      </div>`;
                        openModal(detailsModal);
                        injectModalPdfButton(detailsBody, 'billing', inv);
                    }
                    // Invoice Pay
                    else if (type === 'invoice-pay') {
                        const inv = JSON.parse(target.getAttribute('data-invoice') || '{}');
                        payInvoiceId.value = inv.id || '';
                        payConfirmText.textContent = `Do you want to pay invoice ${inv.invoice_number || inv.id || ''} for ₱${Number(inv.amount || 0).toFixed(2)}?`;
                        openModal(payConfirmModal);
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
                        <div style="display:grid; grid-template-columns:160px 1fr; gap:8px; line-height:1.8;">
                            <div><strong>Contract</strong></div><div>${c.contract_name || c.name || ''}</div>
                            <div><strong>Case</strong></div><div>${c.case_id || ''}</div>
                            <div><strong>Risk</strong></div><div>${(c.risk_level || 'N/A')} — ${c.risk_score || 'N/A'}/100</div>
                            <div><strong>Uploaded</strong></div><div>${c.created_at || c.upload_date || ''}</div>
                            <div style="grid-column:1/-1"><strong>Risk Factors</strong><ul style="margin:.4rem 0 0 1rem;">${rf.map(r => `<li>${(r.factor || '')}</li>`).join('') || '<li>None</li>'}</ul></div>
                            <div style="grid-column:1/-1"><strong>Recommendations</strong><ul style="margin:.4rem 0 0 1rem;">${rec.map(x => `<li>${x}</li>`).join('') || '<li>None</li>'}</ul></div>
                        </div>`;
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


            // Add Member Button Logic
            const addMemberBtn = document.getElementById('addMemberBtn');
            if (addMemberBtn) {
                addMemberBtn.addEventListener('click', () => {
                    employeeInfoTitle.textContent = 'Add Team Member';
                    infoId.value = '';
                    infoName.value = '';
                    infoPos.value = '';
                    infoEmail.value = '';
                    infoPhone.value = '';
                    try {
                        const actions = empInfoForm.querySelector('.form-actions');
                        if (actions) actions.style.display = '';
                        [infoName, infoPos, infoEmail, infoPhone].forEach(i => { if (i) { i.readOnly = false; i.disabled = false; } });
                    } catch (e) { }
                    openModal(empInfoModal);
                });
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
            // ADDED: Universal Close/Cancel Handlers for all Modals
            if (closeDetails) closeDetails.addEventListener('click', () => closeModal(detailsModal));
            if (closeEmployeeInfo) closeEmployeeInfo.addEventListener('click', () => closeModal(empInfoModal));
            if (cancelEmployeeInfo) cancelEmployeeInfo.addEventListener('click', () => closeModal(empInfoModal));
            if (closeEditEmployee) closeEditEmployee.addEventListener('click', () => closeModal(editModal));
            if (cancelEditEmployee) cancelEditEmployee.addEventListener('click', () => closeModal(editModal));
            if (closeEmployeeFormModal) closeEmployeeFormModal.addEventListener('click', () => closeModal(employeeFormModal));
            if (closeDocumentFormModal) closeDocumentFormModal.addEventListener('click', () => closeModal(documentFormModal));
            if (cancelDocumentBtn) cancelDocumentBtn.addEventListener('click', () => closeModal(documentFormModal));
            if (closeEditDocument) closeEditDocument.addEventListener('click', () => closeModal(editDocModal));
            if (cancelEditDocument) cancelEditDocument.addEventListener('click', () => closeModal(editDocModal));
            if (closeInvoiceFormModal) closeInvoiceFormModal.addEventListener('click', () => closeModal(invoiceFormModal));
            if (cancelInvoiceBtn) cancelInvoiceBtn.addEventListener('click', () => closeModal(invoiceFormModal));
            if (cancelPayBtn) cancelPayBtn.addEventListener('click', () => closeModal(payConfirmModal));
            if (closeContractDocsModal) closeContractDocsModal.addEventListener('click', () => closeModal(contractDocsModal));
            if (cancelContractDocsBtn) cancelContractDocsBtn.addEventListener('click', () => closeModal(contractDocsModal));
            if (closeContractFormModal) closeContractFormModal.addEventListener('click', () => closeModal(contractFormModal));
            if (cancelContractBtn) cancelContractBtn.addEventListener('click', () => {
                closeModal(contractFormModal);
                if (contractForm) contractForm.reset();
            });

            // Globalization of Chart instance control
            window.riskChartRef = null;
            window.initRiskChart = function () {
                console.log("initRiskChart: Starting...");
                const status = document.getElementById('chartStatus');
                const canvas = document.getElementById('riskDistributionChart');

                if (!canvas) {
                    console.error("initRiskChart: Canvas element missing.");
                    return;
                }

                if (typeof Chart === 'undefined') {
                    if (status) status.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i> Chart library failed to load.';
                    console.error("initRiskChart: Chart.js is undefined.");
                    return;
                }

                // High: <?php echo (int) $riskCounts['High']; ?>, Med: <?php echo (int) $riskCounts['Medium']; ?>, Low: <?php echo (int) $riskCounts['Low']; ?>
                const chartData = [<?php echo (int) $riskCounts['High']; ?>, <?php echo (int) $riskCounts['Medium']; ?>, <?php echo (int) $riskCounts['Low']; ?>];

                try {
                    if (window.riskChartRef) {
                        window.riskChartRef.destroy();
                    }

                    window.riskChartRef = new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: ['High Risk', 'Medium Risk', 'Low Risk'],
                            datasets: [{
                                data: chartData,
                                backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                                borderRadius: 12,
                                barPercentage: 0.6,
                                categoryPercentage: 0.8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 800 },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    padding: 12,
                                    cornerRadius: 10,
                                    displayColors: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1, color: '#94a3b8', font: { size: 11 } },
                                    grid: { color: '#f1f5f9', drawBorder: false }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#64748b', font: { weight: '600', size: 12 } }
                                }
                            }
                        }
                    });

                    if (status) status.style.display = 'none';
                    canvas.style.opacity = '1';
                    console.log("initRiskChart: Success.");
                } catch (err) {
                    if (status) status.innerHTML = 'Error initializing chart.';
                    console.error("initRiskChart: Exception:", err);
                }
            };

            // Standard triggers
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(window.initRiskChart, 100);
            }
            document.addEventListener('DOMContentLoaded', () => setTimeout(window.initRiskChart, 300));
            window.addEventListener('load', window.initRiskChart);
            window.addEventListener('resize', () => {
                if (window.initRiskChart) window.initRiskChart();
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
            const backBtn = document.getElementById('backDashboardBtn');
            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    // Redirect to facilities reservation dashboard
                    window.location.href = 'facilities-reservation.php';
                });
            }
        })();
    </script>
    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;">
        <iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"></iframe>
    </div>
</body>

</html>