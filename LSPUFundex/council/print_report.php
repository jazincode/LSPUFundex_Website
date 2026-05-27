<?php
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$sectionId = (int)($_SESSION['section_id'] ?? 0);
if ($sectionId <= 0) {
    redirect(BASE_URL . 'officer/dashboard.php');
}

$stmt = $conn->prepare(
    "SELECT s.*, d.name AS dept_name, d.code AS dept_code,
            yl.name AS year_level_name
     FROM sections s
     JOIN departments d  ON d.id  = s.department_id
     JOIN year_levels yl ON yl.id = s.year_level_id
     WHERE s.id = ?"
);
$stmt->bind_param("i", $sectionId);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT * FROM funds WHERE section_id = ? ORDER BY fund_date ASC"
);
$stmt->bind_param("i", $sectionId);
$stmt->execute();
$funds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare(
    "SELECT * FROM expenses WHERE section_id = ? ORDER BY expense_date ASC"
);
$stmt->bind_param("i", $sectionId);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalFunds    = getTotalFunds($conn, $sectionId);
$totalExpenses = getTotalExpenses($conn, $sectionId);
$balance       = $totalFunds - $totalExpenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report — <?php echo clean($section['name']); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; font-size:13px; color:#2c3e50; padding:30px; }
        .header { text-align:center; border-bottom:3px solid #1b4f72; padding-bottom:16px; margin-bottom:20px; }
        .header h1 { font-size:22px; color:#1b4f72; }
        .header h2 { font-size:15px; color:#555; font-weight:400; margin-top:4px; }
        .header p  { color:#888; font-size:12px; margin-top:6px; }
        .section-info { display:flex; gap:30px; background:#f4f6f9; border-radius:8px; padding:14px 18px; margin-bottom:20px; }
        .info-item label { display:block; font-size:10px; color:#888; text-transform:uppercase; letter-spacing:0.5px; }
        .info-item span  { font-weight:700; color:#1b4f72; font-size:13px; }
        h3 { font-size:14px; color:#1b4f72; margin:20px 0 8px; border-left:4px solid #1b4f72; padding-left:10px; }
        table { width:100%; border-collapse:collapse; margin-bottom:10px; }
        th { background:#1b4f72; color:white; padding:8px 10px; font-size:11px; text-align:left; }
        td { padding:7px 10px; border-bottom:1px solid #e0e6ed; font-size:12px; }
        tr:nth-child(even) td { background:#f8fbff; }
        .text-right { text-align:right; }
        .summary { margin-top:20px; border:2px solid #1b4f72; border-radius:8px; overflow:hidden; }
        .summary table { margin:0; }
        .summary th { background:#0d1b2a; }
        .summary .total-row td { font-weight:700; font-size:13px; }
        .balance-positive { color:#27ae60; }
        .balance-negative { color:#e74c3c; }
        .footer { margin-top:30px; border-top:1px solid #e0e6ed; padding-top:12px; text-align:center; font-size:11px; color:#aaa; }
        .print-btn { position:fixed; top:20px; right:20px; background:#1b4f72; color:white; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-size:13px; font-weight:600; }
        @media print {
            .print-btn { display:none; }
            body { padding:15px; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">
    🖨️ Print / Save PDF
</button>

<div class="header">
    <h1>LSPUFundex Financial Report</h1>
    <h2><?php echo clean(SCHOOL_NAME); ?></h2>
    <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
</div>

<div class="section-info">
    <div class="info-item">
        <label>Section</label>
        <span><?php echo clean($section['name']); ?></span>
    </div>
    <div class="info-item">
        <label>Department</label>
        <span><?php echo clean($section['dept_name']); ?> (<?php echo clean($section['dept_code']); ?>)</span>
    </div>
    <div class="info-item">
        <label>Year Level</label>
        <span><?php echo clean($section['year_level_name']); ?></span>
    </div>
    <div class="info-item">
        <label>School Year</label>
        <span><?php echo clean($section['school_year']); ?></span>
    </div>
</div>

<h3>Fund Records (<?php echo count($funds); ?> entries)</h3>
<?php if (empty($funds)): ?>
    <p style="color:#aaa; font-style:italic; padding:10px;">No funds recorded.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Source</th>
            <th>Date</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
    <?php $i=1; foreach ($funds as $f): ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo clean($f['title']); ?></td>
            <td><?php echo $f['source'] ? clean($f['source']) : '—'; ?></td>
            <td><?php echo formatDate($f['fund_date']); ?></td>
            <td class="text-right"><?php echo formatMoney($f['amount']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-right" style="font-weight:700;">TOTAL FUNDS:</td>
            <td class="text-right" style="font-weight:700;"><?php echo formatMoney($totalFunds); ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<h3>Expense Records (<?php echo count($expenses); ?> entries)</h3>
<?php if (empty($expenses)): ?>
    <p style="color:#aaa; font-style:italic; padding:10px;">No expenses recorded.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Category</th>
            <th>Date</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
    <?php $i=1; foreach ($expenses as $e): ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo clean($e['title']); ?></td>
            <td><?php echo clean($e['category']); ?></td>
            <td><?php echo formatDate($e['expense_date']); ?></td>
            <td class="text-right"><?php echo formatMoney($e['amount']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-right" style="font-weight:700;">TOTAL EXPENSES:</td>
            <td class="text-right" style="font-weight:700;"><?php echo formatMoney($totalExpenses); ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<div class="summary">
    <table>
        <thead>
            <tr><th colspan="2">Financial Summary</th></tr>
        </thead>
        <tbody>
            <tr class="total-row">
                <td>Total Funds Collected</td>
                <td class="text-right"><?php echo formatMoney($totalFunds); ?></td>
            </tr>
            <tr class="total-row">
                <td>Total Expenses</td>
                <td class="text-right"><?php echo formatMoney($totalExpenses); ?></td>
            </tr>
            <tr class="total-row" style="background:#f0fff4;">
                <td style="font-size:15px;">Remaining Balance</td>
                <td class="text-right <?php echo $balance >= 0
                    ? 'balance-positive':'balance-negative'; ?>" style="font-size:15px;">
                    <?php echo formatMoney($balance); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="footer">
    <p>LSPUFundex — School Financial Transparency System</p>
    <p><?php echo clean(SCHOOL_NAME); ?> &copy; <?php echo date('Y'); ?></p>
</div>

</body>
</html>