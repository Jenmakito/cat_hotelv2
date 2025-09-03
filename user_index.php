<?php
session_start();
include 'db_connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// ข้อความแจ้งเตือน
$error = '';
$success = '';

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT id, username, email, phone, address FROM users WHERE username=?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$user_email = $user['email'];
$user_phone = $user['phone'];
$user_address = $user['address'];
$stmt->close();

// ดึงข้อมูลแมวของผู้ใช้
$stmt = $conn->prepare("SELECT * FROM cats WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ดึงการจองที่ยังไม่ได้ชำระ
$stmt = $conn->prepare("
    SELECT r.id, c.name AS cat_name, r.date_from, r.date_to, r.room_type, r.total_cost
    FROM reservations r
    JOIN cats c ON r.cat_id = c.id
    WHERE r.customer_id = ? AND r.paid = 0
    ORDER BY r.date_from ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unpaid_reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการข้อมูลแมวและการจอง</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
</style>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white p-6 shadow-lg">
        <div class="text-xl font-semibold mb-8 text-center">CAT_HOTEL</div>
        <nav>
            <ul>
                <li class="mb-2"><a href="edit_profile.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">เเก้ไขข้อมูลส่วนตัว</a></li>
                <li class="mb-2"><a href="my_cats.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">จัดการข้อมูลแมว</a></li>
                <li class="mb-2"><a href="reservations.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">การจอง</a></li>
                <li class="mb-2"><a href="user_reservations.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">เช็คอินเข้าพัก</a></li>
                <li class="mb-2"><a href="payments.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">ชำระเงิน</a></li>
                <li class="mt-8"><a href="logout.php" class="block py-2 px-4 rounded-md bg-red-600 hover:bg-red-700 text-center">ออกจากระบบ</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>

        <!-- ข้อมูลผู้ใช้ -->
        <div class="mb-6 p-4 bg-indigo-50 rounded-lg border border-indigo-200">
            <h4 class="text-lg font-semibold text-indigo-800 mb-2">ข้อมูลผู้ใช้</h4>
            <ul class="text-indigo-700 space-y-1">
                <li><span class="font-medium">ชื่อผู้ใช้:</span> <?php echo htmlspecialchars($user['username']); ?></li>
                <li><span class="font-medium">อีเมล:</span> <?php echo htmlspecialchars($user_email); ?></li>
                <li><span class="font-medium">เบอร์โทรศัพท์:</span> <?php echo htmlspecialchars($user_phone); ?></li>
                <li><span class="font-medium">ที่อยู่:</span> <?php echo htmlspecialchars($user_address); ?></li>
            </ul>
        </div>

        <!-- ตารางแมว -->
        <div class="bg-white p-6 rounded-lg shadow-md my-6">
            <h3 class="text-2xl font-semibold mb-4 text-gray-700">ข้อมูลแมวของฉัน</h3>
            <?php if(count($cats) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden shadow-md">
                        <thead class="bg-gray-200">
                            <tr class="text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">ชื่อแมว</th>
                                <th class="py-3 px-6 text-left">สีแมว</th>
                                <th class="py-3 px-6 text-left">อายุ</th>
                                <th class="py-3 px-6 text-left">เพศ</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach($cats as $cat): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6"><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($cat['color']); ?></td>
                                <td class="py-3 px-6"><?php echo $cat['age']; ?></td>
                                <td class="py-3 px-6"><?php echo $cat['gender'] == 'male' ? 'ผู้' : 'เมีย'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">คุณยังไม่มีข้อมูลแมว</p>
            <?php endif; ?>
        </div>

        <!-- ตารางการจองที่ยังไม่ได้ชำระ แสดงยอดทันที -->
        <div class="bg-white p-6 rounded-lg shadow-md my-6">
            <h3 class="text-2xl font-semibold mb-4 text-gray-700">การจองที่ยังไม่ได้ชำระ</h3>
            <?php if(count($unpaid_reservations) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden shadow-md">
                        <thead class="bg-gray-200">
                            <tr class="text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">แมว</th>
                                <th class="py-3 px-6 text-left">วันที่เริ่ม</th>
                                <th class="py-3 px-6 text-left">วันที่สิ้นสุด</th>
                                <th class="py-3 px-6 text-left">ประเภทห้อง</th>
                                <th class="py-3 px-6 text-left">ยอดชำระ</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach($unpaid_reservations as $res): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6"><?php echo htmlspecialchars($res['cat_name']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($res['date_from']); ?></td>
                                <td class="py-3 px-6"><?php echo htmlspecialchars($res['date_to']); ?></td>
                                <td class="py-3 px-6"><?php echo !empty($res['room_type']) ? htmlspecialchars($res['room_type']) : '-'; ?></td>
                                <td class="py-3 px-6"><?php echo number_format($res['total_cost'], 2) . " บาท"; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">คุณยังไม่มีการจองที่ต้องชำระ</p>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>
