<?php
// admin/audit/AuditLog.php
require_once __DIR__ . '/../includes/db.php';

class AuditLog {
    public static function log($action, $entityType, $entityId, $details = '') {
        global $pdo;
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("INSERT INTO audit_log (action, entity_type, entity_id, details) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$action, $entityType, $entityId, $details]);
    }
    
    public static function getLogs($limit = 50) {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT " . (int)$limit);
        return $stmt->fetchAll();
    }
}
?>
