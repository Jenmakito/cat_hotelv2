<?php
// กำหนดข้อมูลการเชื่อมต่อฐานข้อมูล
include '../db_connect.php';

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- ฟังก์ชันดึงข้อมูล ---
function getCustomers($conn) {
    $sql = "SELECT id, username, email, phone FROM users WHERE role='user'";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

function getCats($conn) {
    $sql = "SELECT c.id, c.name, c.color, c.age, c.gender, u.username as owner_name 
            FROM cats c JOIN users u ON c.user_id = u.id";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

function getRooms($conn) {
    $sql = "SELECT id, room_number, room_type, status FROM rooms ORDER BY room_number";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

/**
 * Gets payments with optional method filtering.
 *
 * @param mysqli $conn The database connection.
 * @param string|null $paymentMethod The payment method to filter by.
 * @return array The fetched payment data.
 */
function getPayments($conn, $paymentMethod = null) {
    $sql = "SELECT p.id, u.username as customer_name, r.id as reservation_id, p.amount, p.payment_method, p.payment_date, p.status
            FROM payments p 
            JOIN users u ON p.customer_id = u.id
            LEFT JOIN reservations r ON p.reservation_id = r.id";
    
    // Add WHERE clause if a payment method is specified
    if ($paymentMethod) {
        $sql .= " WHERE p.payment_method = ?";
    }

    $sql .= " ORDER BY p.payment_date DESC";

    if ($paymentMethod) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $paymentMethod);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    
    return $res->fetch_all(MYSQLI_ASSOC);
}

/**
 * Gets reservations with optional date filtering.
 *
 * @param mysqli $conn The database connection.
 * @param string|null $dateFrom The start date for filtering.
 * @param string|null $dateTo The end date for filtering.
 * @return array The fetched reservation data.
 */
function getReservations($conn, $dateFrom = null, $dateTo = null) {
    $sql = "SELECT 
                r.id, 
                u.username AS customer_name, 
                c.name AS cat_name, 
                ro.room_number,
                r.date_from, 
                r.date_to, 
                r.total_cost, 
                r.status,
                r.paid 
            FROM reservations AS r
            JOIN users AS u ON r.customer_id = u.id
            JOIN cats AS c ON r.cat_id = c.id
            JOIN rooms AS ro ON r.room_id = ro.id";
    
    // Add WHERE clause if dates are specified
    if ($dateFrom && $dateTo) {
        $sql .= " WHERE r.date_from >= ? AND r.date_to <= ?";
    }
    
    $sql .= " ORDER BY r.id DESC";

    if ($dateFrom && $dateTo) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $dateFrom, $dateTo);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    
    return $res->fetch_all(MYSQLI_ASSOC);
}

// Get filter values from URL
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterPaymentMethod = $_GET['payment_method'] ?? '';

// Get data for the current report
$currentReport = $_GET['report'] ?? 'reservations';

$reservations = getReservations($conn, $filterDateFrom, $filterDateTo);
$payments = getPayments($conn, $filterPaymentMethod);
$customers = getCustomers($conn);
$cats = getCats($conn);
$rooms = getRooms($conn);

// Get unique payment methods for the filter dropdown
$allPayments = getPayments($conn);
$paymentMethods = [];
if (!empty($allPayments)) {
    $paymentMethods = array_unique(array_column($allPayments, 'payment_method'));
}

$conn->close();

$reportData = [
    'reservations' => [
        'title' => 'รายงานการจองห้องพัก',
        'data' => $reservations,
        'tableId' => 'reservationTable',
        'headers' => ['ID', 'user', 'cat', 'room', 'Date of stay', 'Issue date', 'Total cost'],
        'fields' => ['id', 'customer_name', 'cat_name', 'room_number', 'date_from', 'date_to', 'total_cost'],
        'showDateFilter' => true,
        'showMethodFilter' => false
    ],
    'payments' => [
        'title' => 'รายงานการชำระเงิน',
        'data' => $payments,
        'tableId' => 'paymentTable',
        'headers' => ['ID pay', 'ID reser', 'user', 'Amount of money', 'How to pay', 'status'],
        'fields' => ['id', 'reservation_id', 'customer_name', 'amount', 'payment_method', 'status'],
        'showDateFilter' => false,
        'showMethodFilter' => true
    ],
    'customers' => [
        'title' => 'รายงานข้อมูลลูกค้า',
        'data' => $customers,
        'tableId' => 'customerTable',
        'headers' => ['Username', 'Email', 'เบอร์โทรศัพท์'],
        'fields' => ['username', 'email', 'phone'],
        'showDateFilter' => false,
        'showMethodFilter' => false
    ],
    'cats' => [
        'title' => 'รายงานข้อมูลแมว',
        'data' => $cats,
        'tableId' => 'catTable',
        'headers' => ['cat_name', 'user', 'color', 'gender'],
        'fields' => ['name', 'owner_name', 'color', 'gender'],
        'showDateFilter' => false,
        'showMethodFilter' => false
    ],
    'rooms' => [
        'title' => 'รายงานข้อมูลห้องพัก',
        'data' => $rooms,
        'tableId' => 'roomTable',
        'headers' => ['room', 'type', 'status'],
        'fields' => ['room_number', 'room_type', 'status'],
        'showDateFilter' => false,
        'showMethodFilter' => false
    ]
];

$currentReportInfo = $reportData[$currentReport];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หน้ารายงาน</title>
    <style>
        body {
            font-family: Arial, sans-serif; 
            margin: 0; 
            background-color: #1a1a2e; 
            color: #e0e0e0;
        }
        .header {
            background-color: #2a2a44;;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background-color: #2a2a44;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5);
            margin-top: 20px;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .controls label, .controls span {
            font-weight: bold;
        }
        .controls select, .controls input[type="text"], .controls input[type="date"] {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #4a4a6a;
            background-color: #3a3a5a;
            color: #e0e0e0;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filter-group button {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            background-color: #9c27b0;
            color: white;
            cursor: pointer;
        }
        .button-group {
            display: flex;
            gap: 10px;
        }
        .button-group button, .button-group a {
            padding: 10px 15px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .copy-btn { background-color: #007bff; }
        .csv-btn { background-color: #28a745; }
        .print-btn { background-color: #6c757d; }
        .back-link { background-color: #dc3545; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid #4a4a6a;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #4a148c;
            color: white;
        }
        td { 
            background-color: #3a3a5a; 
        }
        tr:nth-child(even) td { 
            background-color: #2a2a44; 
        }
        .status-badge {
            padding: 3px 6px;
            border-radius: 4px;
            color: white;
            font-size: 0.85em;
            font-weight: bold;
        }
        .pending { background-color:#fbc02d; }
        .pending_approval { background-color: #ff9800; }
        .confirmed { background-color:#4caf50; }
        .cancelled { background-color:#9e9e9e; }
        .approved { background-color:#4caf50; }
        .rejected { background-color:#f44336; }

        @media print {
            .controls, .header {
                display: none;
            }
            .container {
                box-shadow: none;
                margin-top: 0;
            }
        }
    </style>
</head>
<body>

<div class="header">
    รายงาน
</div>

<div class="container">
    <div class="controls">
        <div class="selection-group">
            <label for="reportSelect">เลือกหน้า:</label>
            <select id="reportSelect" onchange="location = this.value;">
                <option value="?report=reservations" <?php echo $currentReport == 'reservations' ? 'selected' : ''; ?>>รายงานการจองห้องพัก</option>
                <option value="?report=payments" <?php echo $currentReport == 'payments' ? 'selected' : ''; ?>>รายงานการชำระเงิน</option>
                <option value="?report=customers" <?php echo $currentReport == 'customers' ? 'selected' : ''; ?>>รายงานข้อมูลลูกค้า</option>
                <option value="?report=cats" <?php echo $currentReport == 'cats' ? 'selected' : ''; ?>>รายงานข้อมูลแมว</option>
                <option value="?report=rooms" <?php echo $currentReport == 'rooms' ? 'selected' : ''; ?>>รายงานข้อมูลห้องพัก</option>
            </select>
        </div>
        
        <div class="button-group">
            <button class="copy-btn" onclick="copyTable('<?php echo $currentReportInfo['tableId']; ?>')">Copy</button>
            <button class="csv-btn" onclick="exportTableToCSV('<?php echo $currentReportInfo['tableId']; ?>', '<?php echo $currentReport; ?>_report.csv')">CSV</button>
            <button class="print-btn" onclick="printTable('<?php echo $currentReportInfo['tableId']; ?>', '<?php echo $currentReportInfo['title']; ?>')">Print</button>
            <a href="../admin_dashboard.php" class="back-link">X</a>
        </div>
    </div>
    
    <h3><?php echo $currentReportInfo['title']; ?></h3>
    
    <?php if ($currentReport === 'reservations'): ?>
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="report" value="reservations">
            <span>จากวันที่:</span>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
            <span>ถึงวันที่:</span>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
            <button type="submit">ค้นหา</button>
        </form>
    <?php elseif ($currentReport === 'payments'): ?>
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="report" value="payments">
            <span>วิธีการชำระเงิน:</span>
            <select name="payment_method">
                <option value="">ทั้งหมด</option>
                <?php foreach($paymentMethods as $method): ?>
                    <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $filterPaymentMethod === $method ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($method); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">ค้นหา</button>
        </form>
    <?php endif; ?>

    <table id="<?php echo $currentReportInfo['tableId']; ?>">
        <thead>
            <tr>
                <?php foreach($currentReportInfo['headers'] as $header): ?>
                    <th><?php echo $header; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($currentReportInfo['data'])): ?>
                <?php foreach($currentReportInfo['data'] as $row): ?>
                    <tr>
                        <?php foreach($currentReportInfo['fields'] as $field): ?>
                            <td>
                                <?php 
                                    $value = $row[$field] ?? '-';
                                    if ($field === 'total_cost' || $field === 'amount') {
                                        echo number_format($value, 2);
                                    } elseif ($field === 'status') {
                                        echo "<span class='status-badge " . htmlspecialchars($value) . "'>" . htmlspecialchars($value) . "</span>";
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?php echo count($currentReportInfo['headers']); ?>">ไม่พบข้อมูล</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // JavaScript สำหรับปุ่ม Copy, CSV, Print
    function copyTable(tableId) {
        const table = document.getElementById(tableId);
        let text = '';
        for (let i = 0; i < table.rows.length; i++) {
            for (let j = 0; j < table.rows[i].cells.length; j++) {
                text += table.rows[i].cells[j].innerText + '\t';
            }
            text += '\n';
        }
        navigator.clipboard.writeText(text).then(() => {
            alert('คัดลอกข้อมูลเรียบร้อยแล้ว');
        }).catch(err => {
            console.error('ไม่สามารถคัดลอกได้:', err);
        });
    }

    function exportTableToCSV(tableId, filename) {
        let csv = [];
        const rows = document.querySelectorAll(`#${tableId} tr`);
        
        for (const row of rows) {
            const rowData = [];
            for (const cell of row.querySelectorAll('th, td')) {
                const innerText = cell.innerText.replace(/"/g, '""');
                rowData.push(`"${innerText}"`);
            }
            csv.push(rowData.join(','));
        }
        
        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const downloadLink = document.createElement('a');
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }

    // New and improved printTable function
    function printTable(tableId, title) {
        const printContents = document.getElementById(tableId).outerHTML;
        const originalContents = document.body.innerHTML;
        
        // Check if there is a payment method filter form and get the selected value
        const paymentMethodForm = document.querySelector('form.filter-group select[name="payment_method"]');
        let paymentMethodText = '';
        if (paymentMethodForm && paymentMethodForm.value !== '') {
            const selectedMethod = paymentMethodForm.options[paymentMethodForm.selectedIndex].text;
            paymentMethodText = `<p style="text-align: center; color: #555; margin-bottom: 10px;">
                                    **ประเภทการชำระเงิน:** ${selectedMethod}
                                 </p>`;
        }

        // Check if there is a date filter form and get the values
        const dateForm = document.querySelector('form.filter-group input[name="date_from"]');
        let dateRangeText = '';
        if (dateForm) {
            const dateFrom = document.querySelector('input[name="date_from"]').value;
            const dateTo = document.querySelector('input[name="date_to"]').value;
            
            if (dateFrom && dateTo) {
                const formattedDateFrom = new Date(dateFrom).toLocaleDateString('th-TH', { dateStyle: 'long' });
                const formattedDateTo = new Date(dateTo).toLocaleDateString('th-TH', { dateStyle: 'long' });
                dateRangeText = `<p style="text-align: center; color: #555; margin-bottom: 20px;">
                                    **จากวันที่:** ${formattedDateFrom} **ถึงวันที่:** ${formattedDateTo}
                                 </p>`;
            }
        }
        
        document.body.innerHTML = `
            <h2 style="text-align: center;">${title}</h2>
            ${dateRangeText}
            ${paymentMethodText}
            ${printContents}
        `;
        
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
</script>

</body>
</html>