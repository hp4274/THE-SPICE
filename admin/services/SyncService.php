<?php
// admin/services/SyncService.php
require_once __DIR__ . '/../models/Lead.php';
require_once __DIR__ . '/../models/Reservation.php';
require_once __DIR__ . '/../models/Table.php';
require_once __DIR__ . '/../models/Bill.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../audit/AuditLog.php';

class SyncService {
    
    // Fixed Buffet Prices
    const ADULT_PRICE = 19.99;
    const CHILD_PRICE = 9.99;
    
    // Broadcast live event payload to Ratchet WebSocket server
    public static function broadcastEvent($event, $data = []) {
        try {
            $fp = @fsockopen("127.0.0.1", 8081, $errno, $errstr, 1);
            if ($fp) {
                $payload = json_encode([
                    'event' => $event,
                    'data' => $data,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                fwrite($fp, $payload);
                fclose($fp);
                return true;
            }
        } catch (\Throwable $e) {
            // Socket server offline, ignore silently
        }
        return false;
    }
    
    // 1. CONFIRM LEAD: Lead -> Reservation -> Reserve Table -> Pending Bill
    public static function confirmLead($leadId, $tableId) {
        $lead = Lead::getById($leadId);
        if (!$lead) return false;

        // Change Lead Status
        Lead::updateStatus($leadId, 'Confirmed', $tableId);

        // Create Reservation
        $resData = [
            'lead_id' => $leadId,
            'customer_name' => $lead['customer_name'],
            'phone' => $lead['phone'],
            'guests' => $lead['guest_count'],
            'reservation_date' => $lead['booking_date'],
            'reservation_time' => $lead['booking_time'],
            'table_id' => $tableId
        ];
        $resId = Reservation::create($resData);

        // Reserve table if reservation date is today
        if ($lead['booking_date'] == date('Y-m-d')) {
            Table::updateStatus($tableId, 'Reserved', $resId);
        }

        // Generate Pending Bill with FIXED Buffet Prices ($19.99 Adult, $9.99 Child)
        $billData = [
            'reservation_id' => $resId,
            'table_id' => $tableId,
            'customer_name' => $lead['customer_name']
        ];
        $billId = Bill::create($billData);
        
        $adults = isset($lead['adults']) && (int)$lead['adults'] > 0 ? (int)$lead['adults'] : 0;
        $children = isset($lead['children']) ? (int)$lead['children'] : 0;
        if ($adults == 0 && $children == 0) {
            $adults = (int)($lead['guest_count'] ?: 2);
        }

        $subtotal = ($adults * self::ADULT_PRICE) + ($children * self::CHILD_PRICE);
        $gst = round($subtotal * 0.085, 2);
        Bill::updateTotals($billId, $subtotal, 0, $gst);
        
        Reservation::updateBillId($resId, $billId);
        
        AuditLog::log("Sync Confirm Lead", "System", $leadId, "Generated Res: $resId, Table: $tableId, Bill: $billId ($adults Adults @ $19.99, $children Children @ $9.99)");
        return true;
    }

    // 2. REJECT LEAD: Lead -> Rejected -> Free Table -> Cancel Pending Bill
    public static function rejectLead($leadId, $reason = 'Unavailable') {
        $lead = Lead::getById($leadId);
        if (!$lead) return false;

        Lead::updateStatus($leadId, 'Rejected', null, $reason);

        global $pdo;
        $stmt = $pdo->prepare("SELECT id, table_id, bill_id FROM reservations WHERE lead_id = ?");
        $stmt->execute([$leadId]);
        $res = $stmt->fetch();

        if ($res) {
            Reservation::updateStatus($res['id'], 'Cancelled');
            if ($res['table_id']) {
                Table::updateStatus($res['table_id'], 'Available', null);
            }
            if ($res['bill_id']) {
                Bill::updateStatus($res['bill_id'], 'Cancelled');
            }
        }

        AuditLog::log("Sync Reject Lead", "System", $leadId, "Rejected lead & freed resources");
        return true;
    }

    // 3. CANCEL LEAD: Lead -> Cancelled -> Free Table -> Cancel Bill
    public static function cancelLead($leadId) {
        $lead = Lead::getById($leadId);
        if (!$lead) return false;

        Lead::updateStatus($leadId, 'Cancelled');
        
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, table_id, bill_id FROM reservations WHERE lead_id = ?");
        $stmt->execute([$leadId]);
        $res = $stmt->fetch();
        
        if ($res) {
            Reservation::updateStatus($res['id'], 'Cancelled');
            if ($res['table_id']) {
                Table::updateStatus($res['table_id'], 'Available', null);
            }
            if ($res['bill_id']) {
                Bill::updateStatus($res['bill_id'], 'Cancelled');
            }
        }
        
        AuditLog::log("Sync Cancel Lead", "System", $leadId, "Cancelled related Res and Bill");
        return true;
    }

    // 4. NO SHOW LEAD / RESERVATION
    public static function noShowLead($leadId) {
        $lead = Lead::getById($leadId);
        if (!$lead) return false;

        Lead::updateStatus($leadId, 'No Show');

        global $pdo;
        $stmt = $pdo->prepare("SELECT id, table_id, bill_id FROM reservations WHERE lead_id = ?");
        $stmt->execute([$leadId]);
        $res = $stmt->fetch();

        if ($res) {
            Reservation::updateStatus($res['id'], 'No Show');
            if ($res['table_id']) {
                Table::updateStatus($res['table_id'], 'Available', null);
            }
            if ($res['bill_id']) {
                Bill::updateStatus($res['bill_id'], 'Cancelled');
            }
        }

        AuditLog::log("Sync No Show", "System", $leadId, "Marked lead as No Show");
        return true;
    }

    // 5. DELETE LEAD: Delete Lead + Related Reservation + Bill + Free Table
    public static function deleteLead($leadId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, table_id, bill_id FROM reservations WHERE lead_id = ?");
        $stmt->execute([$leadId]);
        $res = $stmt->fetch();

        if ($res) {
            if ($res['table_id']) {
                Table::updateStatus($res['table_id'], 'Available', null);
            }
            if ($res['bill_id']) {
                $delBill = $pdo->prepare("DELETE FROM bills WHERE id = ?");
                $delBill->execute([$res['bill_id']]);
            }
            $delRes = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
            $delRes->execute([$res['id']]);
        }

        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);

        AuditLog::log("Sync Delete Lead", "System", $leadId, "Deleted lead and related records");
        return true;
    }
    
    // 6. CANCEL RESERVATION
    public static function cancelReservation($reservationId) {
        $res = Reservation::getById($reservationId);
        if (!$res) return false;

        Reservation::updateStatus($reservationId, 'Cancelled');
        if ($res['table_id']) {
            Table::updateStatus($res['table_id'], 'Available', null);
        }
        if ($res['bill_id']) {
            Bill::updateStatus($res['bill_id'], 'Cancelled');
        }
        if ($res['lead_id']) {
            Lead::updateStatus($res['lead_id'], 'Cancelled');
        }
        
        AuditLog::log("Sync Cancel Reservation", "System", $reservationId, "Cancelled reservation, freed table and bill");
        return true;
    }

    // 7. CUSTOMER ARRIVES
    public static function customerArrives($reservationId) {
        $res = Reservation::getById($reservationId);
        if (!$res) return false;

        Reservation::updateStatus($reservationId, 'Arrived');
        AuditLog::log("Sync Customer Arrives", "System", $reservationId, "Customer marked Arrived");
        return true;
    }

    // 8. SEAT CUSTOMER: Arrived -> Dining -> Table Occupied -> Bill Active
    public static function seatCustomer($reservationId) {
        $res = Reservation::getById($reservationId);
        if (!$res) return false;

        Reservation::updateStatus($reservationId, 'Dining');
        if ($res['table_id']) {
            Table::updateStatus($res['table_id'], 'Occupied', $reservationId);
        }
        if ($res['bill_id']) {
            Bill::updateStatus($res['bill_id'], 'Active');
        }
        
        AuditLog::log("Sync Seat Customer", "System", $reservationId, "Customer seated at table");
        return true;
    }

    // 9. CHANGE TABLE: Move Reservation + Bill to New Table, Free Old Table
    public static function changeTable($reservationId, $newTableId) {
        $res = Reservation::getById($reservationId);
        if (!$res) return false;

        $oldTableId = $res['table_id'];
        if ($oldTableId && $oldTableId != $newTableId) {
            Table::updateStatus($oldTableId, 'Available', null);
        }

        // Determine new table status
        $tableStatus = ($res['status'] === 'Dining') ? 'Occupied' : 'Reserved';
        Table::updateStatus($newTableId, $tableStatus, $reservationId);

        // Update Reservation table_id
        global $pdo;
        $stmt = $pdo->prepare("UPDATE reservations SET table_id = ? WHERE id = ?");
        $stmt->execute([$newTableId, $reservationId]);

        // Update Bill table_id
        if ($res['bill_id']) {
            $stmtBill = $pdo->prepare("UPDATE bills SET table_id = ? WHERE id = ?");
            $stmtBill->execute([$newTableId, $res['bill_id']]);
        }

        AuditLog::log("Sync Change Table", "System", $reservationId, "Moved from Table $oldTableId to Table $newTableId");
        return true;
    }

    // 10. WALK-IN CUSTOMER: Available Table -> Create Lead -> Create Res (Dining) -> Occupy Table -> Generate Active Bill ($19.99 Adult, $9.99 Child)
    public static function walkinCustomer($tableId, $customerName, $phone, $guests, $adults = 0, $children = 0) {
        $adults = $adults > 0 ? (int)$adults : (int)$guests;
        $children = (int)$children;

        $leadId = Lead::create([
            'customer_name' => $customerName,
            'phone' => $phone,
            'email' => 'walkin@thespice.com',
            'guest_count' => ($adults + $children),
            'booking_date' => date('Y-m-d'),
            'booking_time' => date('H:i'),
            'booking_source' => 'Walk-in',
            'special_request' => 'Walk-in Customer'
        ]);

        Lead::updateStatus($leadId, 'Confirmed', $tableId);

        $resId = Reservation::create([
            'lead_id' => $leadId,
            'customer_name' => $customerName,
            'phone' => $phone,
            'guests' => ($adults + $children),
            'reservation_date' => date('Y-m-d'),
            'reservation_time' => date('H:i'),
            'table_id' => $tableId
        ]);

        Reservation::updateStatus($resId, 'Dining');
        Table::updateStatus($tableId, 'Occupied', $resId);

        $billId = Bill::create([
            'reservation_id' => $resId,
            'table_id' => $tableId,
            'customer_name' => $customerName
        ]);

        $subtotal = ($adults * self::ADULT_PRICE) + ($children * self::CHILD_PRICE);
        $gst = round($subtotal * 0.085, 2);
        Bill::updateTotals($billId, $subtotal, 0, $gst);
        Bill::updateStatus($billId, 'Active');
        Reservation::updateBillId($resId, $billId);

        AuditLog::log("Sync Walk-in", "System", $resId, "Seated walk-in at Table $tableId ($adults Adults, $children Children)");
        return true;
    }

    // 11. COMPLETE PAYMENT: Bill Paid -> Reservation Completed -> Table Cleaning -> Log Transaction
    public static function completePayment($billId, $paymentMethod, $amount) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        if (!$bill) return false;
        
        Bill::updateStatus($billId, 'Paid');
        
        Transaction::create([
            'bill_id' => $billId,
            'customer_name' => $bill['customer_name'],
            'amount' => $amount ?: $bill['grand_total'],
            'payment_method' => $paymentMethod,
            'status' => 'Paid'
        ]);
        
        if ($bill['reservation_id']) {
            Reservation::updateStatus($bill['reservation_id'], 'Completed');
        }
        
        if ($bill['table_id']) {
            Table::updateStatus($bill['table_id'], 'Cleaning', null);
        }
        
        AuditLog::log("Sync Payment", "System", $billId, "Payment completed, table set to cleaning");
        return true;
    }

