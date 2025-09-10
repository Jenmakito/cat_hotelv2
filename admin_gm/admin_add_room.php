<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

include '../db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = $_POST['room_number'] ?? '';
    $room_type = $_POST['room_type'] ?? '';
    $status = $_POST['status'] ?? 'available'; // กำหนดค่าเริ่มต้นเป็น 'available'

    // ตรวจสอบว่าข้อมูลไม่ว่างเปล่า
    if (!empty($room_number) && !empty($room_type)) {
        // ใช้ prepared statement เพื่อป้องกัน SQL Injection
        $stmt_insert = $conn->prepare("INSERT INTO rooms (room_number, room_type, status) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $room_number, $room_type, $status);

        if ($stmt_insert->execute()) {
            $message = "<p style='color:green;'>เพิ่มห้องพักสำเร็จ!</p>";
            // เปลี่ยนเส้นทางกลับไปที่หน้า dashboard หลังจาก 2 วินาที
            header("Refresh: 2; url=../admin_dashboard.php");
        } else {
            $message = "<p style='color:red;'>เกิดข้อผิดพลาดในการเพิ่มห้อง: " . $stmt_insert->error . "</p>";
        }

        $stmt_insert->close();
    } else {
        $message = "<p style='color:red;'>กรุณากรอกข้อมูลให้ครบถ้วน.</p>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>เพิ่มห้องพักใหม่</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #1a1a2e;
        color: #e0e0e0;
        padding: 20px;
    }
    .form-container {
        background-color: #2a2a44;
        padding: 20px;
        border-radius: 12px;
        max-width: 500px;
        margin: 50px auto;
        box-shadow: 0 4px 8px rgba(0,0,0,0.5);
    }
    h2 {
        color: #9c27b0;
        text-align: center;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 8px;
        box-sizing: border-box;
        border: 1px solid #4a4a6a;
        background-color: #3a3a5a;
        color: #e0e0e0;
        border-radius: 4px;
    }
    .btn-submit {
        display: block;
        width: 100%;
        padding: 10px;
        background-color: #4caf50;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 1em;
        cursor: pointer;
    }
    .btn-submit:hover {
        background-color: #45a049;
    }
    .back-link {
        display: block;
        text-align: center;
        margin-top: 10px;
        color: #b39ddb;
    }
</style>
</head>
<body>
<div class="form-container">
    <h2>เพิ่มห้องพักใหม่</h2>
    <?php echo $message; ?>
    <form action="admin_add_room.php" method="POST">
        <div class="form-group">
            <label for="room_number">หมายเลขห้อง:</label>
            <input type="text" id="room_number" name="room_number" required>
        </div>
        <div class="form-group">
            <label for="room_type">ประเภทห้อง:</label>
            <select id="room_type" name="room_type" required>
                <option value="standard">Standard</option>
                <option value="vip">VIP</option>
            </select>
        </div>
        <div class="form-group">
            <label for="status">สถานะห้อง:</label>
            <select id="status" name="status" required>
                <option value="available">ว่าง</option>
                <option value="occupied">ไม่ว่าง</option>
            </select>
        </div>
        <button type="submit" class="btn-submit">เพิ่มห้อง</button>
    </form>
    <a href="../admin_dashboard.php" class="back-link">กลับสู่หน้า Dashboard</a>
</div>
</body>
</html>