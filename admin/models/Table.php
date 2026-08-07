<?php
// admin/models/Table.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../audit/AuditLog.php';

class Table {
    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM tables ORDER BY table_number ASC");
        return $stmt->fetchAll();
    }

    public static function getById($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function updateStatus($id, $status, $reservationId = null) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE tables SET status = ?, current_reservation_id = ? WHERE id = ?");
        $res = $stmt->execute([$status, $reservationId, $id]);
        if ($res) {
            AuditLog::log("Updated Table Status", "Table", $id, "Status changed to $status");
        }
        return $res;
    }

    public static function getAvailable() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM tables WHERE status = 'Available' ORDER BY table_number ASC");
        return $stmt->fetchAll();
    }

    /**
     * Smart Availability check:
     * A table is available at $date and $time if:
     * 1. It is not Blocked/Maintenance/Merged/Cleaning
     * 2. No active (Upcoming, Confirmed, Arrived, Dining) reservation exists on this table within 2 hours (120 mins).
     * 3. If an earlier reservation was marked Completed/Cancelled (table freed early), it is available immediately!
     */
    public static function getAvailableForTime($date, $time, $excludeReservationId = null) {
        global $pdo;
        $sql = "SELECT t.* FROM tables t
                WHERE t.status NOT IN ('Blocked', 'Maintenance', 'Merged', 'Cleaning', 'Needs Cleaning')
                AND NOT EXISTS (
                    SELECT 1 FROM reservations r 
                    WHERE r.table_id = t.id 
                    AND r.reservation_date = :res_date 
                    AND r.status IN ('Upcoming', 'Confirmed', 'Arrived', 'Dining')
                    " . ($excludeReservationId ? "AND r.id != :exclude_id " : "") . "
                    AND ABS(TIMESTAMPDIFF(MINUTE, CONCAT(r.reservation_date, ' ', r.reservation_time), CONCAT(:res_date2, ' ', :res_time))) < 120
                )
                ORDER BY t.table_number ASC";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':res_date' => $date,
            ':res_date2' => $date,
            ':res_time' => $time
        ];
        if ($excludeReservationId) {
            $params[':exclude_id'] = $excludeReservationId;
        }
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>
