<?php
// admin/api/router.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/SyncService.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'sync.poll':
            echo json_encode(SyncService::pollSyncState());
            break;

        // --- LEAD ENDPOINTS ---
        case 'leads.confirm':
            $leadId = $input['lead_id'] ?? 0;
            $tableId = $input['table_id'] ?? 0;
            if (!$leadId || !$tableId) {
                echo json_encode(['success' => false, 'message' => 'Missing lead_id or table_id']);
                break;
            }
            $res = SyncService::confirmLead($leadId, $tableId);
            echo json_encode(['success' => $res, 'message' => 'Lead confirmed & table assigned!']);
            break;

        case 'leads.reject':
            $leadId = $input['lead_id'] ?? 0;
            $reason = $input['reason'] ?? 'Unavailable';
            $res = SyncService::rejectLead($leadId, $reason);
            echo json_encode(['success' => $res, 'message' => 'Lead rejected']);
            break;
            
        case 'leads.cancel':
            $leadId = $input['lead_id'] ?? 0;
            $res = SyncService::cancelLead($leadId);
            echo json_encode(['success' => $res, 'message' => 'Lead cancelled']);
            break;

        case 'leads.noshow':
            $leadId = $input['lead_id'] ?? 0;
            $res = SyncService::noShowLead($leadId);
            echo json_encode(['success' => $res, 'message' => 'Lead marked No Show']);
            break;

        case 'leads.contacted':
            $leadId = $input['lead_id'] ?? 0;
            $res = SyncService::contactLead($leadId);
            echo json_encode(['success' => $res, 'message' => 'Lead marked Contacted']);
            break;

        case 'leads.delete':
            $leadId = $input['lead_id'] ?? 0;
            $res = SyncService::deleteLead($leadId);
            echo json_encode(['success' => $res, 'message' => 'Lead deleted']);
            break;
            
        // --- RESERVATION ENDPOINTS ---
        case 'reservations.arrive':
            $resId = $input['reservation_id'] ?? 0;
            $res = SyncService::customerArrives($resId);
            echo json_encode(['success' => $res, 'message' => 'Customer marked Arrived']);
            break;

        case 'reservations.seat':
            $resId = $input['reservation_id'] ?? 0;
            $res = SyncService::seatCustomer($resId);
            echo json_encode(['success' => $res, 'message' => 'Customer seated at table']);
            break;

        case 'reservations.change_table':
            $resId = $input['reservation_id'] ?? 0;
            $newTableId = $input['new_table_id'] ?? 0;
            if (!$resId || !$newTableId) {
                echo json_encode(['success' => false, 'message' => 'Missing reservation_id or new_table_id']);
                break;
            }
            $res = SyncService::changeTable($resId, $newTableId);
            echo json_encode(['success' => $res, 'message' => 'Table transferred successfully!']);
            break;
            
        case 'reservations.cancel':
            $resId = $input['reservation_id'] ?? 0;
            $res = SyncService::cancelReservation($resId);
            echo json_encode(['success' => $res, 'message' => 'Reservation cancelled']);
            break;

        case 'reservations.noshow':
            $resId = $input['reservation_id'] ?? 0;
            $res = SyncService::noShowReservation($resId);
            echo json_encode(['success' => $res, 'message' => 'Reservation marked No Show']);
            break;
            
        // --- BILL ENDPOINTS ---
        case 'bills.pay':
            $billId = $input['bill_id'] ?? 0;
            $method = $input['payment_method'] ?? 'Cash';
            $amount = $input['amount'] ?? 0;
            $res = SyncService::completePayment($billId, $method, $amount);
            echo json_encode(['success' => $res, 'message' => 'Payment completed successfully!']);
            break;

        case 'bills.refund':
            $billId = $input['bill_id'] ?? 0;
            $amount = $input['amount'] ?? 0;
            $reason = $input['reason'] ?? 'Customer Refund';
            $res = SyncService::refundBill($billId, $amount, $reason);
            echo json_encode(['success' => $res, 'message' => 'Bill refunded successfully!']);
            break;

        // --- TABLE ENDPOINTS ---
        case 'tables.walkin':
            $tableId = $input['table_id'] ?? 0;
            $name = trim($input['customer_name'] ?? '') ?: 'Walk-in Guest';
            $phone = $input['phone'] ?? 'N/A';
            $adults = max(0, intval($input['adults'] ?? 0));
            $children = max(0, intval($input['children'] ?? 0));
            $guests = intval($input['guests'] ?? 0);
            if ($adults + $children > 0) {
                $guests = $adults + $children;
            } elseif ($guests <= 0) {
                $guests = 2;
            }
            if (!$tableId) {
                echo json_encode(['success' => false, 'message' => 'Please select a table']);
                break;
            }
            $table = Table::getById($tableId);
            if (!$table || $table['status'] !== 'Available') {
                echo json_encode(['success' => false, 'message' => 'That table is no longer available']);
                break;
            }
            $res = SyncService::walkinCustomer($tableId, $name, $phone, $guests, $adults, $children);
            echo json_encode(['success' => $res, 'message' => 'Walk-in customer seated & bill created!']);
            break;

        case 'tables.clean':
            $tableId = $input['table_id'] ?? 0;
            $res = SyncService::cleanTable($tableId);
            echo json_encode(['success' => $res, 'message' => 'Table cleaned & available!']);
            break;

        case 'tables.clean_all':
            $count = SyncService::cleanAllTables();
            echo json_encode([
                'success' => true,
                'message' => $count > 0 ? "$count table(s) cleaned & available!" : 'No tables needed cleaning'
            ]);
            break;

        case 'tables.free':
            $tableId = $input['table_id'] ?? 0;
            $force = !empty($input['force']);
            $res = SyncService::releaseTable($tableId, $force);
            echo json_encode($res);
            break;

        case 'tables.block':
            $tableId = $input['table_id'] ?? 0;
            echo json_encode(SyncService::blockTable($tableId));
            break;

        case 'tables.unblock':
            $tableId = $input['table_id'] ?? 0;
            $res = SyncService::unblockTable($tableId);
            echo json_encode(['success' => $res, 'message' => 'Table unblocked']);
            break;

        case 'tables.merge':
            $primaryId = $input['primary_table_id'] ?? 0;
            $secondaryId = $input['secondary_table_id'] ?? 0;
            if (!$primaryId || !$secondaryId) {
                echo json_encode(['success' => false, 'message' => 'Please select both primary and secondary tables.']);
                break;
            }
            echo json_encode(SyncService::mergeTables($primaryId, $secondaryId));
            break;

        case 'tables.unmerge':
            $tableId = $input['table_id'] ?? 0;
            $res = SyncService::unmergeTable($tableId);
            echo json_encode(['success' => $res, 'message' => 'Table unmerged & available!']);
            break;

        case 'tables.edit':
            $tableId = $input['table_id'] ?? 0;
            $tableNumber = trim($input['table_number'] ?? '');
            $capacity = intval($input['capacity'] ?? 0);
            if (!$tableId || $capacity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid table parameters.']);
                break;
            }
            global $pdo;
            if (!empty($tableNumber)) {
                $dupe = $pdo->prepare("SELECT id FROM tables WHERE table_number = ? AND id != ?");
                $dupe->execute([$tableNumber, $tableId]);
                if ($dupe->fetch()) {
                    echo json_encode(['success' => false, 'message' => "Table number \"$tableNumber\" is already in use"]);
                    break;
                }
                $stmt = $pdo->prepare("UPDATE tables SET table_number = ?, capacity = ? WHERE id = ?");
                $res = $stmt->execute([$tableNumber, $capacity, $tableId]);
            } else {
                $stmt = $pdo->prepare("UPDATE tables SET capacity = ? WHERE id = ?");
                $res = $stmt->execute([$capacity, $tableId]);
            }
            AuditLog::log("Updated Table Details", "Table", $tableId, "Capacity set to $capacity" . ($tableNumber ? ", label $tableNumber" : ""));
            echo json_encode(['success' => $res, 'message' => 'Table details updated successfully']);
            break;

        case 'tables.delete':
            $tableId = $input['table_id'] ?? 0;
            echo json_encode(SyncService::deleteTable($tableId));
            break;

        case 'tables.reservation':
            // Who is currently sitting at / holding this table?
            $tableId = $input['table_id'] ?? ($_GET['table_id'] ?? 0);
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT r.*, b.grand_total, b.status AS bill_status
                FROM reservations r
                LEFT JOIN bills b ON b.id = r.bill_id
                WHERE r.table_id = ? AND r.status IN ('Upcoming', 'Confirmed', 'Arrived', 'Dining')
                ORDER BY r.reservation_date ASC, r.reservation_time ASC
                LIMIT 1
            ");
            $stmt->execute([$tableId]);
            $row = $stmt->fetch();
            echo json_encode(['success' => (bool)$row, 'data' => $row ?: null, 'message' => $row ? '' : 'No active reservation on this table']);
            break;

        // --- SEARCH ---
        case 'search.global':
            $q = $_GET['q'] ?? ($input['q'] ?? '');
            echo json_encode(['success' => true, 'data' => SyncService::globalSearch($q)]);
            break;

        case 'tables.list':
            $tables = Table::getAll();
            echo json_encode(['success' => true, 'data' => $tables]);
            break;
            
        case 'tables.available':
            $tables = Table::getAvailable();
            echo json_encode(['success' => true, 'data' => $tables]);
            break;

        case 'tables.available_for_time':
            $date = $_GET['date'] ?? ($input['date'] ?? date('Y-m-d'));
            $time = $_GET['time'] ?? ($input['time'] ?? date('H:i'));
            $excludeId = $_GET['exclude_id'] ?? ($input['exclude_id'] ?? null);
            $tables = Table::getAvailableForTime($date, $time, $excludeId);
            echo json_encode(['success' => true, 'data' => $tables]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
