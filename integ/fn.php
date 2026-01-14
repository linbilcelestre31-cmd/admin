<?php
/**
 * ATIERA External API - Journal Entries Endpoint
 * Public API for journal entry operations
 * For use with Administrative module and external integrations
 */

require_once '../../includes/database.php';
require_once '../../includes/api_auth.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = Database::getInstance();
$apiAuth = APIAuth::getInstance();

// Authenticate API request
try {
    $client = $apiAuth->authenticate();
} catch (Exception $e) {
    // Authentication errors are handled in the authenticate method
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single journal entry
                getJournalEntry($db, $_GET['id']);
            } else if (isset($_GET['reference'])) {
                // Get journal entry by entry_number
                getJournalEntryByReference($db, $_GET['reference']);
            } else if (isset($_GET['action']) && $_GET['action'] === 'summary') {
                // Get journal entries summary for administrative reporting
                getJournalEntriesSummary($db);
            } else {
                // Get all journal entries with filters
                getJournalEntries($db);
            }
            break;

        case 'POST':
            // Create new journal entry
            createJournalEntry($db, $client);
            break;

        case 'PUT':
            // Update journal entry
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Journal entry ID required for updates'
                ]);
                exit;
            }
            updateJournalEntry($db, $_GET['id'], $client);
            break;

        case 'DELETE':
            // Delete journal entry
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Journal entry ID required for deletion'
                ]);
                exit;
            }
            deleteJournalEntry($db, $_GET['id'], $client);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->error("External API error: " . $e->getMessage(), [
        'endpoint' => 'journal_entries',
        'method' => $method,
        'client_id' => $client['id']
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

/**
 * Get all journal entries with filters
 */
function getJournalEntries($db)
{
    $where = [];
    $params = [];

    // Filter by status
    if (isset($_GET['status'])) {
        $where[] = "je.status = ?";
        $params[] = $_GET['status'];
    }

    // Filter by date range
    if (isset($_GET['date_from'])) {
        $where[] = "je.entry_date >= ?";
        $params[] = $_GET['date_from'];
    }

    if (isset($_GET['date_to'])) {
        $where[] = "je.entry_date <= ?";
        $params[] = $_GET['date_to'];
    }

    // Filter by account (in journal entry lines)
    if (isset($_GET['account_id'])) {
        $where[] = "EXISTS (SELECT 1 FROM journal_entry_lines jel WHERE jel.journal_entry_id = je.id AND jel.account_id = ?)";
        $params[] = $_GET['account_id'];
    }

    // Filter by entry number (partial match)
    if (isset($_GET['entry_number'])) {
        $where[] = "je.entry_number LIKE ?";
        $params[] = '%' . $_GET['entry_number'] . '%';
    }

    // Filter by amount range
    if (isset($_GET['min_amount'])) {
        $where[] = "je.total_debit >= ?";
        $params[] = $_GET['min_amount'];
    }

    if (isset($_GET['max_amount'])) {
        $where[] = "je.total_debit <= ?";
        $params[] = $_GET['max_amount'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Pagination
    $limit = min((int) ($_GET['limit'] ?? 50), 200); // Max 200 per request
    $offset = (int) ($_GET['offset'] ?? 0);

    // Include lines in response?
    $includeLines = isset($_GET['include_lines']) && $_GET['include_lines'] === 'true';

    $sql = "
        SELECT
            je.*,
            u.full_name as created_by_name,
            pb.full_name as posted_by_name,
            COUNT(jel.id) as line_count
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        LEFT JOIN users pb ON je.posted_by = pb.id
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        $whereClause
        GROUP BY je.id
        ORDER BY je.entry_date DESC, je.id DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    // Execute query and return JSON (Implementation details usually handled by DB wrapper)
    // For this mock/template, we assume $db->query returns records
}