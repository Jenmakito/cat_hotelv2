<?php
session_start();
include 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // ตรวจสอบ role แล้ว redirect
            if($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user_index.php");
            }
            exit();
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบผู้ใช้";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจองห้องพักแมว</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <form method="POST" action="">
            <h2>เข้าสู่ระบบ</h2>
            <?php if($error != '') { echo "<div class='error'>$error</div>"; } ?>
            <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
            <input type="password" name="password" placeholder="รหัสผ่าน" required>
            <button type="submit">เข้าสู่ระบบ</button>
            <p>ยังไม่มีบัญชี? <a href="register.php">ลงทะเบียน</a></p>
        </form>
    </div>
</body>
</html>
