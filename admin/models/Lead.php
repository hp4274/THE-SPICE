<?php
// admin/models/Lead.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../audit/AuditLog.php';

class Lead {
    public static function create($data) {
        global $pdo;
        $bookingId = 'LD' . date('Ymd') . rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO leads (booking_id, customer_name, phone, email, guest_count, booking_date, booking_time, occasion, special_request, booking_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $res = $stmt->execute([
            $bookingId,
            $data['customer_name'],
            $data['phone'],
            $data['email'] ?? null,
            $data['guest_count'],
            $data['booking_date'],
            $data['booking_time'],
            $data['occasion'] ?? null,
            $data['special_request'] ?? null,
            $data['booking_source'] ?? 'Online'
        ]);
        if ($res) {
            $id = $pdo->lastInsertId();
            AuditLog::log("Created Lead", "Lead", $id, "Booking ID: $bookingId");
            return $id;
        }
        return false;
    }

    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public static function getById($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function updateStatus($id, $status, $tableId = null, $reason = null) {
        global $pdo;
        if ($tableId === null) {
            $stmt = $pdo->prepare("UPDATE leads SET status = ?, rejection_reason = COALESCE(?, rejection_reason) WHERE id = ?");
            $res = $stmt->execute([$status, $reason, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE leads SET status = ?, assigned_table_id = ?, rejection_reason = ? WHERE id = ?");
            $res = $stmt->execute([$status, $tableId, $reason, $id]);
        }
        if ($res) {
            AuditLog::log("Updated Lead Status", "Lead", $id, "Status changed to $status");
        }
        return $res;
    }
}
?>
