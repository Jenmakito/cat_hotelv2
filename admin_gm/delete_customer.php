<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit();
}

include '../db_connect.php';

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตรวจสอบว่ามีการส่งค่า id มาหรือไม่
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // เตรียมคำสั่ง SQL เพื่อป้องกัน SQL Injection
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('ลบข้อมูลลูกค้าสำเร็จ'); window.location.href='../admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการลบข้อมูล: " . $stmt->error . "'); window.location.href='../admin_dashboard.php';</script>";
    }
    
    $stmt->close();
} else {
    echo "<script>alert('ไม่พบ ID ที่ต้องการลบ'); window.location.href='../admin_dashboard.php';</script>";
}

$conn->close();
?>