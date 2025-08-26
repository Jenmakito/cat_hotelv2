<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>หน้าหลัก - ระบบจองห้องพักแมว</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h1>ยินดีต้อนรับ, <?php echo $_SESSION['username']; ?>!</h1>
<p>นี่คือหน้าหลักของเว็บไซต์</p>

<nav>
    <a href="user_dashboard.php">Dashboard</a> | 
    <a href="logout.php">ออกจากระบบ</a>
</nav>
</body>
</html>
