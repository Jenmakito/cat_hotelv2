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

function getReservations($conn) {
    $sql = "SELECT 
                r.id, 
                u.username as customer_name, 
                c.name as cat_name, 
                r.date_from, 
                r.date_to, 
                ro.room_number, 
                r.paid, 
                r.total_cost, 
                r.status,
                r.check_in,
                r.check_out,
                ro.id as room_id
            FROM reservations r
            JOIN users u ON r.customer_id = u.id
            JOIN cats c ON r.cat_id = c.id
            LEFT JOIN rooms ro ON r.room_id = ro.id
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
            // ดึง room_id จากการจองเพื่ออัปเดตสถานะห้อง
            $stmt_get_room = $conn->prepare("SELECT room_id FROM reservations WHERE id = ?");
            $stmt_get_room->bind_param("i", $reservation_id);
            $stmt_get_room->execute();
            $result = $stmt_get_room->get_result();
            $reservation_data = $result->fetch_assoc();
            $stmt_get_room->close();

            if ($reservation_data && !empty($reservation_data['room_id'])) {
                $room_id_to_update = $reservation_data['room_id'];
                
                // ใช้ Transaction เพื่อให้แน่ใจว่าการอัปเดตทั้งหมดสำเร็จ
                $conn->begin_transaction();
                try {
                    // อัปเดตสถานะการจองเป็น 'confirmed'
                    $stmt_res = $conn->prepare("UPDATE reservations SET status='confirmed' WHERE id=?");
                    $stmt_res->bind_param("i", $reservation_id);
                    $stmt_res->execute();
                    $stmt_res->close();

                    // อัปเดตสถานะห้องเป็น 'occupied'
                    $stmt_room = $conn->prepare("UPDATE rooms SET status='occupied' WHERE id=?");
                    $stmt_room->bind_param("i", $room_id_to_update);
                    $stmt_room->execute();
                    $stmt_room->close();

                    $conn->commit();
                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    // สามารถเพิ่มการจัดการข้อผิดพลาดที่นี่ได้
                }
            }
        } elseif ($action === 'reject') {
            // หากปฏิเสธ ให้คืนสถานะห้องเป็น 'available' (ถ้าจำเป็น) และยกเลิกการจอง
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
$rooms = $conn->query("SELECT * FROM rooms ORDER BY room_number")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<style>
    body {
        font-family: Arial, sans-serif; margin: 0; padding: 20px;
        background-color: #1a1a2e; color: #e0e0e0;
    }
    h2, h3 { color: #9c27b0; }
    a { color: #b39ddb; text-decoration:none; }
    a:hover { color: #ffffff; }
    hr { border:0; height:1px; background-color:#4a4a6a; margin:20px 0; }
    .dashboard-container { display:flex; flex-wrap:wrap; justify-content:space-around; }
    .section {
        background-color: #2a2a44; padding:20px; border-radius:12px;
        margin:10px; flex:1 1 30%; box-shadow: 0 4px 8px rgba(0,0,0,0.5);
    }
    .full-width-section { flex: 1 1 100%; }
    table {
        width:100%; border-collapse:collapse; margin-top:10px;
        border-radius:8px; overflow:hidden;
    }
    th, td { border:1px solid #4a4a6a; padding:8px; text-align:left; }
    th { background-color:#4a148c; color:white; font-weight:bold; }
    td { background-color:#3a3a5a; }
    tr:nth-child(even) td { background-color:#2a2a44; }
    .actions a, .actions button {
        margin-right:5px; padding:4px 8px; border:none; border-radius:4px;
        font-size:0.85em; cursor:pointer; text-decoration:none; color:white;
    }
    .approve { background-color:#4caf50; } .approve:hover { background-color:#45a049; }
    .reject { background-color:#f44336; } .reject:hover { background-color:#da190b; }
    .checkin { background-color:#2196f3; } .checkin:hover { background-color:#1e88e5; }
    .checkout { background-color:#ff9800; } .checkout:hover { background-color:#fb8c00; }
    .delete { background-color:#607d8b; } .delete:hover { background-color:#455a64; }
    .history { background-color: #9c27b0; } .history:hover { background-color: #8e24aa; }
    .status-badge {
        padding:3px 6px; border-radius:4px; color:white;
        font-size:0.85em; font-weight:bold;
    }
    .pending { background-color:#fbc02d; }
    .pending_approval { background-color: #ff9800; }
    .confirmed { background-color:#4caf50; }
    .cancelled { background-color:#9e9e9e; }
</style>
</head>
<body>
<h2>สวัสดี <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</h2>
<p>Admin mode ของผู้ดูแลระบบ</p>
<a href="logout.php">ออกจากระบบ</a>
<a href="admin_gm/admin_add_room.php" class="approve" style="margin-left: 10px;">เพิ่มห้องพักใหม่</a>
<a href="admin_gm/report.php" >รายงาน</a>
<hr>

<div class="dashboard-container">

    <div class="section">
        <h3>ข้อมูลลูกค้า</h3>
        <table>
            <thead><tr><th>Username</th><th>Email</th><th>เบอร์โทรศัพท์</th><th>การจัดการ</th></tr></thead>
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

    <div class="section">
        <h3>ข้อมูลแมว</h3>
        <table>
            <thead><tr><th>ชื่อแมว</th><th>เจ้าของ</th><th>สี</th><th>เพศ</th><th>การจัดการ</th></tr></thead>
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
    <div class="section full-width-section">
        <h3>ข้อมูลการจอง</h3>
        <table>
            <thead>
                <tr>
                    <th>ลูกค้า</th><th>ชื่อแมว</th><th>วันที่เข้าพัก</th><th>วันที่ออก</th>
                    <th>ห้อง</th><th>สถานะจอง</th><th>สถานะชำระ</th><th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($reservations)): foreach($reservations as $res): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['cat_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['date_from']); ?></td>
                        <td><?php echo htmlspecialchars($res['date_to']); ?></td>
                        <td><?php echo htmlspecialchars($res['room_number'] ?? '-'); ?></td>
                        <td>
                            <?php 
                                $status = $res['status'] ?? 'pending';
                                $labels = ['pending'=>'รอชำระเงิน','pending_approval'=>'รออนุมัติ','confirmed'=>'ยืนยันแล้ว','cancelled'=>'ยกเลิก'];
                                echo "<span class='status-badge {$status}'>".($labels[$status] ?? '')."</span>";
                                
                                if($res['check_in'] && !$res['check_out']) {
                                    echo "<br><span class='status-badge checkin'>เข้าพักแล้ว</span>";
                                } elseif ($res['check_out']) {
                                    echo "<br><span class='status-badge checkout'>เช็คเอาท์แล้ว</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <span style="color: <?php echo (int)$res['paid'] === 1 ? '#4caf50' : '#f44336'; ?>; font-weight: bold;">
                                <?php echo (int)$res['paid'] === 1 ? 'ชำระแล้ว' : 'ยังไม่ชำระ'; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <?php if($res['status']==='pending_approval'): ?>
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
                            <?php elseif($res['status']==='confirmed' && !$res['check_in']): ?>
                                <a href="admin_gm/admin_checkin.php?reservation_id=<?php echo $res['id']; ?>&room_id=<?php echo $res['room_id']; ?>" class="checkin">เช็คอิน</a>
                            <?php elseif($res['check_in'] && !$res['check_out']): ?>
                                <a href="admin_gm/admin_checkout.php?reservation_id=<?php echo $res['id']; ?>&room_id=<?php echo $res['room_id']; ?>" class="checkout">เช็คเอาท์</a>
                            <?php endif; ?>
                            <a href="admin_gm/delete_reservation.php?id=<?php echo $res['id']; ?>" class="delete" onclick="return confirm('คุณต้องการลบข้อมูลการจองนี้หรือไม่?');">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8">ไม่มีข้อมูลการจอง</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>