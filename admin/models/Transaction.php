<?php
// admin/models/Transaction.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../audit/AuditLog.php';

class Transaction {
    public static function create($data) {
        global $pdo;
        $billNo = isset($data['bill_id']) ? $data['bill_id'] : ($data['bill_no'] ?? '0');
        $status = $data['status'] ?? 'Paid';
        if ($status === 'Completed') $status = 'Paid';

        // Check columns of transactions table to insert correctly
        try {
            $stmt = $pdo->prepare("INSERT INTO transactions (bill_no, customer_name, amount, payment_method, status) VALUES (?, ?, ?, ?, ?)");
            $res = $stmt->execute([
                $billNo,
                $data['customer_name'],
                $data['amount'],
                $data['payment_method'],
                $status
            ]);
            
            if ($res) {
                $id = $pdo->lastInsertId();
                AuditLog::log("Created Transaction", "Transaction", $id, "Bill: $billNo, Amount: " . $data['amount']);
                return $id;
            }
        } catch (Exception $e) {
            AuditLog::log("Transaction Insert Error", "Transaction", 0, $e->getMessage());
        }
        return false;
    }

    public static function getAll() {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public static function getById($id) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
