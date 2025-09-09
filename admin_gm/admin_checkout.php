<?php
session_start();

// Check if the user is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
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

$message = '';
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $room_id = intval($_POST['room_id'] ?? 0);

    if ($reservation_id > 0 && $room_id > 0) {
        $conn->begin_transaction();
        try {
            // Update reservation status to completed and set checkout time
            $stmt_res = $conn->prepare("UPDATE reservations SET status = 'completed', check_out = NOW() WHERE id = ?");
            $stmt_res->bind_param("i", $reservation_id);
            $stmt_res->execute();
            $stmt_res->close();

            // Update room status to available
            $stmt_room = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $stmt_room->bind_param("i", $room_id);
            $stmt_room->execute();
            $stmt_room->close();

            $conn->commit();
            $_SESSION['message'] = "Check-out successful! Reservation ID: {$reservation_id}, Room ID: {$room_id}";
            header("Location: ../admin_dashboard.php"); // Redirect back to dashboard
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Error during check-out: " . $e->getMessage();
        }
    } else {
        $message = "Invalid reservation or room ID provided.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Check-out Reservation</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: #e0e0e0; padding: 20px; text-align: center; }
        .container { background-color: #2a2a44; padding: 40px; border-radius: 12px; max-width: 400px; margin: 50px auto; box-shadow: 0 4px 8px rgba(0,0,0,0.5); }
        h2 { color: #9c27b0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        button { padding: 10px 20px; border: none; border-radius: 8px; color: white; cursor: pointer; font-size: 1em; margin: 5px; }
        .confirm-btn { background-color: #f44336; } /* Red for checkout */
        .confirm-btn:hover { background-color: #e53935; }
        .cancel-btn { background-color: #607d8b; }
        .cancel-btn:hover { background-color: #546e7a; }
        .message { margin-top: 20px; padding: 10px; border-radius: 8px; }
        .error { background-color: #f44336; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h2>ยืนยันการเช็คเอาท์</h2>
    <?php if ($message) { ?>
        <div class="message error"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>

    <?php if ($reservation_id > 0 && $room_id > 0) { ?>
        <p>คุณกำลังจะทำการเช็คเอาท์</p>
        <form method="POST" action="admin_checkout.php">
            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">
            <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
            <button type="submit" class="confirm-btn">ยืนยันการเช็คเอาท์</button>
            <button type="button" class="cancel-btn" onclick="window.history.back();">ย้อนกลับ</button>
        </form>
    <?php } else { ?>
        <p>กรุณาระบุ Reservation ID และ Room ID ที่ถูกต้อง</p>
        <button type="button" class="cancel-btn" onclick="window.location.href='../admin_dashboard.php';">กลับหน้าหลัก</button>
    <?php } ?>
</div>
</body>
</html>