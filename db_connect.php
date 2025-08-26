<?php
$servername = "localhost";
$username = "root";
$password = ""; // ค่าเริ่มต้นของ XAMPP
$dbname = "cathotel_db";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
