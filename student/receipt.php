<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_student();

$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("
    SELECT p.*, s.full_name, s.reg_no
    FROM payments p JOIN students s ON s.id = p.student_id
    WHERE p.id=? AND p.student_id=? AND p.status='paid'
");
$s->execute([$id, $_SESSION['student_id']]);
$p = $s->fetch();
if (!$p) { die('Receipt not found or not paid.'); }
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>Receipt #<?= $p['id'] ?></title>
<style>
  body { font-family: Arial, sans-serif; max-width: 520px; margin: 40px auto; padding: 20px; }
  .box { border: 2px solid #333; padding: 24px; border-radius: 8px; }
  h2 { margin-top: 0; text-align: center; }
  table { width: 100%; font-size: 14px; }
  td { padding: 6px 0; }
  .total { font-size: 18px; font-weight: bold; border-top: 1px dashed #999; }
  .stamp { text-align: center; color: #0a7; font-size: 22px; font-weight: bold; margin: 12px 0; }
  @media print { .no-print { display: none; } }
</style>
</head><body>
<div class="box">
  <h2>🏠 HOSTEL RECEIPT</h2>
  <div class="stamp">PAID</div>
  <table>
    <tr><td>Receipt No:</td><td><b>RCPT-<?= str_pad($p['id'],6,'0',STR_PAD_LEFT) ?></b></td></tr>
    <tr><td>Date:</td><td><?= htmlspecialchars($p['paid_at']) ?></td></tr>
    <tr><td>Student:</td><td><?= htmlspecialchars($p['full_name']) ?></td></tr>
    <tr><td>Reg No:</td><td><?= htmlspecialchars($p['reg_no']) ?></td></tr>
    <tr><td>Payment For:</td><td><?= $p['type']==='registration' ? 'Registration Fee' : 'Rent — '.$p['month'] ?></td></tr>
    <tr><td>M-Pesa Ref:</td><td><?= htmlspecialchars($p['mpesa_ref'] ?? '—') ?></td></tr>
    <tr class="total"><td>Amount Paid:</td><td>KSh <?= number_format($p['amount']) ?></td></tr>
  </table>
  <p style="text-align:center;font-size:12px;color:#666;margin-top:24px">
    Thank you. Keep this receipt for your records.
  </p>
</div>
<button class="no-print" style="margin-top:16px" onclick="window.print()">🖨 Print</button>
</body></html>