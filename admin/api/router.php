<?php
// admin/api/router.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/SyncService.php';

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
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
            $res = Lead::updateStatus($leadId, 'Contacted');
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
            $res = SyncService::cancelReservation($resId);
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
            $name = $input['customer_name'] ?? 'Walk-in Guest';
            $phone = $input['phone'] ?? 'N/A';
            $guests = $input['guests'] ?? 2;
            $res = SyncService::walkinCustomer($tableId, $name, $phone, $guests);
            echo json_encode(['success' => $res, 'message' => 'Walk-in customer seated & bill created!']);
            break;

        case 'tables.clean':
            $tableId = $input['table_id'] ?? 0;
            $res = SyncService::cleanTable($tableId);
            echo json_encode(['success' => $res, 'message' => 'Table cleaned & available!']);
            break;

        case 'tables.free':
            $tableId = $input['table_id'] ?? 0;
            $res = Table::updateStatus($tableId, 'Available', null);
            echo json_encode(['success' => $res, 'message' => 'Table freed & available!']);
            break;

        case 'tables.block':
            $tableId = $input['table_id'] ?? 0;
            $res = SyncService::blockTable($tableId);
            echo json_encode(['success' => $res, 'message' => 'Table blocked']);
            break;

        case 'tables.unblock':
            $tableId = $input['table_id'] ?? 0;
            $res = SyncService::unblockTable($tableId);
            echo json_encode(['success' => $res, 'message' => 'Table unblocked']);
            break;

        case 'tables.edit':
            $tableId = $input['table_id'] ?? 0;
            $capacity = $input['capacity'] ?? 0;
            global $pdo;
            $stmt = $pdo->prepare("UPDATE tables SET capacity = ? WHERE id = ?");
            $res = $stmt->execute([$capacity, $tableId]);
            echo json_encode(['success' => $res, 'message' => 'Table capacity updated']);
            break;
            
        case 'tables.delete':
            $tableId = $input['table_id'] ?? 0;
            global $pdo;
            $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
            $res = $stmt->execute([$tableId]);
            echo json_encode(['success' => $res, 'message' => 'Table deleted']);
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
