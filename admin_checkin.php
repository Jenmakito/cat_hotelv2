<?php
// เริ่มต้นเซสชันและรวมไฟล์เชื่อมต่อฐานข้อมูล
session_start();
include 'db_connect.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่และมีบทบาทเป็น 'admin'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// ส่วนสำหรับประมวลผลการเช็คอิน
if (isset($_POST['action']) && $_POST['action'] === 'check_in_code') {
    $reservation_id = (int)$_POST['reservation_id'];
    $submitted_code = $_POST['check_in_code'];

    // ตรวจสอบว่ารหัสเช็คอินและ ID การจองถูกต้องหรือไม่
    $stmt = $conn->prepare("SELECT id, room_id FROM reservations WHERE id = ? AND check_in_code = ? AND check_in = 0");
    $stmt->bind_param("is", $reservation_id, $submitted_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation_data = $result->fetch_assoc();
    $stmt->close();

    if ($reservation_data) {
        // ถ้ารหัสถูกต้อง ให้อัปเดตสถานะเช็คอินและสถานะห้อง
        $update_res_stmt = $conn->prepare("UPDATE reservations SET check_in = 1 WHERE id = ?");
        $update_res_stmt->bind_param("i", $reservation_id);
        $update_res_stmt->execute();
        $update_res_stmt->close();
        
        $update_room_stmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
        $update_room_stmt->bind_param("i", $reservation_data['room_id']);
        $update_room_stmt->execute();
        $update_room_stmt->close();

        $success = "การเช็คอินสำเร็จแล้ว! ห้องพักถูกอัปเดตสถานะเป็นไม่ว่าง";
    } else {
        $error = "รหัสเช็คอินหรือ ID การจองไม่ถูกต้อง หรือมีการเช็คอินไปแล้ว";
    }
}

// ส่วนสำหรับประมวลผลการเช็คเอาท์
if (isset($_POST['action']) && $_POST['action'] === 'check_out_admin') {
    $reservation_id = (int)$_POST['reservation_id'];

    // ตรวจสอบสถานะการจอง
    $stmt = $conn->prepare("SELECT room_id FROM reservations WHERE id = ? AND check_in = 1 AND check_out = 0");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation_data = $result->fetch_assoc();
    $stmt->close();

    if ($reservation_data) {
        // อัปเดตสถานะการเช็คเอาท์
        $update_res_stmt = $conn->prepare("UPDATE reservations SET check_out = 1 WHERE id = ?");
        $update_res_stmt->bind_param("i", $reservation_id);
        $update_res_stmt->execute();
        $update_res_stmt->close();

        // อัปเดตสถานะห้องพักให้ว่าง
        $update_room_stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
        $update_room_stmt->bind_param("i", $reservation_data['room_id']);
        $update_room_stmt->execute();
        $update_room_stmt->close();

        $success = "การเช็คเอาท์สำเร็จแล้ว! ห้องพักถูกอัปเดตสถานะเป็นว่าง";
    } else {
        $error = "ไม่สามารถเช็คเอาท์ได้ การจองนี้อาจไม่ได้เช็คอินหรือเช็คเอาท์ไปแล้ว";
    }
}


// ดึงรายการการจองทั้งหมดที่ยังไม่ได้เช็คอิน
$stmt = $conn->prepare("SELECT r.*, c.name as cat_name, u.username as customer_name, ro.room_number FROM reservations r JOIN cats c ON r.cat_id = c.id JOIN users u ON r.customer_id = u.id JOIN rooms ro ON r.room_id = ro.id WHERE r.check_in = 0 AND r.check_out = 0 ORDER BY r.date_from DESC");
$stmt->execute();
$pending_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ดึงรายการการจองที่เช็คอินแล้ว
$stmt = $conn->prepare("SELECT r.*, c.name as cat_name, u.username as customer_name, ro.room_number FROM reservations r JOIN cats c ON r.cat_id = c.id JOIN users u ON r.customer_id = u.id JOIN rooms ro ON r.room_id = ro.id WHERE r.check_in = 1 AND r.check_out = 0 ORDER BY r.date_to ASC");
$stmt->execute();
$checked_in_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าผู้ดูแลระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-8">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">หน้าผู้ดูแลระบบ: เช็คอิน/เช็คเอาท์</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-2xl font-semibold mb-4 text-gray-700">ยืนยันการเช็คอินด้วยรหัส</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="check_in_code">
                <div>
                    <label for="reservation_id" class="block text-sm font-medium text-gray-700">ID การจอง:</label>
                    <input type="text" id="reservation_id" name="reservation_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="check_in_code" class="block text-sm font-medium text-gray-700">รหัสเช็คอิน:</label>
                    <input type="text" id="check_in_code" name="check_in_code" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md">
                    ยืนยันเช็คอิน
                </button>
            </form>
        </div>

        <a href="admin_index.php" class="block text-center mt-6 text-indigo-600 hover:underline">
            กลับหน้า ADMIN
        </a>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">รายการที่รอการเช็คอิน</h3>
                <?php if (!empty($pending_reservations)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้จอง</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ห้อง</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัสเช็คอิน</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pending_reservations as $res): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($res['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($res['customer_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($res['room_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-bold"><?php echo htmlspecialchars($res['check_in_code']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">ไม่มีการจองที่รอการเช็คอิน</p>
                <?php endif; ?>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">รายการที่เช็คอินแล้ว</h3>
                <?php if (!empty($checked_in_reservations)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้จอง</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ห้อง</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">การกระทำ</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($checked_in_reservations as $res): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($res['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($res['customer_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($res['room_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="check_out_admin">
                                            <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md transition-colors duration-200" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการเช็คเอาท์การจองนี้?');">
                                                เช็คเอาท์
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">ไม่มีการจองที่เช็คอินแล้ว</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</body>
</html>