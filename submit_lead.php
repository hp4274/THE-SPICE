<?php
header('Content-Type: application/json');
require_once 'admin/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? $_POST['mobile'] ?? '';
    $message = $_POST['message'] ?? $_POST['requests'] ?? '';
    
    $dateInput = $_POST['date'] ?? '';
    $date = date('Y-m-d');
    if (!empty($dateInput)) {
        $parsed = strtotime(str_replace('/', '-', $dateInput));
        if ($parsed !== false) {
            $date = date('Y-m-d', $parsed);
        }
    }
    
    $time = $_POST['time'] ?? '';
    if (empty($time) && isset($_POST['time_hour'])) {
        $min = $_POST['time_minute'] ?? '00';
        $time = $_POST['time_hour'] . ':' . $min;
    }
    if (empty($time)) {
        $time = '18:00';
    }

    $adults = isset($_POST['adults']) ? intval($_POST['adults']) : 0;
    $children = isset($_POST['children']) ? intval($_POST['children']) : 0;

    $todayStr = date('Y-m-d');
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if (!empty($date) && $date < $todayStr) {
        echo json_encode(['success' => false, 'message' => 'Reservations cannot be booked for past dates. Please select today or a future date.']);
        exit;
    }

    try {
        $guest_count = $adults + $children;
        if ($guest_count === 0 && !empty($_POST['guests'])) {
            $guest_count = intval($_POST['guests']);
        }
        $booking_id = 'BK' . date('Ymd') . rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO leads (customer_name, email, phone, special_request, adults, children, guest_count, booking_id, booking_date, booking_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $message, $adults, $children, $guest_count, $booking_id, $date, $time, 'New']);
        
        // Trigger Ratchet Live WebSocket Notification to Admin Panel
        require_once 'admin/services/SyncService.php';
        SyncService::broadcastEvent('new_lead', [
            'booking_id' => $booking_id,
            'customer_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'guest_count' => $guest_count,
            'booking_date' => $date,
            'booking_time' => date('g:i A', strtotime($time))
        ]);

        echo json_encode(['success' => true, 'message' => 'Thank you! Your message has been sent.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
