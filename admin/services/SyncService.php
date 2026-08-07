<?php
// admin/services/SyncService.php
date_default_timezone_set('Asia/Kolkata');
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
    
    // Broadcast live event payload (Legacy WebSocket wrapper)
    public static function broadcastEvent($event, $data = []) {
        return true;
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

    // 4b. MARK LEAD CONTACTED
    public static function contactLead($leadId) {
        $lead = Lead::getById($leadId);
        if (!$lead) return false;

        Lead::updateStatus($leadId, 'Contacted');
        AuditLog::log("Sync Contacted", "System", $leadId, "Marked lead {$lead['booking_id']} as Contacted");
        self::broadcastEvent('lead_status_updated', [
            'lead_id' => $leadId,
            'status' => 'Contacted',
            'customer_name' => $lead['customer_name']
        ]);
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

    // 6b. NO SHOW RESERVATION (distinct from Cancelled — keeps the reason in reporting)
    public static function noShowReservation($reservationId) {
        $res = Reservation::getById($reservationId);
        if (!$res) return false;

        Reservation::updateStatus($reservationId, 'No Show');
        if ($res['table_id']) {
            Table::updateStatus($res['table_id'], 'Available', null);
        }
        if ($res['bill_id']) {
            Bill::updateStatus($res['bill_id'], 'Cancelled');
        }
        if ($res['lead_id']) {
            Lead::updateStatus($res['lead_id'], 'No Show');
        }

        AuditLog::log("Sync No Show Reservation", "Reservation", $reservationId, "Marked No Show, freed table and bill");
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
        
        // Prevent duplicate transaction entry for the same bill
        $checkStmt = $pdo->prepare("SELECT id FROM transactions WHERE bill_id = ? AND status = 'Paid'");
        $checkStmt->execute([$billId]);
        if (!$checkStmt->fetch()) {
            Transaction::create([
                'bill_id' => $billId,
                'customer_name' => $bill['customer_name'],
                'amount' => $amount ?: $bill['grand_total'],
                'payment_method' => $paymentMethod,
                'status' => 'Paid'
            ]);
        }
        
        if ($bill['reservation_id']) {
            Reservation::updateStatus($bill['reservation_id'], 'Completed');
            $res = Reservation::getById($bill['reservation_id']);
            if ($res && !empty($res['lead_id'])) {
                Lead::updateStatus($res['lead_id'], 'Completed');
            }
        }
        
        if ($bill['table_id']) {
            Table::updateStatus($bill['table_id'], 'Cleaning', null);
        }
        
        AuditLog::log("Sync Payment", "System", $billId, "Payment completed, table set to cleaning");
        return true;
    }

    // 11b. RELEASE TABLE: Close out an occupied table (complete reservation, settle/cancel bill, send to Cleaning)
    // Returns ['success' => bool, 'message' => string, 'bill_id' => int|null]
    public static function releaseTable($tableId, $force = false) {
        global $pdo;

        $table = Table::getById($tableId);
        if (!$table) {
            return ['success' => false, 'message' => 'Table not found'];
        }

        // Find the reservation currently holding this table
        $resId = $table['current_reservation_id'] ?: null;
        if (!$resId) {
            $stmt = $pdo->prepare("SELECT id FROM reservations WHERE table_id = ? AND status IN ('Arrived', 'Dining') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$tableId]);
            $row = $stmt->fetch();
            $resId = $row ? $row['id'] : null;
        }

        $res = $resId ? Reservation::getById($resId) : null;
        $bill = ($res && !empty($res['bill_id'])) ? Bill::getById($res['bill_id']) : null;

        // Block the release while money is still owed, unless explicitly forced
        if (!$force && $bill && in_array($bill['status'], ['Pending', 'Active', 'Payment Pending'])) {
            return [
                'success' => false,
                'message' => "Bill #{$bill['id']} is unpaid ($" . number_format($bill['grand_total'], 2) . "). Settle the payment first.",
                'bill_id' => (int)$bill['id']
            ];
        }

        if ($res) {
            Reservation::updateStatus($res['id'], 'Completed');
            if (!empty($res['lead_id'])) {
                Lead::updateStatus($res['lead_id'], 'Completed');
            }
        }
        if ($bill && in_array($bill['status'], ['Pending', 'Active', 'Payment Pending'])) {
            Bill::updateStatus($bill['id'], 'Cancelled');
        }

        Table::updateStatus($tableId, 'Cleaning', null);

        AuditLog::log("Sync Release Table", "Table", $tableId, "Table released" . ($res ? ", Res {$res['id']} completed" : "") . ($force ? " (forced)" : ""));
        return ['success' => true, 'message' => 'Table released & sent for cleaning'];
    }

    // 12. CLEAN TABLE: Table Cleaning -> Available & Unmerge
    public static function cleanTable($tableId) {
        global $pdo;
        Table::updateStatus($tableId, 'Available', null);
        
        // Unmerge any secondary tables attached to this table
        $stmt = $pdo->prepare("UPDATE tables SET status = 'Available', merged_with = NULL WHERE merged_with = ?");
        $stmt->execute([$tableId]);

        // If this table itself was merged into another table, reset merged_with
        $stmt2 = $pdo->prepare("UPDATE tables SET merged_with = NULL WHERE id = ?");
        $stmt2->execute([$tableId]);

        AuditLog::log("Sync Table Cleaned", "System", $tableId, "Table cleaned and set to Available");
        return true;
    }

    // 12b. CLEAN ALL DIRTY TABLES in one round trip
    public static function cleanAllTables() {
        global $pdo;
        $stmt = $pdo->query("SELECT id FROM tables WHERE status IN ('Cleaning', 'Needs Cleaning')");
        $ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($ids as $id) {
            self::cleanTable($id);
        }
        return count($ids);
    }

    // 13. MERGE / UNMERGE TABLES
    public static function mergeTables($primaryTableId, $secondaryTableId) {
        global $pdo;
        if ($primaryTableId == $secondaryTableId) {
            return ['success' => false, 'message' => 'Primary and secondary tables must be different'];
        }

        $primary = Table::getById($primaryTableId);
        $secondary = Table::getById($secondaryTableId);
        if (!$primary || !$secondary) {
            return ['success' => false, 'message' => 'Table not found'];
        }
        if ($secondary['status'] !== 'Available') {
            return ['success' => false, 'message' => "Table {$secondary['table_number']} is {$secondary['status']} — only Available tables can be merged in"];
        }
        if (in_array($primary['status'], ['Merged', 'Blocked', 'Maintenance'])) {
            return ['success' => false, 'message' => "Table {$primary['table_number']} is {$primary['status']} and cannot host a merge"];
        }

        // Set secondary table status to 'Merged' and linked to primary table
        $stmt = $pdo->prepare("UPDATE tables SET status = 'Merged', merged_with = ? WHERE id = ?");
        $stmt->execute([$primaryTableId, $secondaryTableId]);

        $combined = (int)$primary['capacity'] + (int)$secondary['capacity'];
        AuditLog::log("Sync Merge Tables", "Table", $primaryTableId, "Merged Table {$secondary['table_number']} into Table {$primary['table_number']} (combined $combined seats)");
        return ['success' => true, 'message' => "Merged — Table {$primary['table_number']} now seats $combined guests"];
    }

    public static function unmergeTable($secondaryTableId) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE tables SET status = 'Available', merged_with = NULL WHERE id = ?");
        $res = $stmt->execute([$secondaryTableId]);
        AuditLog::log("Sync Unmerge Table", "System", $secondaryTableId, "Unmerged Table");
        return $res;
    }

    // 14. BLOCK / UNBLOCK TABLE
    public static function blockTable($tableId) {
        $table = Table::getById($tableId);
        if (!$table) {
            return ['success' => false, 'message' => 'Table not found'];
        }
        if (in_array($table['status'], ['Occupied', 'Reserved', 'Dining', 'Billing'])) {
            return ['success' => false, 'message' => "Table {$table['table_number']} is {$table['status']} — free it before blocking"];
        }
        Table::updateStatus($tableId, 'Blocked', null);
        AuditLog::log("Sync Block Table", "Table", $tableId, "Table blocked");
        return ['success' => true, 'message' => "Table {$table['table_number']} blocked"];
    }

    // 14b. DELETE TABLE (guarded — never orphan a live reservation or bill)
    public static function deleteTable($tableId) {
        global $pdo;
        $table = Table::getById($tableId);
        if (!$table) {
            return ['success' => false, 'message' => 'Table not found'];
        }
        if (!in_array($table['status'], ['Available', 'Blocked', 'Maintenance'])) {
            return ['success' => false, 'message' => "Table {$table['table_number']} is {$table['status']} — only free tables can be deleted"];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE table_id = ? AND status IN ('Upcoming', 'Confirmed', 'Arrived', 'Dining')");
        $stmt->execute([$tableId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => "Table {$table['table_number']} still has active reservations — cancel or move them first"];
        }

        // Detach any tables merged into this one
        $pdo->prepare("UPDATE tables SET status = 'Available', merged_with = NULL WHERE merged_with = ?")->execute([$tableId]);

        $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
        $stmt->execute([$tableId]);

        AuditLog::log("Sync Delete Table", "Table", $tableId, "Deleted Table {$table['table_number']}");
        return ['success' => true, 'message' => "Table {$table['table_number']} deleted"];
    }

    // 14c. GLOBAL SEARCH across leads, reservations, tables and bills
    public static function globalSearch($query, $limit = 5) {
        global $pdo;
        $query = trim((string)$query);
        if ($query === '') return [];

        $like = '%' . $query . '%';
        $results = [];

        $sets = [
            [
                'type' => 'Reservation',
                'page' => 'reservations.php',
                'sql'  => "SELECT id, reservation_number AS ref, customer_name AS title, CONCAT(reservation_date, ' ', TIME_FORMAT(reservation_time, '%h:%i %p')) AS sub, status
                           FROM reservations
                           WHERE customer_name LIKE :q OR phone LIKE :q2 OR reservation_number LIKE :q3
                           ORDER BY id DESC LIMIT $limit"
            ],
            [
                'type' => 'Lead',
                'page' => 'leads.php',
                'sql'  => "SELECT id, booking_id AS ref, customer_name AS title, CONCAT(booking_date, ' ', TIME_FORMAT(booking_time, '%h:%i %p')) AS sub, status
                           FROM leads
                           WHERE customer_name LIKE :q OR phone LIKE :q2 OR booking_id LIKE :q3
                           ORDER BY id DESC LIMIT $limit"
            ],
            [
                'type' => 'Table',
                'page' => 'tables.php',
                'sql'  => "SELECT id, table_number AS ref, CONCAT('Table ', table_number) AS title, CONCAT(capacity, ' Seats') AS sub, status
                           FROM tables
                           WHERE table_number LIKE :q OR status LIKE :q2 OR CAST(capacity AS CHAR) LIKE :q3
                           ORDER BY table_number ASC LIMIT $limit"
            ],
            [
                'type' => 'Bill',
                'page' => 'bills.php',
                'sql'  => "SELECT id, bill_number AS ref, customer_name AS title, CONCAT('$', FORMAT(grand_total, 2)) AS sub, status
                           FROM bills
                           WHERE customer_name LIKE :q OR bill_number LIKE :q2 OR CAST(grand_total AS CHAR) LIKE :q3
                           ORDER BY id DESC LIMIT $limit"
            ],
        ];

        foreach ($sets as $set) {
            try {
                $stmt = $pdo->prepare($set['sql']);
                $stmt->execute([':q' => $like, ':q2' => $like, ':q3' => $like]);
                foreach ($stmt->fetchAll() as $row) {
                    $row['type'] = $set['type'];
                    $row['page'] = $set['page'];
                    $results[] = $row;
                }
            } catch (\Throwable $e) {
                // Skip a set whose table/columns are missing on this install
            }
        }

        return $results;
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

    // 15. AUTOMATIC NO-SHOW CHECKER
    public static function checkAutoNoShows() {
        global $pdo;
        if (!$pdo) return 0;
        
        try {
            // Get grace period from settings (default: 5 minutes)
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key IN ('no_show_grace_mins', 'auto_cancel_mins') ORDER BY id DESC");
            $graceMins = 5;
            if ($stmt && $row = $stmt->fetch()) {
                $graceMins = intval($row['setting_value']);
            }
            if ($graceMins <= 0) $graceMins = 5;

            $nowTimestamp = time();
            $updatedCount = 0;

            // A. Check CONFIRMED LEADS (Accepted & reserved table)
            $leadStmt = $pdo->query("SELECT * FROM leads WHERE status = 'Confirmed'");
            $leads = $leadStmt ? $leadStmt->fetchAll() : [];

            foreach ($leads as $lead) {
                if (empty($lead['booking_date']) || empty($lead['booking_time'])) continue;
                
                $bookingDateTimeStr = $lead['booking_date'] . ' ' . $lead['booking_time'];
                $bookingTimestamp = strtotime($bookingDateTimeStr);
                
                if ($bookingTimestamp !== false) {
                    $noShowThreshold = $bookingTimestamp + ($graceMins * 60);
                    if ($nowTimestamp >= $noShowThreshold) {
                        $leadId = $lead['id'];
                        Lead::updateStatus($leadId, 'No Show');

                        if (!empty($lead['assigned_table_id'])) {
                            Table::updateStatus($lead['assigned_table_id'], 'Available', null);
                        }

                        // Also update linked reservation if exists
                        $resStmt = $pdo->prepare("SELECT id FROM reservations WHERE lead_id = ? AND status IN ('Upcoming', 'Arrived')");
                        $resStmt->execute([$leadId]);
                        $res = $resStmt->fetch();
                        if ($res) {
                            Reservation::updateStatus($res['id'], 'No Show');
                        }

                        AuditLog::log("Auto No Show", "System", $leadId, "Lead {$lead['booking_id']} marked No Show (booking: $bookingDateTimeStr, grace: {$graceMins}m)");
                        
                        self::broadcastEvent('lead_status_updated', [
                            'lead_id' => $leadId,
                            'status' => 'No Show',
                            'customer_name' => $lead['customer_name']
                        ]);

                        $updatedCount++;
                    }
                }
            }

            // B. Check RESERVATIONS with status 'Upcoming'
            $resStmt = $pdo->query("SELECT * FROM reservations WHERE status = 'Upcoming'");
            $reservations = $resStmt ? $resStmt->fetchAll() : [];

            foreach ($reservations as $res) {
                if (empty($res['reservation_date']) || empty($res['reservation_time'])) continue;

                $resDateTimeStr = $res['reservation_date'] . ' ' . $res['reservation_time'];
                $resTimestamp = strtotime($resDateTimeStr);

                if ($resTimestamp !== false) {
                    $noShowThreshold = $resTimestamp + ($graceMins * 60);
                    if ($nowTimestamp >= $noShowThreshold) {
                        $resId = $res['id'];
                        Reservation::updateStatus($resId, 'No Show');

                        if (!empty($res['table_id'])) {
                            Table::updateStatus($res['table_id'], 'Available', null);
                        }

                        AuditLog::log("Auto No Show", "System", $resId, "Reservation {$res['reservation_number']} marked No Show (time: $resDateTimeStr, grace: {$graceMins}m)");

                        self::broadcastEvent('reservation_status_updated', [
                            'reservation_id' => $resId,
                            'status' => 'No Show',
                            'customer_name' => $res['customer_name']
                        ]);

                        $updatedCount++;
                    }
                }
            }

            return $updatedCount;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // 16. SYNC POLL STATE (For hosting environments without WebSockets)
    public static function pollSyncState() {
        global $pdo;
        
        // 1. Run automatic no-show checker
        $noShowsUpdated = self::checkAutoNoShows();

        // 2. Fetch state summary numbers & latest audit ID
        $latestAuditId = 0;
        try {
            $stmt = $pdo->query("SELECT MAX(id) as max_id FROM audit_log");
            if ($stmt) {
                $latestAuditId = intval($stmt->fetchColumn() ?: 0);
            }
        } catch (\Throwable $e) {}

        $leadCount = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM leads");
            if ($stmt) {
                $leadCount = intval($stmt->fetchColumn() ?: 0);
            }
        } catch (\Throwable $e) {}

        $tableState = '';
        try {
            $stmt = $pdo->query("SELECT GROUP_CONCAT(CONCAT(id, '-', status, '-', IFNULL(merged_with, 0)) ORDER BY id SEPARATOR '|') FROM tables");
            if ($stmt) {
                $tableState = $stmt->fetchColumn() ?: '';
            }
        } catch (\Throwable $e) {}

        return [
            'success' => true,
            'no_shows_updated' => $noShowsUpdated,
            'latest_audit_id' => $latestAuditId,
            'lead_count' => $leadCount,
            'table_state' => md5($tableState),
            'timestamp' => time()
        ];
    }
}
?>
