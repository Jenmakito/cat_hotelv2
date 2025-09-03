<?php
// เริ่มต้นเซสชันและรวมไฟล์เชื่อมต่อฐานข้อมูล
session_start();
include 'db_connect.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่และมีบทบาทเป็น 'user'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูลห้องพักทั้งหมด
$stmt = $conn->prepare("SELECT id, room_number, room_type, status FROM rooms ORDER BY room_type, room_number");
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกห้องพัก</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-3xl font-bold mb-6 text-gray-800">เลือกห้องพักสำหรับแมวของคุณ</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($rooms as $room): ?>
                    <div class="p-4 rounded-lg shadow-sm border 
                        <?php 
                            if ($room['status'] == 'occupied') {
                                echo 'bg-gray-200 border-gray-400 text-gray-500';
                            } elseif ($room['room_type'] == 'vip') {
                                echo 'bg-yellow-100 border-yellow-300 text-yellow-800';
                            } else {
                                echo 'bg-blue-100 border-blue-300 text-blue-800';
                            }
                        ?>
                    ">
                        <h3 class="text-xl font-semibold">ห้อง: <?php echo htmlspecialchars($room['room_number']); ?></h3>
                        <p class="mt-2">
                            ประเภท: <span class="font-medium"><?php echo htmlspecialchars($room['room_type'] == 'standard' ? 'ห้องธรรมดา (150 บาท/คืน)' : 'VIP (400 บาท/คืน)'); ?></span>
                        </p>
                        <p class="mt-1">
                            สถานะ: <span class="font-medium">
                                <?php 
                                    if ($room['status'] == 'occupied') {
                                        echo 'ไม่ว่าง';
                                    } else {
                                        echo 'ว่าง';
                                    }
                                ?>
                            </span>
                        </p>
                        <div class="mt-4">
                            <?php if ($room['status'] == 'available'): ?>
                                <a href="user_reservations.php?room_id=<?php echo $room['id']; ?>" class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                                    เลือกห้องนี้
                                </a>
                            <?php else: ?>
                                <button class="block w-full text-center bg-gray-400 text-gray-600 font-bold py-2 px-4 rounded-md cursor-not-allowed" disabled>
                                    ห้องนี้ไม่ว่าง
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>