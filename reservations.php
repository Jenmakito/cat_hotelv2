<?php
// เริ่มต้นเซสชันและรวมไฟล์เชื่อมต่อฐานข้อมูล
session_start();
include 'db_connect.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่และมีบทบาทเป็น 'user'
// ถ้าไม่ใช่ ให้เปลี่ยนเส้นทางไปยังหน้าล็อกอิน
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// กำหนดตัวแปรสำหรับข้อความแจ้งเตือน
$error = '';
$success = '';

// ดึง id ผู้ใช้และข้อมูลที่เกี่ยวข้อง
$stmt = $conn->prepare("SELECT id, email, address, phone FROM users WHERE username=?");
$stmt->bind_param("s", $_SESSION['username']);  
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// ดึงข้อมูลแมวของผู้ใช้เพื่อใช้ในแบบฟอร์ม
$stmt = $conn->prepare("SELECT id, name FROM cats WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// เพิ่มการจองใหม่
if (isset($_POST['action']) && $_POST['action'] === 'add_reservation') {
    $cat_id = (int)$_POST['cat_id'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $room_type = $_POST['room_type'];
    // แก้ไข: ตรวจสอบว่ามีค่า total_cost ส่งมาหรือไม่ก่อนใช้งาน
    $total_cost = isset($_POST['total_cost']) ? (float)$_POST['total_cost'] : 0.00;
    $paid = isset($_POST['paid']) ? 1 : 0;

    // ตรวจสอบว่ามีการเลือกแมวหรือไม่
    if ($cat_id == 0) {
        $error = "กรุณาเลือกแมวสำหรับการจอง";
    } else {
        // แก้ไข: เพิ่ม total_cost ลงในคำสั่ง INSERT
        $stmt = $conn->prepare("INSERT INTO reservations (customer_id, cat_id, date_from, date_to, room_type, total_cost, paid) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissidi", $user_id, $cat_id, $date_from, $date_to, $room_type, $total_cost, $paid);
        if ($stmt->execute()) {
            $success = "จองห้องเรียบร้อยแล้ว!";
        } else {
            $error = "เกิดข้อผิดพลาดในการจองห้อง";
        }
        $stmt->close();
    }
}

// เพิ่มฟังก์ชันสำหรับยกเลิกการจอง
if (isset($_POST['action']) && $_POST['action'] === 'delete_reservation') {
    $reservation_id = (int)$_POST['reservation_id'];

    // ตรวจสอบว่าการจองนั้นเป็นของผู้ใช้ปัจจุบันก่อนทำการลบ
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success = "การจองถูกยกเลิกเรียบร้อยแล้ว!";
        } else {
            $error = "ไม่พบการจองที่ต้องการยกเลิกหรือคุณไม่มีสิทธิ์ลบการจองนี้";
        }
    } else {
        $error = "เกิดข้อผิดพลาดในการยกเลิกการจอง";
    }
    $stmt->close();
}

