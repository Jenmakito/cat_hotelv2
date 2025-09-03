<?php
session_start();
// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php'; // เชื่อมต่อฐานข้อมูล

$message = '';
$error = '';
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

// ตรวจสอบว่ามี reservation_id และ room_id ส่งมาหรือไม่
if ($reservation_id === 0 || $room_id === 0) {
    header("Location: admin_dashboard.php");
    exit();
}

// เมื่อกดปุ่มยืนยันเช็คเอาท์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id_post = intval($_POST['reservation_id']);
    $room_id_post = intval($_POST['room_id']);

    $conn->begin_transaction();
    try {
        // 1. อัปเดตตาราง reservations: ตั้งค่า check_out = 1 และ status = 'completed'
        $stmt_res = $conn->prepare("UPDATE reservations SET check_out = 1, status = 'completed' WHERE id = ?");
        $stmt_res->bind_param("i", $reservation_id_post);
        $stmt_res->execute();
        $stmt_res->close();

        // 2. อัปเดตตาราง rooms: ตั้งค่า status = 'available'
        $stmt_room = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
        $stmt_room->bind_param("i", $room_id_post);
        $stmt_room->execute();
        $stmt_room->close();

        // ยืนยันการทำรายการ
        $conn->commit();
        $_SESSION['message'] = "เช็คเอาท์สำเร็จสำหรับรหัสการจอง: {$reservation_id_post}";
        header("Location: admin_dashboard.php");
        exit();
    } catch (mysqli_sql_exception $e) {
        // หากเกิดข้อผิดพลาด ให้ย้อนกลับ
        $conn->rollback();
        $error = "เกิดข้อผิดพลาดระหว่างการเช็คเอาท์: " . $e->getMessage();
    }
}

// ดึงข้อมูลการจองเพื่อแสดงยืนยัน
$stmt_info = $conn->prepare(
    "SELECT r.id, c.name as cat_name, u.username as customer_name, ro.room_number
     FROM reservations r
     JOIN users u ON r.customer_id = u.id
     JOIN cats c ON r.cat_id = c.id
     JOIN rooms ro ON r.room_id = ro.id
     WHERE r.id = ?"
);
$stmt_info->bind_param("i", $reservation_id);
$stmt_info->execute();
$reservation_info = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยืนยันการเช็คเอาท์</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: #e0e0e0; padding: 20px; text-align: center; }
        .container { background-color: #2a2a44; padding: 40px; border-radius: 12px; max-width: 500px; margin: 50px auto; box-shadow: 0 4px 8px rgba(0,0,0,0.5); }
        h2 { color: #ff9800; } /* สีส้มสำหรับ Checkout */
        .info { text-align: left; margin-bottom: 25px; line-height: 1.6; }
        .info span { font-weight: bold; color: #b39ddb; }
        button { padding: 12px 25px; border: none; border-radius: 8px; background-color: #ff9800; color: white; cursor: pointer; font-size: 1.1em; transition: background-color 0.2s; }
        button:hover { background-color: #fb8c00; }
        .back-link { display: inline-block; margin-top: 20px; color: #b39ddb; }
        .error { margin-top: 20px; padding: 10px; border-radius: 8px; background-color: #f44336; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h2>ยืนยันการเช็คเอาท์</h2>

    <?php if ($reservation_info): ?>
        <div class="info">
            <p><span>รหัสการจอง:</span> <?php echo htmlspecialchars($reservation_info['id']); ?></p>
            <p><span>ลูกค้า:</span> <?php echo htmlspecialchars($reservation_info['customer_name']); ?></p>
            <p><span>แมว:</span> <?php echo htmlspecialchars($reservation_info['cat_name']); ?></p>
            <p><span>ห้อง:</span> <?php echo htmlspecialchars($reservation_info['room_number']); ?></p>
        </div>
        <form method="POST">
            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">
            <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
            <button type="submit">ยืนยันการเช็คเอาท์</button>
        </form>
    <?php else: ?>
        <p class="error">ไม่พบข้อมูลการจองที่ระบุ</p>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back-link">กลับสู่หน้า Dashboard</a>
</div>
</body>
</html>