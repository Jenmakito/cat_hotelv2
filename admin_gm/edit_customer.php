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

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$customer = null;

// ถ้าส่งค่ามาแบบ POST (เมื่อมีการกดปุ่มบันทึก)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];

    $sql = "UPDATE users SET username=?, phone=?, email=?, address=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $full_name, $phone, $email, $address, $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('แก้ไขข้อมูลลูกค้าสำเร็จ'); window.location.href='../admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการแก้ไขข้อมูล'); window.location.href='../admin_dashboard.php';</script>";
    }
    $stmt->close();
}

// ถ้ามีการส่งค่า id มาจาก URL (เมื่อกดปุ่มแก้ไขใน Dashboard)
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM users WHERE id = ? AND role = 'user'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลลูกค้า</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a1a2e; /* สีพื้นหลังเข้ม */
            color: #e0e0e0; /* สีตัวอักษรหลัก */
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background-color: #2a2a44; /* สีพื้นหลังของกล่อง */
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.4); /* เงาเข้มขึ้น */
        }
        h2 {
            text-align: center;
            color: #9c27b0; /* สีม่วงเข้มสำหรับหัวข้อ */
        }
        label {
            display: block;
            margin-top: 10px;
            color: #b39ddb; /* สีม่วงอ่อนสำหรับป้ายกำกับ */
        }
        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
            background-color: #3a3a5a; /* สีพื้นหลังช่องข้อมูล */
            color: #e0e0e0;
            border: 1px solid #4a4a6a; /* เส้นขอบสีเข้ม */
        }
        button {
            background-color: #4a148c; /* สีม่วงเข้มสำหรับปุ่ม */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
        }
        button:hover {
            background-color: #7b1fa2; /* สีม่วงอ่อนลงเมื่อโฮเวอร์ */
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #b39ddb;
            text-decoration: none;
        }
        .back-link:hover {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>แก้ไขข้อมูลลูกค้า</h2>
        <?php if ($customer): ?>
        <form action="edit_customer.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($customer['id']); ?>">
            <label for="full_name">ชื่อผู้ใช้:</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($customer['username']); ?>" required>
            <label for="phone">เบอร์โทรศัพท์:</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>">
            <label for="email">อีเมล:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
            <label for="address">ที่อยู่:</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($customer['address']); ?></textarea>
            <button type="submit">บันทึกการแก้ไข</button>
        </form>
        <?php else: ?>
        <p style="text-align: center; color: #ff6666;">ไม่พบข้อมูลลูกค้าที่ต้องการแก้ไข</p>
        <?php endif; ?>
        <a href="../admin_dashboard.php" class="back-link">ย้อนกลับหน้า admin mode</a>
    </div>
</body>
</html>