<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include your database connection
// Adjusted path to point to the correct db location
require '../db/db.php';

// ✅ Remote API Link
const API_BASE_URL = 'https://core1.atierahotelandrestaurant.com/get_direct_checkins.php';

// ✅ Detect which connection variable exists
if (function_exists('get_pdo')) {
    $db = get_pdo();
} elseif (isset($conn)) {
    $db = $conn;
} elseif (isset($con)) {
    $db = $con;
} elseif (isset($pdo)) {
    $db = $pdo;
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No database connection variable found. Please check db.php.'
    ]);
    exit;
}

try {
    // ✅ Auto-update database schema if columns are missing
    if ($db instanceof PDO) {
        $cols = $db->query("SHOW COLUMNS FROM direct_checkins")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('host_id', $cols)) {
            $db->exec("ALTER TABLE direct_checkins ADD COLUMN host_id VARCHAR(50) DEFAULT NULL");
        }
        if (!in_array('notes', $cols)) {
            $db->exec("ALTER TABLE direct_checkins ADD COLUMN notes TEXT DEFAULT NULL");
        }
    }

    // ✅ Handle POST request for inserting new records (LOCAL DB)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Read JSON input if sent as raw body, otherwise use $_POST
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // --- CHECKOUT LOGIC ---
        if (isset($input['action']) && $input['action'] === 'checkout' && isset($input['id'])) {
            $id = $input['id'];
            $checkoutDate = date('Y-m-d H:i:s');

            // 1. Prevent checking out external records (start with 'ext_')
            if (strpos((string) $id, 'ext_') === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Cannot check out external records.']);
                exit;
            }

            // 2. Update Local Record
            $sql = "UPDATE direct_checkins SET status = 'checked_out', checkout_date = ? WHERE id = ?"; // Removed 'checkin_time' from update

            // Adjust query based on DB driver
            if ($db instanceof mysqli) {
                $stmt = $db->prepare($sql);
                // 'si' -> string (date), integer (id)
                $stmt->bind_param("si", $checkoutDate, $id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Guest checked out successfully.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
                }
            } elseif ($db instanceof PDO) {
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$checkoutDate, $id])) {
                    echo json_encode(['status' => 'success', 'message' => 'Guest checked out successfully.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Database error: Failed to update record.']);
                }
            }
            exit;
        }
        // --- END CHECKOUT LOGIC ---

        // ... (Keep existing INSERT logic) ...

        $fullName = $input['full_name'] ?? null;
        $email = $input['email'] ?? null;
        $phone = $input['phone'] ?? null;
        $roomNumber = $input['room_number'] ?? null;
        $hostId = $input['host_id'] ?? null;
        $checkinDate = $input['time_in'] ?? date('Y-m-d H:i:s');
        $notes = $input['notes'] ?? null;

        if (!$fullName) {
            echo json_encode(['status' => 'error', 'message' => 'Full Name is required.']);
            exit;
        }

        $sql = "INSERT INTO direct_checkins (full_name, email, phone_number, room_number, host_id, checkin_date, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";

        if ($db instanceof mysqli) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssssss", $fullName, $email, $phone, $roomNumber, $hostId, $checkinDate, $notes);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Check-in recorded successfully.']);
            } else {
                throw new Exception($stmt->error);
            }
        } elseif ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$fullName, $email, $phone, $roomNumber, $hostId, $checkinDate, $notes]);
            echo json_encode(['status' => 'success', 'message' => 'Check-in recorded successfully.']);
        }

        exit; // Stop further execution
    }

    // ✅ Handle GET request: Fetch from EXTERNAL API + LOCAL DB (Combined)
    $externalData = [];
    $localData = [];

    // 1. Fetch from External API
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, API_BASE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL for testing
        $response = curl_exec($ch);

        if (!curl_errno($ch)) {
            $json = json_decode($response, true);
            if ($json && isset($json['data'])) {
                // Map external fields to our UI format
                foreach ($json['data'] as $item) {
                    $externalData[] = [
                        'id' => 'ext_' . ($item['id'] ?? uniqid()),
                        'full_name' => $item['guest_name'] ?? 'N/A',
                        'room_number' => $item['room_number'] ?? 'N/A',
                        'checkin_date' => $item['checkin_datetime'] ?? $item['checkin_date'] ?? 'N/A',
                        'checkout_date' => $item['checkout_datetime'] ?? $item['checkout_date'] ?? null,
                        'status' => $item['status'] ?? 'unknown',
                        'source' => 'external' // Flag to identify source
                    ];
                }
            }
        }
        curl_close($ch);
    } catch (Exception $ex) {
        // Ignore external API errors to ensure local data still loads
    }

    // 2. Fetch from Local Database
    $sql = "SELECT * FROM direct_checkins ORDER BY id DESC";
    if ($db instanceof mysqli) {
        $result = $db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['source'] = 'local';
                $localData[] = $row;
            }
        }
    } elseif ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $row['source'] = 'local';
            $localData[] = $row;
        }
    }

    // 3. Merge and Return
    $combinedData = array_merge($localData, $externalData);

    echo json_encode([
        'status' => 'success',
        'records' => count($combinedData),
        'data' => $combinedData
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// ✅ Close connection if it's mysqli
if ($db instanceof mysqli) {
    $db->close();
}
?>