// ดึงข้อมูลการจองของผู้ใช้ทั้งหมด
$stmt = $conn->prepare("SELECT r.*, c.name as cat_name FROM reservations r JOIN cats c ON r.cat_id = c.id WHERE r.customer_id = ? ORDER BY r.date_from DESC");
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
    <title>การจองห้องพักของฉัน</title>
    <!-- Tailwind CSS -->
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
        <!-- Main Content (พื้นที่แสดงผลด้านขวา) -->
        <main>

            <h2 class="text-3xl font-bold mb-6 text-gray-800">การจองห้องพักของฉัน</h2>

            <!-- แสดงข้อความแจ้งเตือน -->
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

            <!-- ส่วนของแบบฟอร์มการจองใหม่ -->
            <div id="add-reservation-form" class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">จองห้องพักใหม่</h3>
                <form method="POST" class="space-y-4" onsubmit="calculatePrice()">
                    <input type="hidden" name="action" value="add_reservation">
                    <input type="hidden" id="total_cost_input" name="total_cost" value="0">

                    <!-- Dropdown สำหรับเลือกแมว -->
                    <div>
                        <label for="cat_id" class="block text-sm font-medium text-gray-700">เลือกแมว:</label>
                        <select id="cat_id" name="cat_id" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="0">--- เลือกแมว ---</option>
                            <?php foreach($cats as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- วันที่เข้าพัก -->
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700">วันที่เข้าพัก:</label>
                        <input type="date" id="date_from" name="date_from" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" onchange="calculatePrice()">
                    </div>

                    <!-- วันที่สิ้นสุดการเข้าพัก -->
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700">วันที่สิ้นสุด:</label>
                        <input type="date" id="date_to" name="date_to" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" onchange="calculatePrice()">
                    </div>

                    <!-- ประเภทห้อง -->
                    <div>
                        <label for="room_type" class="block text-sm font-medium text-gray-700">ประเภทห้อง:</label>
                        <select id="room_type" name="room_type" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" onchange="calculatePrice()">
                            <option value="standard">Standard (ห้องธรรมดา) - 150 บาท/คืน</option>
                            <option value="vip">VIP - 400 บาท/คืน</option>
                        </select>
                    </div>

                    <!-- ราคาที่คำนวณ -->
                    <div id="price_display" class="text-lg font-semibold text-gray-800">
                        ค่าใช้จ่ายทั้งหมด: 0 บาท
                    </div>

                    <!-- สถานะการชำระเงิน -->
                    <div class="flex items-center">
                        <input id="paid" name="paid" type="checkbox"
                               class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="paid" class="ml-2 block text-sm text-gray-900">ชำระเงินแล้ว</label>
                    </div>

                    <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        ยืนยันการจอง
                    </button>
                            <a href="user_index.php" class="block text-center mt-6 text-indigo-600 hover:underline">
                                กลับหน้า HOME
                            </a>
                </form>
            </div>

            <!-- ส่วนของข้อมูลการจองที่มีอยู่ -->
            <div id="current-reservations" class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">การจองปัจจุบันของคุณ</h3>
                
                <?php if (count($reservations) > 0): ?>
                    <div class="space-y-4">
                    <?php foreach($reservations as $res): ?>
                        <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200 flex justify-between items-center">
                            <div>
                                <h5 class="text-lg font-semibold text-indigo-800 mb-2">แมว: <?php echo htmlspecialchars($res['cat_name']); ?></h5>
                                <ul class="text-indigo-700 space-y-1">
                                    <li><span class="font-medium">วันที่เข้าพัก:</span> <?php echo htmlspecialchars($res['date_from']); ?></li>
                                    <li><span class="font-medium">วันที่สิ้นสุด:</span> <?php echo htmlspecialchars($res['date_to']); ?></li>
                                    <li><span class="font-medium">ประเภทห้อง:</span> <?php echo htmlspecialchars($res['room_type'] == 'standard' ? 'Standard (ห้องธรรมดา)' : 'VIP'); ?></li>
                                    <li><span class="font-medium">สถานะ:</span> <?php echo $res['paid'] ? 'ชำระเงินแล้ว' : 'ยังไม่ได้ชำระ'; ?></li>
                                    <li><span class="font-medium">ค่าใช้จ่ายทั้งหมด:</span> <?php echo number_format($res['total_cost'], 2); ?> บาท</li>
                                </ul>
                            </div>
                            <!-- Form สำหรับยกเลิกการจอง -->
                            <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกการจองนี้?');">
                                <input type="hidden" name="action" value="delete_reservation">
                                <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                                    ยกเลิก
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">คุณยังไม่มีการจองห้องพัก</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function calculatePrice() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const roomType = document.getElementById('room_type').value;
            const priceDisplay = document.getElementById('price_display');
            const totalCostInput = document.getElementById('total_cost_input');

            if (dateFrom && dateTo) {
                const start = new Date(dateFrom);
                const end = new Date(dateTo);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 

                let pricePerNight = 0;
                if (roomType === 'vip') {
                    pricePerNight = 400;
                } else {
                    pricePerNight = 150;
                }

                const totalCost = diffDays * pricePerNight;
                priceDisplay.textContent = `ค่าใช้จ่ายทั้งหมด: ${totalCost} บาท (${diffDays} คืน)`;
                totalCostInput.value = totalCost;
            } else {
                priceDisplay.textContent = `ค่าใช้จ่ายทั้งหมด: 0 บาท`;
                totalCostInput.value = 0;
            }
        }
    </script>
</body>
</html>
