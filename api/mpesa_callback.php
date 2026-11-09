<?php
require_once __DIR__ . '/../config/db.php';

// Log raw input for debugging
file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . ' ' . file_get_contents('php://input') . "\n", FILE_APPEND);

$data = json_decode(file_get_contents('php://input'), true);
$stk = $data['Body']['stkCallback'] ?? null;

if (!$stk) { http_response_code(200); echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'OK']); exit; }

$checkoutId = $stk['CheckoutRequestID'] ?? '';
$resultCode = $stk['ResultCode'] ?? '';
$resultDesc = $stk['ResultDesc'] ?? '';

$pdo->beginTransaction();
try {
    if ($resultCode == 0) {
        // SUCCESS: Extract M-Pesa receipt number
        $receipt = null;
        foreach (($stk['CallbackMetadata']['Item'] ?? []) as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $receipt = $item['Value']; break;
            }
        }
        $stmt = $pdo->prepare("UPDATE payments SET status='paid', result_code=?, result_desc=?, mpesa_ref=?, paid_at=NOW() WHERE checkout_request_id=?");
        $stmt->execute([$resultCode, $resultDesc, $receipt, $checkoutId]);

        // Special logic: mark registration fee paid
        $p = $pdo->prepare("SELECT * FROM payments WHERE checkout_request_id=?");
        $p->execute([$checkoutId]);
        $pay = $p->fetch();
        if ($pay && $pay['type'] === 'registration') {
            $pdo->prepare("UPDATE students SET registration_paid=1 WHERE id=?")->execute([$pay['student_id']]);
        }
    } else {
        // FAILURE: User cancelled, wrong PIN, timeout, etc.
        $pdo->prepare("UPDATE payments SET status='failed', result_code=?, result_desc=? WHERE checkout_request_id=?")
            ->execute([$resultCode, $resultDesc, $checkoutId]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Callback DB Error: ' . $e->getMessage());
}

// MUST return this to Safaricom
http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);