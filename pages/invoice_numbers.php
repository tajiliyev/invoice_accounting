<?php
// pages/invoice_numbers.php
$sql = "SELECT invoice_number, client_name, issue_date, amount, status 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        ORDER BY 
            CASE 
                WHEN invoice_number REGEXP '^[0-9]+$' THEN CAST(invoice_number AS UNSIGNED)
                ELSE 999999
            END";
$invoices = $pdo->query($sql)->fetchAll();
?>

<div class="header">
    <h1><i class="fas fa-list-ol"></i> Нумерация счетов</h1>
    <p>История и порядок номеров счетов</p>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>Номер счета</th>
                <th>Клиент</th>
                <th>Дата выписки</th>
                <th>Сумма</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            <?php $counter = 1; ?>
            <?php foreach ($invoices as $invoice): ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                <td><?php echo escape($invoice['client_name']); ?></td>
                <td><?php echo date('d.m.Y', strtotime($invoice['issue_date'])); ?></td>
                <td><?php echo number_format($invoice['amount'], 2); ?> TMT</td>
                <td>
                    <span class="status status-<?php echo $invoice['status']; ?>">
                        <?php echo $invoice['status'] == 'pending' ? 'Ожидание' : 
                               ($invoice['status'] == 'partial' ? 'Частично' : 'Оплачено'); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>