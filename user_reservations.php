<?php
session_start();
include 'db_connect.php';

// ตรวจสอบการล็อกอินและบทบาท
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// ดึงการจองทั้งหมดของผู้ใช้พร้อมข้อมูลแมว
$reservations = [];
$stmt = $conn->prepare("
    SELECT 
        r.id, 
        c.name AS cat_name, 
        r.checkin_code, 
        r.check_in, 
        r.paid
    FROM reservations r
    JOIN cats c ON r.cat_id = c.id
    WHERE r.customer_id = ?
    ORDER BY r.date_from DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สถานะการจอง</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .pending { background-color: #fcd34d; color: #92400e; }
    .confirmed { background-color: #d1fae5; color: #065f46; }
    .checked-in { background-color: #bfdbfe; color: #1e40af; }
</style>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex min-h-screen">
    <aside class="w-64 bg-gray-800 text-white p-6 shadow-lg">
        <div class="text-xl font-semibold mb-8 text-center">CAT_HOTEL</div>
        <nav>
            <ul>
                <li class="mb-2"><a href="user_index.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">หน้าหลัก</a></li>
                <li class="mb-2"><a href="my_cats.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">จัดการข้อมูลแมว</a></li>
                <li class="mb-2"><a href="reservations.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">การจอง</a></li>
                <li class="mb-2"><a href="user_reservations.php" class="block w-full py-2 px-4 rounded-md bg-gray-700">สถานะการจอง</a></li>
                <li class="mb-2"><a href="payments.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">ชำระเงิน</a></li>
                <li class="mt-8"><a href="logout.php" class="block py-2 px-4 rounded-md bg-red-600 hover:bg-red-700 text-center">ออกจากระบบ</a></li>
            </ul>
        </nav>
    </aside>

    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">สถานะการจองของคุณ</h2>

        <div class="bg-white p-6 rounded-lg shadow-md my-6">
            <h3 class="text-2xl font-semibold mb-4 text-gray-700">รายการการจอง</h3>
            <?php if(count($reservations) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden shadow-md">
                        <thead class="bg-gray-200">
                            <tr class="text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">ชื่อแมว</th>
                                <th class="py-3 px-6 text-left">รหัสเช็คอิน</th>
                                <th class="py-3 px-6 text-left">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach($reservations as $res): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6"><?php echo htmlspecialchars($res['cat_name']); ?></td>
                                <td class="py-3 px-6 font-bold text-gray-800"><?php echo htmlspecialchars($res['checkin_code'] ?? 'ไม่มีรหัส'); ?></td>
                                <td class="py-3 px-6">
                                    <?php if ($res['check_in'] == 1): ?>
                                        <span class="status-badge checked-in">เช็คอินแล้ว</span>
                                    <?php elseif ($res['paid'] == 1): ?>
                                        <span class="status-badge confirmed">ชำระเงินแล้ว</span>
                                    <?php else: ?>
                                        <span class="status-badge pending">รอยืนยัน</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">คุณยังไม่มีการจอง</p>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>