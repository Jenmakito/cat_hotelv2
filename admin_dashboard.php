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

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- ฟังก์ชันดึงข้อมูล ---
function getCustomers($conn) {
    $sql = "SELECT * FROM users WHERE role='user'";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

function getCats($conn) {
    $sql = "SELECT c.id, c.name, c.color, c.age, c.gender, u.username as owner_name 
            FROM cats c JOIN users u ON c.user_id = u.id";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

function getReservations($conn) {
    $sql = "SELECT r.id, u.username as customer_name, c.name as cat_name, r.date_from, r.date_to, r.room_type, r.paid, r.total_cost, r.status
            FROM reservations r
            JOIN users u ON r.customer_id = u.id
            JOIN cats c ON r.cat_id = c.id
            ORDER BY r.date_from DESC";
    $res = $conn->query($sql);
    return $res->fetch_all(MYSQLI_ASSOC);
}

// --- จัดการ POST สำหรับอนุมัติ / ปฏิเสธ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reservation_id = intval($_POST['reservation_id'] ?? 0);

    if ($reservation_id > 0) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE reservations SET status='confirmed' WHERE id=?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE reservations SET status='cancelled' WHERE id=?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin_dashboard.php");
        exit();
    }
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
<title>Admin Dashboard</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #1a1a2e;
        color: #e0e0e0;
    }
    h2, h3 {
        color: #9c27b0;
    }
    a { color: #b39ddb; text-decoration:none; }
    a:hover { color: #ffffff; }
    hr { border:0; height:1px; background-color:#4a4a6a; margin:20px 0; }

    .dashboard-container { display:flex; flex-wrap:wrap; justify-content:space-around; }

    .section {
        background-color: #2a2a44;
        padding:20px;
        border-radius:12px;
        margin:10px;
        flex:1 1 30%;
        box-shadow: 0 4px 8px rgba(0,0,0,0.5);
    }

    table {
        width:100%;
        border-collapse:collapse;
        margin-top:10px;
        border-radius:8px;
        overflow:hidden;
    }
    th, td {
        border:1px solid #4a4a6a;
        padding:8px;
        text-align:left;
    }
    th { background-color:#4a148c; color:white; font-weight:bold; }
    td { background-color:#3a3a5a; }
    tr:nth-child(even) td { background-color:#2a2a44; }

    .actions a, .actions button {
        margin-right:5px;
        padding:4px 8px;
        border:none;
        border-radius:4px;
        font-size:0.85em;
        cursor:pointer;
        text-decoration:none;
    }
    .actions button { color:white; }
    .approve { background-color:#4caf50; }
    .approve:hover { background-color:#45a049; }
    .reject { background-color:#f44336; }
    .reject:hover { background-color:#da190b; }
    .delete { background-color:#607d8b; }
    .delete:hover { background-color:#455a64; }

    .status-badge {
        padding:3px 6px;
        border-radius:4px;
        color:white;
        font-size:0.85em;
        font-weight:bold;
    }
    .pending { background-color:#fbc02d; }
    .confirmed { background-color:#4caf50; }
    .cancelled { background-color:#9e9e9e; }
</style>
</head>
<body>
<h2>สวัสดี <?php echo $_SESSION['username']; ?> (Admin)</h2>
<p>Admin mode ของผู้ดูแลระบบ</p>
<a href="logout.php">ออกจากระบบ</a>
<hr>

<div class="dashboard-container">

    <!-- ข้อมูลลูกค้า -->
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
                <?php if(!empty($customers)): foreach($customers as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['username']); ?></td>
                        <td><?php echo htmlspecialchars($c['email']); ?></td>
                        <td><?php echo htmlspecialchars($c['phone']); ?></td>
                        <td class="actions">
                            <a href="admin_gm/edit_customer.php?id=<?php echo $c['id']; ?>" class="approve">แก้ไข</a>
                            <a href="admin_gm/delete_customer.php?id=<?php echo $c['id']; ?>" class="delete" onclick="return confirm('คุณต้องการลบข้อมูลลูกค้ารายนี้หรือไม่?');">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">ไม่มีข้อมูลลูกค้า</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ข้อมูลแมว -->
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
                <?php if(!empty($cats)): foreach($cats as $cat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td><?php echo htmlspecialchars($cat['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($cat['color']); ?></td>
                        <td><?php echo htmlspecialchars($cat['gender']); ?></td>
                        <td class="actions">
                            <a href="admin_gm/edit_cat.php?id=<?php echo $cat['id']; ?>" class="approve">แก้ไข</a>
                            <a href="admin_gm/delete_cat.php?id=<?php echo $cat['id']; ?>" class="delete" onclick="return confirm('คุณต้องการลบข้อมูลแมวตัวนี้หรือไม่?');">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5">ไม่มีข้อมูลแมว</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ข้อมูลการจอง -->
    <div class="section">
        <h3>ข้อมูลการจอง</h3>
        <table>
            <thead>
                <tr>
                    <th>ลูกค้า</th>
                    <th>ชื่อแมว</th>
                    <th>วันที่เข้าพัก</th>
                    <th>วันที่ออก</th>
                    <th>สถานะ</th>
                    <th>ราคา</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($reservations)): foreach($reservations as $res): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['cat_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['date_from']); ?></td>
                        <td><?php echo htmlspecialchars($res['date_to']); ?></td>
                        <td>
                            <?php 
                                $status = $res['status'] ?? 'pending';
                                echo "<span class='status-badge {$status}'>";
                                $labels = ['pending'=>'รอการยืนยัน','confirmed'=>'ยืนยันแล้ว','cancelled'=>'ถูกยกเลิก'];
                                echo $labels[$status] ?? 'ไม่ทราบ';
                                echo "</span>";
                            ?>
                        </td>
                        <td><?php echo number_format($res['total_cost'],2); ?> บาท</td>
                        <td class="actions">
                            <?php if($res['status']==='pending'): ?>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                    <button type="submit" class="approve">อนุมัติ</button>
                                </form>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                    <button type="submit" class="reject">ปฏิเสธ</button>
                                </form>
                            <?php endif; ?>
                            <a href="delete_reservation.php?id=<?php echo $res['id']; ?>" class="delete" onclick="return confirm('คุณต้องการลบข้อมูลการจองนี้หรือไม่?');">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7">ไม่มีข้อมูลการจอง</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
