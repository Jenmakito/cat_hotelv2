<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit();
}

include '../db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $sql = "DELETE FROM reservations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('ลบข้อมูลการจองสำเร็จ'); window.location.href='../admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการลบข้อมูล: " . $stmt->error . "'); window.location.href='../admin_dashboard.php';</script>";
    }
    
    $stmt->close();
} else {
    echo "<script>alert('ไม่พบ ID ที่ต้องการลบ'); window.location.href='../admin_dashboard.php';</script>";
}

$conn->close();
?>