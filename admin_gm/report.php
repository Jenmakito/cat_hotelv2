<?php
// กำหนดข้อมูลการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cathotel_db";

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

function getPayments($conn) {
    $sql = "SELECT p.id, u.username as customer_name, r.id as reservation_id, p.amount, p.payment_method, p.payment_date, p.status
            FROM payments p 
            JOIN users u ON p.customer_id = u.id
            LEFT JOIN reservations r ON p.reservation_id = r.id
            ORDER BY p.payment_date DESC";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

function getReservations($conn) {
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
            JOIN rooms AS ro ON r.room_id = ro.id
            ORDER BY r.id DESC";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

// ดึงข้อมูลทั้งหมด
$customers = getCustomers($conn);
$cats = getCats($conn);
$rooms = getRooms($conn);
$reservations = getReservations($conn);
$payments = getPayments($conn);

$conn->close();

$currentReport = $_GET['report'] ?? 'reservations';

$reportData = [
    'reservations' => [
        'title' => 'รายงานการจองห้องพัก',
        'data' => $reservations,
        'tableId' => 'reservationTable',
        'headers' => ['ID', 'user', 'cat', 'room', 'Date of stay', 'Issue date', 'Total cost'],
        'fields' => ['id', 'customer_name', 'cat_name', 'room_number', 'date_from', 'date_to', 'total_cost'],
        'showSearch' => true
    ],
    'payments' => [
        'title' => 'รายงานการชำระเงิน',
        'data' => $payments,
        'tableId' => 'paymentTable',
        'headers' => ['ID pay', 'ID reser', 'user', 'Amount of money', 'How to pay', 'status'],
        'fields' => ['id', 'reservation_id', 'customer_name', 'amount', 'payment_method', 'status'],
        'showSearch' => false
    ],
    'customers' => [
        'title' => 'รายงานข้อมูลลูกค้า',
        'data' => $customers,
        'tableId' => 'customerTable',
        'headers' => ['Username', 'Email', 'เบอร์โทรศัพท์'],
        'fields' => ['username', 'email', 'phone'],
        'showSearch' => false
    ],
    'cats' => [
        'title' => 'รายงานข้อมูลแมว',
        'data' => $cats,
        'tableId' => 'catTable',
        'headers' => ['cat_name', 'user', 'color', 'gender'],
        'fields' => ['name', 'owner_name', 'color', 'gender'],
        'showSearch' => false
    ],
    'rooms' => [
        'title' => 'รายงานข้อมูลห้องพัก',
        'data' => $rooms,
        'tableId' => 'roomTable',
        'headers' => ['room', 'type', 'status'],
        'fields' => ['room_number', 'room_type', 'status'],
        'showSearch' => false
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
        .controls select, .controls input[type="text"] {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #4a4a6a;
            background-color: #3a3a5a;
            color: #e0e0e0;
        }
        .date-range {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .date-range button {
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
        .button-group button {
            padding: 10px 15px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }
        .copy-btn { background-color: #007bff; }
        .csv-btn { background-color: #28a745; }
        .print-btn { background-color: #6c757d; }
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
        .pending_approval { background-color:#ff9800; }

        .search-box {
            display: flex;
            align-items: center;
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
            </select>
        </div>
        
        <div class="date-range">
            <span>วันที่เริ่ม:</span>
            <input type="date" id="dateFrom">
            <span>ถึง:</span>
            <input type="date" id="dateTo">
            <button onclick="filterByDate()">ค้นหา</button>
        </div>
        
        <div class="button-group">
            <button class="copy-btn" onclick="copyTable('<?php echo $currentReportInfo['tableId']; ?>')">Copy</button>
            <button class="csv-btn" onclick="exportTableToCSV('<?php echo $currentReportInfo['tableId']; ?>', '<?php echo $currentReport; ?>_report.csv')">CSV</button>
            <button class="print-btn" onclick="printTable('<?php echo $currentReportInfo['tableId']; ?>', '<?php echo $currentReportInfo['title']; ?>')">Print</button>
        </div>
        <?php if ($currentReportInfo['showSearch']): ?>
        <?php endif; ?>
    </div>
    
    <h3><?php echo $currentReportInfo['title']; ?></h3>
    
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
    // JavaScript สำหรับฟังก์ชันค้นหา
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            const tableId = '<?php echo $currentReportInfo['tableId']; ?>';
            const rows = document.querySelectorAll(`#${tableId} tbody tr`);
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(value)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

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

    function printTable(tableId, title) {
        const printContents = document.getElementById(tableId).outerHTML;
        const originalContents = document.body.innerHTML;
        document.body.innerHTML = `<h2>${title}</h2>` + printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
    
    function filterByDate() {
        // ฟังก์ชันนี้ยังไม่ได้ถูกเขียนโค้ดสำหรับกรองข้อมูลจริง
        // จะต้องส่งวันที่ไปประมวลผลที่เซิร์ฟเวอร์ด้วย AJAX หรือฟอร์ม
        alert('ฟังก์ชันกรองตามวันที่ยังไม่ถูกพัฒนา');
    }

</script>

</body>
</html>