<?php
// admin/models/Reservation.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../audit/AuditLog.php';

class Reservation {
    public static function create($data) {
        global $pdo;
        $resNum = 'RES' . date('Ymd') . rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO reservations (reservation_number, lead_id, customer_name, phone, guests, reservation_date, reservation_time, table_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $res = $stmt->execute([
            $resNum,
            $data['lead_id'] ?? null,
            $data['customer_name'],
            $data['phone'],
            $data['guests'],
            $data['reservation_date'],
            $data['reservation_time'],
            $data['table_id'] ?? null,
            'Upcoming'
        ]);
        
        if ($res) {
            $id = $pdo->lastInsertId();
            AuditLog::log("Created Reservation", "Reservation", $id, "Res Number: $resNum");
            return $id;
        }
        return false;
    }

    public static function getToday() {
        global $pdo;
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_date = ? ORDER BY reservation_time ASC");
        $stmt->execute([$today]);
        return $stmt->fetchAll();
    }
    
    public static function getById($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function updateStatus($id, $status) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $res = $stmt->execute([$status, $id]);
        if ($res) {
            AuditLog::log("Updated Reservation Status", "Reservation", $id, "Status changed to $status");
        }
        return $res;
    }
    
    public static function updateBillId($id, $billId) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE reservations SET bill_id = ? WHERE id = ?");
        return $stmt->execute([$billId, $id]);
    }
    
    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM reservations ORDER BY reservation_date DESC, reservation_time DESC");
        return $stmt->fetchAll();
    }
}
?>
