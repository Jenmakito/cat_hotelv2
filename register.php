<?php
session_start();
include 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        // ตรวจสอบ username ซ้ำ
        $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "ชื่อผู้ใช้นี้มีอยู่แล้ว";
        } else {
            // สร้าง user ใหม่
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';

            $insert = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $username, $hashed_password, $role);

            if ($insert->execute()) {
                $success = "สมัครสมาชิกสำเร็จ!";
            } else {
                $error = "เกิดข้อผิดพลาด กรุณาลองใหม่";
            }

            $insert->close();
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ลงทะเบียน</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <form method="POST" action="">
        <h2>ลงทะเบียน</h2>
        <?php
            if($error) echo "<div class='error'>$error</div>";
            if($success) echo "<div class='success'>$success</div>";
        ?>
        <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
        <input type="password" name="password" placeholder="รหัสผ่าน" required>
        <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่าน" required>
        <button type="submit">สมัครสมาชิก</button>
        <p>มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
    </form>
</div>
</body>
</html>
