<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
</head>
<body>
<h2>สวัสดี <?php echo $_SESSION['username']; ?> (Admin)</h2>
<p>นี่คือหน้า Dashboard ของผู้ดูแลระบบ</p>
<a href="logout.php">ออกจากระบบ</a>
</body>
</html>
