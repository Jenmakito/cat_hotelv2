<?php
session_start();
// ตรวจสอบว่าผู้ใช้ล็อกอินและมีสิทธิ์เป็น admin หรือไม่
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include '../db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// รับชื่อลูกค้าจาก URL
$customer_name = isset($_GET['customer_name']) ? urldecode($_GET['customer_name']) : '';

$reservations = [];
if (!empty($customer_name)) {
    // ดึงข้อมูลการจองทั้งหมดของลูกค้าคนนี้
    $stmt = $conn->prepare("SELECT 
                                r.id, 
                                c.name AS cat_name,  /* ดึงชื่อจากตาราง cats */
                                r.date_from, 
                                r.date_to, 
                                r.total_cost, 
                                r.status, 
                                r.paid,
                                ro.room_number,
                                ro.room_type
                            FROM reservations r
                            JOIN users u ON r.customer_id = u.id
                            JOIN cats c ON r.cat_id = c.id  /* เพิ่ม JOIN นี้ */
                            LEFT JOIN rooms ro ON r.room_id = ro.id
                            WHERE u.username = ?
                            ORDER BY r.date_from DESC");
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติลูกค้า: <?php echo htmlspecialchars($customer_name); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: #e0e0e0; padding: 20px; }
        .container { background-color: #2a2a44; padding: 20px; border-radius: 12px; max-width: 1000px; margin: 20px auto; box-shadow: 0 4px 8px rgba(0,0,0,0.5); }
        h2 { color: #9c27b0; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #4a4a6a; text-align: left; }
        th { background-color: #4a148c; color: white; font-weight: bold; }
        td { background-color: #3a3a5a; }
        tr:nth-child(even) td { background-color: #2a2a44; }
        .no-data { text-align: center; font-style: italic; color: #888; }
        .back-btn { display: inline-block; margin-top: 20px; padding: 10px 20px; border: none; border-radius: 8px; color: white; cursor: pointer; font-size: 1em; text-decoration: none; background-color: #607d8b; }
        .back-btn:hover { background-color: #546e7a; }
        .status-badge { padding: 3px 6px; border-radius: 4px; color: white; font-size: 0.85em; font-weight: bold; }
        .pending_approval { background-color: #ff9800; }
        .confirmed { background-color: #4caf50; }
        .completed { background-color: #2196f3; }
        .cancelled { background-color: #9e9e9e; }
        .paid { color: #4caf50; font-weight: bold; }
        .not-paid { color: #f44336; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>ประวัติการจองของคุณ<?php echo htmlspecialchars($customer_name); ?></h2>
    
    <?php if (empty($customer_name) || empty($reservations)) { ?>
        <p class="no-data">ไม่พบข้อมูลการจองสำหรับลูกค้าท่านนี้</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>Reservation ID</th>
                    <th>น้องแมว</th>
                    <th>ห้อง</th>
                    <th>ประเภทห้อง</th>
                    <th>วันที่เข้าพัก</th>
                    <th>วันที่ออก</th>
                    <th>สถานะการจอง</th>
                    <th>การชำระเงิน</th>
                    <th>ค่าใช้จ่าย</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $res) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['id']); ?></td>
                        <td><?php echo htmlspecialchars($res['cat_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['room_number'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($res['room_type'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($res['date_from']); ?></td>
                        <td><?php echo htmlspecialchars($res['date_to']); ?></td>
                        <td>
                            <?php 
                                $status = $res['status'];
                                $labels = [
                                    'pending_approval' => 'รออนุมัติ',
                                    'confirmed' => 'ยืนยันแล้ว',
                                    'completed' => 'สำเร็จแล้ว',
                                    'cancelled' => 'ยกเลิก'
                                ];
                                $class = str_replace('_', '-', $status);
                                echo "<span class='status-badge {$class}'>".($labels[$status] ?? 'ไม่ทราบ')."</span>";
                            ?>
                        </td>
                        <td>
                            <span class="<?php echo (int)$res['paid'] === 1 ? 'paid' : 'not-paid'; ?>">
                                <?php echo (int)$res['paid'] === 1 ? 'ชำระแล้ว' : 'ยังไม่ชำระ'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars(number_format($res['total_cost'], 2)); ?> บาท</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
    
    <a href="../admin_dashboard.php" class="back-btn">กลับหน้าหลัก</a>
</div>
</body>
</html>