    // 12. CLEAN TABLE: Table Cleaning -> Available
    public static function cleanTable($tableId) {
        Table::updateStatus($tableId, 'Available', null);
        AuditLog::log("Sync Table Cleaned", "System", $tableId, "Table cleaned and set to Available");
        return true;
    }

    // 13. BLOCK / UNBLOCK TABLE
    public static function blockTable($tableId) {
        Table::updateStatus($tableId, 'Blocked', null);
        AuditLog::log("Sync Block Table", "System", $tableId, "Table blocked");
        return true;
    }

    public static function unblockTable($tableId) {
        Table::updateStatus($tableId, 'Available', null);
        AuditLog::log("Sync Unblock Table", "System", $tableId, "Table unblocked and set to Available");
        return true;
    }

    // 14. REFUND BILL: Log Refund Transaction & Update Bill/Sales
    public static function refundBill($billId, $amount, $reason = 'Customer Request') {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        if (!$bill) return false;

        Bill::updateStatus($billId, 'Cancelled');

        Transaction::create([
            'bill_id' => $billId,
            'customer_name' => $bill['customer_name'],
            'amount' => -$amount,
            'payment_method' => 'Refund',
            'status' => 'Refunded',
            'reference_number' => $reason
        ]);

        AuditLog::log("Sync Refund", "System", $billId, "Refund of $$amount issued: $reason");
        return true;
    }
}
?>
