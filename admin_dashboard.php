<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit();
}

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

// ฟังก์ชันสำหรับดึงข้อมูลลูกค้า
function getCustomers($conn) {
    $sql = "SELECT * FROM users WHERE role = 'user'";
    $result = $conn->query($sql);
    $customers = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }
    return $customers;
}

// ฟังก์ชันสำหรับดึงข้อมูลแมว
function getCats($conn) {
    $sql = "SELECT c.id, c.name, c.color, c.age, c.gender, u.username as owner_name FROM cats c JOIN users u ON c.user_id = u.id";
    $result = $conn->query($sql);
    $cats = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $cats[] = $row;
        }
    }
    return $cats;
}

// ฟังก์ชันสำหรับดึงข้อมูลการจอง
function getReservations($conn) {
    $sql = "SELECT r.id, u.username as customer_name, c.name as cat_name, r.date_from, r.date_to, r.room_type, r.paid, r.total_cost FROM reservations r JOIN users u ON r.customer_id = u.id JOIN cats c ON r.cat_id = c.id ORDER BY r.date_from DESC";
    $result = $conn->query($sql);
    $reservations = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
    }
    return $reservations;
}

$customers = getCustomers($conn);
$cats = getCats($conn);
$reservations = getReservations($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Admin mode</title>
<style>
    /* สไตล์สำหรับธีมมืดและสีม่วง */
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #1a1a2e; /* สีพื้นหลังเข้ม */
        color: #e0e0e0; /* สีตัวอักษรหลัก */
    }
    h2, h3 {
        color: #9c27b0; /* สีม่วงเข้มสำหรับหัวข้อ */
    }
    .dashboard-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
    }
    .section {
        background-color: #2a2a44; /* สีพื้นหลังของแต่ละส่วน */
        padding: 20px;
        border-radius: 8px;
        margin: 10px;
        flex: 1 1 30%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4); /* เงาเข้มขึ้น */
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        border-radius: 8px;
        overflow: hidden; /* ทำให้มุมของตารางโค้งมน */
    }
    th, td {
        border: 1px solid #4a4a6a; /* เส้นขอบตารางสีเข้ม */
        padding: 12px;
        text-align: left;
    }
    th {
        background-color: #4a148c; /* สีม่วงเข้มสำหรับส่วนหัวตาราง */
        color: #ffffff;
        font-weight: bold;
    }
    td {
        background-color: #3a3a5a; /* สีพื้นหลังแถวข้อมูล */
    }
    tr:nth-child(even) td {
        background-color: #2a2a44; /* สีพื้นหลังแถวคู่ที่ต่างออกไป */
    }
    .actions a {
        margin-right: 10px;
        text-decoration: none;
        color: #b39ddb; /* สีม่วงอ่อนสำหรับลิงก์ */
        transition: color 0.3s ease;
    }
    .actions a:hover {
        color: #ffffff; /* เปลี่ยนสีเมื่อเมาส์ชี้ */
    }
    hr {
        border: 0;
        height: 1px;
        background-color: #4a4a6a;
        margin: 20px 0;
    }
    a {
        color: #b39ddb;
        text-decoration: none;
    }
    a:hover {
        color: #ffffff;
    }
</style>
</head>
<body>
<h2>สวัสดี <?php echo $_SESSION['username']; ?> (Admin)</h2>
<p>admin mode ของผู้ดูแลระบบ</p>
<a href="logout.php">ออกจากระบบ</a>
<hr>

<div class="dashboard-container">

    <div class="section">
        <h3>ข้อมูลลูกค้า</h3>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['username']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td class="actions">
                                <a href="admin_gm/edit_customer.php?id=<?php echo $customer['id']; ?>">แก้ไข</a>
                                <a href="admin_gm/delete_customer.php?id=<?php echo $customer['id']; ?>" onclick="return confirm('คุณต้องการลบข้อมูลลูกค้ารายนี้หรือไม่?');">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">ไม่มีข้อมูลลูกค้า</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>ข้อมูลแมว</h3>
        <table>
            <thead>
                <tr>
                    <th>ชื่อแมว</th>
                    <th>เจ้าของ</th>
                    <th>สี</th>
                    <th>เพศ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($cats)): ?>
                    <?php foreach ($cats as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td><?php echo htmlspecialchars($cat['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars($cat['color']); ?></td>
                            <td><?php echo htmlspecialchars($cat['gender']); ?></td>
                            <td class="actions">
                                <a href="admin_gm/edit_cat.php?id=<?php echo $cat['id']; ?>">แก้ไข</a>
                                <a href="admin_gm/delete_cat.php?id=<?php echo $cat['id']; ?>" onclick="return confirm('คุณต้องการลบข้อมูลแมวตัวนี้หรือไม่?');">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">ไม่มีข้อมูลแมว</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>ข้อมูลการจอง</h3>
        <table>
            <thead>
                <tr>
                    <th>ลูกค้า</th>
                    <th>ชื่อแมว</th>
                    <th>วันที่เข้าพัก</th>
                    <th>วันที่ออก</th>
                    <th>ชำระเงินแล้ว</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reservations)): ?>
                    <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($res['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($res['cat_name']); ?></td>
                            <td><?php echo htmlspecialchars($res['date_from']); ?></td>
                            <td><?php echo htmlspecialchars($res['date_to']); ?></td>
                            <td><?php echo $res['paid'] ? '✅' : '❌'; ?></td>
                            <td class="actions">
                                <a href="admin_gm/delete_reservation.php?id=<?php echo $res['id']; ?>" onclick="return confirm('คุณต้องการลบข้อมูลการจองนี้หรือไม่?');">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">ไม่มีข้อมูลการจอง</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>