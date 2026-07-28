<?php
// admin/models/Bill.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../audit/AuditLog.php';

class Bill {
    public static function create($data) {
        global $pdo;
        $billNum = 'INV' . date('Ymd') . rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO bills (bill_number, reservation_id, table_id, customer_name, status) VALUES (?, ?, ?, ?, ?)");
        $res = $stmt->execute([
            $billNum,
            $data['reservation_id'] ?? null,
            $data['table_id'] ?? null,
            $data['customer_name'],
            'Pending'
        ]);
        
        if ($res) {
            $id = $pdo->lastInsertId();
            AuditLog::log("Created Bill", "Bill", $id, "Bill Number: $billNum");
            return $id;
        }
        return false;
    }

    public static function updateStatus($id, $status) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE bills SET status = ? WHERE id = ?");
        $res = $stmt->execute([$status, $id]);
        if ($res) {
            AuditLog::log("Updated Bill Status", "Bill", $id, "Status changed to $status");
        }
        return $res;
    }

    public static function updateTotals($id, $subtotal, $discount, $gst) {
        global $pdo;
        $grandTotal = $subtotal - $discount + $gst;
        $stmt = $pdo->prepare("UPDATE bills SET subtotal = ?, discount = ?, gst = ?, grand_total = ? WHERE id = ?");
        return $stmt->execute([$subtotal, $discount, $gst, $grandTotal, $id]);
    }
    
    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM bills ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public static function getById($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
?>
