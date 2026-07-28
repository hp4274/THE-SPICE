<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

// Get JSON input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $input['name'] ?? '';
    $email = $input['email'] ?? '';
    $mobile = $input['mobile'] ?? '';
    $dateInput = $input['date'] ?? '';
    $time = $input['time'] ?? '';
    if (empty($time) && isset($input['time_hour'])) {
        $min = $input['time_minute'] ?? '00';
        $time = $input['time_hour'] . ':' . $min;
    }
    if (empty($time)) {
        $time = '18:00';
    }
    $adults = isset($input['adults']) ? intval($input['adults']) : 0;
    $children = isset($input['children']) ? intval($input['children']) : 0;
    $requests = $input['requests'] ?? '';

    $date = date('Y-m-d');
    if (!empty($dateInput)) {
        $parsedDate = strtotime(str_replace('/', '-', $dateInput));
        if ($parsedDate !== false) {
            $date = date('Y-m-d', $parsedDate);
        }
    }

    $todayStr = date('Y-m-d');
    if (empty($name) || empty($mobile) || empty($date) || empty($time)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if ($date < $todayStr) {
        echo json_encode(['success' => false, 'message' => 'Reservations cannot be booked for past dates. Please select today or a future date.']);
        exit;
    }

    try {
        $guest_count = $adults + $children;
        if ($guest_count === 0 && !empty($input['guests'])) {
            $guest_count = intval($input['guests']);
        }
        $booking_id = 'BK' . date('Ymd') . rand(1000, 9999);
        
        $stmt = $pdo->prepare("INSERT INTO leads (customer_name, email, phone, special_request, adults, children, guest_count, booking_id, booking_date, booking_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $mobile, $requests, $adults, $children, $guest_count, $booking_id, $date, $time, 'New']);
        
        try {
            require_once __DIR__ . '/../services/SyncService.php';
            SyncService::broadcastEvent('new_lead', [
                'booking_id' => $booking_id,
                'customer_name' => $name,
                'phone' => $mobile,
                'email' => $email,
                'guest_count' => $guest_count,
                'booking_date' => $date,
                'booking_time' => date('g:i A', strtotime($time))
            ]);
        } catch (Exception $e) {
            // Ignore broadcast failure
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
