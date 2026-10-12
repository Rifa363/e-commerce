<?php
header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/database.php';
require_once '../../config/session.php';
requireAdmin();

function reportXml($value) {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function reportHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function reportDate($date) {
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

try {
    $format = $_GET['format'] ?? '';
    if (!in_array($format, ['excel', 'pdf'], true)) {
        throw new InvalidArgumentException('Format laporan tidak valid.');
    }

    $status = $_GET['status'] ?? 'all';
    $statuses = ['all', 'pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
    if (!in_array($status, $statuses, true)) {
        throw new InvalidArgumentException('Status pesanan tidak valid.');
    }

    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    if (($from !== '' && !reportDate($from)) || ($to !== '' && !reportDate($to))) {
        throw new InvalidArgumentException('Format tanggal laporan tidak valid.');
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        throw new InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
    }

    $conditions = [];
    $params = [];
    if ($status !== 'all') {
        $conditions[] = 'o.status = :status';
        $params[':status'] = $status;
    }
    if ($from !== '') {
        $conditions[] = 'o.created_at >= :date_from';
        $params[':date_from'] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $conditions[] = 'o.created_at < :date_to';
        $params[':date_to'] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00');
    }

    $sql = "SELECT o.id, o.order_code, o.recipient_name, o.recipient_phone, o.address,
                   o.shipping_method, o.shipping_cost, o.subtotal, o.discount, o.voucher_code, o.total_amount,
                   o.payment_method, o.status, o.created_at
            FROM orders o";
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY o.created_at DESC';

    $db = (new Database())->getConnection();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    if ($orders) {
        $placeholders = implode(',', array_fill(0, count($orders), '?'));
        $itemsStmt = $db->prepare(
            "SELECT oi.order_id, p.name AS product_name, oi.quantity, oi.price
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN ($placeholders)
             ORDER BY oi.order_id, oi.id"
        );
        $itemsStmt->execute(array_column($orders, 'id'));
        $itemsByOrder = [];
        foreach ($itemsStmt->fetchAll() as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);
    }

    $totalAmount = array_sum(array_map(static function ($order) {
        return (float)$order['total_amount'];
    }, $orders));
    $period = ($from ?: 'Semua') . ' s.d. ' . ($to ?: 'Semua');
    $statusLabel = $status === 'all' ? 'Semua status' : ucfirst($status);
    $filenameDate = date('Ymd_His');

    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="laporan_pesanan_' . $filenameDate . '.xls"');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
            <Worksheet ss:Name="Laporan Pesanan">
                <Table>
                    <Row>
                        <Cell><Data ss:Type="String">Laporan Pesanan RiRa Cakes</Data></Cell>
                    </Row>
                    <Row>
                        <Cell><Data ss:Type="String">Periode</Data></Cell>
                        <Cell><Data ss:Type="String"><?= reportXml($period) ?></Data></Cell>
                    </Row>
                    <Row>
                        <Cell><Data ss:Type="String">Status</Data></Cell>
                        <Cell><Data ss:Type="String"><?= reportXml($statusLabel) ?></Data></Cell>
                    </Row>
                    <Row>
                        <Cell><Data ss:Type="String">Jumlah Pesanan</Data></Cell>
                        <Cell><Data ss:Type="Number"><?= count($orders) ?></Data></Cell>
                    </Row>
                    <Row>
                        <Cell><Data ss:Type="String">Total Nilai Pesanan (Rp)</Data></Cell>
                        <Cell><Data ss:Type="Number"><?= reportXml($totalAmount) ?></Data></Cell>
                    </Row>
                    <Row>
                        <?php foreach (['Kode Pesanan', 'Nama Customer', 'Telepon', 'Alamat', 'Produk', 'Jumlah Item', 'Subtotal (Rp)', 'Kode Voucher', 'Diskon (Rp)', 'Ongkir (Rp)', 'Total (Rp)', 'Pembayaran', 'Status', 'Tanggal'] as $heading): ?>
                            <Cell><Data ss:Type="String"><?= reportXml($heading) ?></Data></Cell>
                        <?php endforeach; ?>
                    </Row>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $itemNames = array_map(static function ($item) {
                            return ($item['product_name'] ?: 'Produk dihapus') . ' x' . $item['quantity'];
                        }, $order['items']);
                        $itemCount = array_sum(array_column($order['items'], 'quantity'));
                        ?>
                        <Row>
                            <?php foreach ([
                                ['String', $order['order_code']],
                                ['String', $order['recipient_name']],
                                ['String', $order['recipient_phone']],
                                ['String', $order['address']],
                                ['String', implode(', ', $itemNames)],
                                ['Number', $itemCount],
                                ['Number', $order['subtotal']],
                                ['String', $order['voucher_code'] ?: '-'],
                                ['Number', $order['discount']],
                                ['Number', $order['shipping_cost']],
                                ['Number', $order['total_amount']],
                                ['String', strtoupper($order['payment_method'])],
                                ['String', ucfirst($order['status'])],
                                ['String', $order['created_at']]
                            ] as [$type, $value]): ?>
                                <Cell><Data ss:Type="<?= $type ?>"><?= reportXml($value) ?></Data></Cell>
                            <?php endforeach; ?>
                        </Row>
                    <?php endforeach; ?>
                </Table>
            </Worksheet>
        </Workbook>
        <?php
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Pesanan RiRa Cakes</title>
        <style>
            body { color: #2d2424; font: 14px Arial, sans-serif; margin: 30px; }
            h1 { margin-bottom: 8px; }
            .meta { margin-bottom: 20px; color: #555; }
            .summary { display: flex; gap: 30px; margin-bottom: 20px; font-weight: bold; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #bbb; padding: 8px; text-align: left; vertical-align: top; }
            th { background: #f2e7e2; }
            .number { text-align: right; white-space: nowrap; }
            .toolbar { margin-bottom: 20px; }
            button { background: #a85c49; border: 0; border-radius: 5px; color: white; cursor: pointer; padding: 10px 16px; }
            @media print {
                body { margin: 10mm; }
                .toolbar { display: none; }
                tr { page-break-inside: avoid; }
                thead { display: table-header-group; }
            }
            @page { size: landscape; margin: 12mm; }
        </style>
    </head>
    <body>
        <div class="toolbar"><button onclick="window.print()">Cetak / Simpan sebagai PDF</button></div>
        <h1>Laporan Pesanan RiRa Cakes</h1>
        <div class="meta">Periode: <?= reportHtml($period) ?> &nbsp;|&nbsp; Status: <?= reportHtml($statusLabel) ?></div>
        <div class="summary">
            <div>Jumlah pesanan: <?= count($orders) ?></div>
            <div>Total nilai pesanan: Rp <?= number_format($totalAmount, 0, ',', '.') ?></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Kode</th><th>Customer</th><th>Telepon</th><th>Alamat</th><th>Produk</th>
                    <th>Item</th><th>Subtotal</th><th>Voucher</th><th>Diskon</th><th>Ongkir</th><th>Total</th>
                    <th>Pembayaran</th><th>Status</th><th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $itemNames = array_map(static function ($item) {
                        return ($item['product_name'] ?: 'Produk dihapus') . ' x' . $item['quantity'];
                    }, $order['items']);
                    $itemCount = array_sum(array_column($order['items'], 'quantity'));
                    ?>
                    <tr>
                        <td><?= reportHtml($order['order_code']) ?></td>
                        <td><?= reportHtml($order['recipient_name']) ?></td>
                        <td><?= reportHtml($order['recipient_phone']) ?></td>
                        <td><?= reportHtml($order['address']) ?></td>
                        <td><?= reportHtml(implode(', ', $itemNames)) ?></td>
                        <td class="number"><?= (int)$itemCount ?></td>
                        <td class="number">Rp <?= number_format((float)$order['subtotal'], 0, ',', '.') ?></td>
                        <td><?= reportHtml($order['voucher_code'] ?: '-') ?></td>
                        <td class="number">Rp <?= number_format((float)$order['discount'], 0, ',', '.') ?></td>
                        <td class="number">Rp <?= number_format((float)$order['shipping_cost'], 0, ',', '.') ?></td>
                        <td class="number">Rp <?= number_format((float)$order['total_amount'], 0, ',', '.') ?></td>
                        <td><?= reportHtml(strtoupper($order['payment_method'])) ?></td>
                        <td><?= reportHtml(ucfirst($order['status'])) ?></td>
                        <td><?= reportHtml($order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="14">Tidak ada pesanan pada filter ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Order report export failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Laporan tidak dapat dibuat. Silakan coba lagi.']);
}
