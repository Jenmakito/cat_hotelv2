<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit();
}

include '../db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$message = '';
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_code = trim($_POST['check_in_code'] ?? '');
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $room_id = intval($_POST['room_id'] ?? 0);

    if ($reservation_id > 0 && !empty($submitted_code)) {
        // --- START FIX ---
        // แก้ไข 'check_in_code' เป็น 'checkin_code'
        $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ? AND checkin_code = ? AND status = 'confirmed' AND check_in = 0");
        // --- END FIX ---
        $stmt->bind_param("is", $reservation_id, $submitted_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $conn->begin_transaction();
            try {
                // Update check_in status in reservations table
                $stmt_res = $conn->prepare("UPDATE reservations SET check_in = 1 WHERE id = ?");
                $stmt_res->bind_param("i", $reservation_id);
                $stmt_res->execute();
                $stmt_res->close();

                // Update room status to occupied
                $stmt_room = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $stmt_room->bind_param("i", $room_id);
                $stmt_room->execute();
                $stmt_room->close();

                $conn->commit();
                $_SESSION['message'] = "Check-in successful! Reservation ID: {$reservation_id}";
                header("Location: ../admin_dashboard.php"); // Redirect back to dashboard
                exit();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $message = "Error during check-in: " . $e->getMessage();
            }
        } else {
            $message = "Invalid check-in code, reservation not confirmed, or already checked in.";
        }
    } else {
        $message = "Please enter the check-in code.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Check-in Reservation</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: #e0e0e0; padding: 20px; text-align: center; }
        .container { background-color: #2a2a44; padding: 40px; border-radius: 12px; max-width: 400px; margin: 50px auto; box-shadow: 0 4px 8px rgba(0,0,0,0.5); }
        h2 { color: #9c27b0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #4a4a6a; background-color: #3a3a5a; color: #e0e0e0; border-radius: 8px; box-sizing: border-box; }
        button { padding: 10px 20px; border: none; border-radius: 8px; background-color: #4caf50; color: white; cursor: pointer; font-size: 1em; }
        button:hover { background-color: #45a049; }
        .message { margin-top: 20px; padding: 10px; border-radius: 8px; }
        .error { background-color: #f44336; color: white; }
        .info { background-color: #2196f3; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h2>ยืนยันการเช็คอิน</h2>
    <?php if ($message): ?>
        <div class="message error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="POST" action="admin_checkin.php">
        <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">
        <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
        <div class="form-group">
            <label for="check_in_code">กรุณาใส่รหัสเช็คอิน:</label>
            <input type="text" id="check_in_code" name="check_in_code" required>
        </div>
        <button type="submit">ยืนยัน</button>
    </form>
</div>
</body>
</html>