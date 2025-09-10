<?php
// เริ่มต้นเซสชันและรวมไฟล์เชื่อมต่อฐานข้อมูล
session_start();
include 'db_connect.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่และมีบทบาทเป็น 'user'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// กำหนดตัวแปรสำหรับข้อความแจ้งเตือน
$error = '';
$success = '';

// ดึง id ผู้ใช้
$stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// --- START FIX ---
// ตรวจสอบว่าผู้ใช้มีอยู่จริงหรือไม่ ถ้าไม่มี แสดงว่าเซสชันไม่ถูกต้อง
if (!$user) {
    // ทำลายเซสชันเก่าและส่งกลับไปหน้าล็อกอิน
    session_unset();
    session_destroy();
    header("Location: login.php?message=Invalid session. Please log in again.");
    exit();
}
// --- END FIX ---

$user_id = $user['id'];
$stmt->close();

// ดึงข้อมูลแมวของผู้ใช้
$stmt = $conn->prepare("SELECT id, name FROM cats WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ฟังก์ชันสำหรับสร้างรหัสเช็คอิน 6 หลัก
function generateCheckinCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// ส่วนสำหรับประมวลผลการจองและการยกเลิก
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_reservation') {
        $cat_id = (int)$_POST['cat_id'];
        $room_id = (int)$_POST['room_id'];
        $date_from = $_POST['date_from'];
        $date_to = $_POST['date_to'];
        $total_cost = (float)$_POST['total_cost'];
        
        // สถานะเริ่มต้นของการจอง
        $paid = 0;
        $status = 'pending'; // สถานะเริ่มต้นคือ 'pending'

        if ($cat_id == 0 || $room_id == 0) {
            $error = "กรุณาเลือกแมวและห้องสำหรับการจอง";
        } else {
            $checkin_code = generateCheckinCode();

            // --- START FIX ---
            // แก้ไข SQL ให้ถูกต้อง: ลบ room_type ออกจากคำสั่ง INSERT
            $stmt = $conn->prepare("INSERT INTO reservations (customer_id, cat_id, room_id, date_from, date_to, total_cost, paid, checkin_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // แก้ไข bind_param ให้สอดคล้องกับจำนวน parameter และ data type ที่ถูกต้อง
            $stmt->bind_param("iiissdiss", $user_id, $cat_id, $room_id, $date_from, $date_to, $total_cost, $paid, $checkin_code, $status);
            // --- END FIX ---

            if ($stmt->execute()) {
                $success = "จองห้องเรียบร้อยแล้ว! กรุณาไปที่หน้าชำระเงินเพื่อดำเนินการต่อ";
            } else {
                $error = "เกิดข้อผิดพลาดในการจองห้อง";
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete_reservation') {
        $reservation_id = (int)$_POST['reservation_id'];

        $stmt = $conn->prepare("SELECT room_id, status FROM reservations WHERE id = ? AND customer_id = ?");
        $stmt->bind_param("ii", $reservation_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $reservation_data = $result->fetch_assoc();
        
        if ($reservation_data) {
            $room_id = $reservation_data['room_id'];
            $res_status = $reservation_data['status'];
            $stmt->close();

            // ลบการจอง
            $stmt_delete = $conn->prepare("DELETE FROM reservations WHERE id = ? AND customer_id = ?");
            $stmt_delete->bind_param("ii", $reservation_id, $user_id);
            if ($stmt_delete->execute()) {
                $success = "การจองถูกยกเลิกเรียบร้อยแล้ว!";
                // อัปเดตสถานะห้องกลับเป็น 'available' เฉพาะเมื่อการจองได้รับการยืนยันแล้วเท่านั้น
                if ($res_status == 'confirmed') {
                    $update_stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                    $update_stmt->bind_param("i", $room_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            } else {
                $error = "เกิดข้อผิดพลาดในการยกเลิกการจอง";
            }
            $stmt_delete->close();
        } else {
            $error = "ไม่พบการจองที่ต้องการยกเลิก";
        }
    }
}

// ดึงข้อมูลห้องพักทั้งหมด
$stmt = $conn->prepare("SELECT id, room_number, room_type, status FROM rooms ORDER BY room_type, room_number");
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT r.*, c.name as cat_name, ro.room_number, ro.room_type FROM reservations r JOIN cats c ON r.cat_id = c.id JOIN rooms ro ON r.room_id = ro.id WHERE r.customer_id = ? ORDER BY r.date_from DESC");
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
        <main>
            <h2 class="text-3xl font-bold mb-6 text-gray-800">การจองห้องพักของฉัน</h2>
            
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
            
            <div id="room-selection-section" class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">เลือกห้องพักสำหรับจอง</h3>
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
                            <h4 class="text-xl font-semibold">ห้อง: <?php echo htmlspecialchars($room['room_number']); ?></h4>
                            <p class="mt-2">
                                ประเภท: <span class="font-medium"><?php echo htmlspecialchars($room['room_type'] == 'standard' ? 'ห้องธรรมดา' : 'VIP'); ?></span>
                            </p>
                            <p class="mt-1">
                                ราคา: <span class="font-medium"><?php echo htmlspecialchars($room['room_type'] == 'standard' ? '150' : '400'); ?> บาท/คืน</span>
                            </p>
                            <div class="mt-4">
                                <?php if ($room['status'] == 'available'): ?>
                                    <button onclick="selectRoom(<?php echo $room['id']; ?>, '<?php echo $room['room_number']; ?>', '<?php echo $room['room_type']; ?>')" class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                                        เลือกห้องนี้
                                    </button>
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

            <div id="add-reservation-form" class="bg-white p-6 rounded-lg shadow-md mb-6 hidden">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">จองห้องพักใหม่</h3>
                <p class="text-lg font-bold mb-4">
                    ห้องพักที่เลือก: <span id="selected_room_display" class="text-indigo-600"></span>
                </p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_reservation">
                    <input type="hidden" id="room_id_input" name="room_id" value="">
                    <input type="hidden" id="total_cost_input" name="total_cost" value="0">
                    <input type="hidden" id="room_type_hidden">

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

                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700">วันที่เข้าพัก:</label>
                        <input type="date" id="date_from" name="date_from" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" onchange="calculatePrice()">
                    </div>

                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700">วันที่สิ้นสุด:</label>
                        <input type="date" id="date_to" name="date_to" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" onchange="calculatePrice()">
                    </div>

                    <div id="price_display" class="text-lg font-semibold text-gray-800">
                        ค่าใช้จ่ายทั้งหมด: 0 บาท
                    </div>

                    <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        ยืนยันการจอง
                    </button>
                    <button type="button" onclick="cancelSelection()" class="w-full mt-2 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                        ยกเลิก
                    </button>
                </form>
            </div>
            
            <a href="user_index.php" class="block text-center mt-6 text-indigo-600 hover:underline">
                กลับหน้า HOME
            </a>

            <div id="current-reservations" class="bg-white p-6 rounded-lg shadow-md mt-6">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">การจองปัจจุบันของคุณ</h3>
                
                <?php if (count($reservations) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($reservations as $res): ?>
                            <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200 flex justify-between items-center">
                                <div>
                                    <h5 class="text-lg font-semibold text-indigo-800 mb-2">แมว: <?php echo htmlspecialchars($res['cat_name']); ?></h5>
                                    <ul class="text-indigo-700 space-y-1">
                                        <li><span class="font-medium">ห้องพัก:</span> <?php echo htmlspecialchars($res['room_number']); ?> (<?php echo htmlspecialchars($res['room_type'] == 'standard' ? 'ห้องธรรมดา' : 'VIP'); ?>)</li>
                                        <li><span class="font-medium">วันที่เข้าพัก:</span> <?php echo htmlspecialchars($res['date_from']); ?></li>
                                        <li><span class="font-medium">วันที่สิ้นสุด:</span> <?php echo htmlspecialchars($res['date_to']); ?></li>
                                        <li><span class="font-medium">สถานะ:</span> <?php echo $res['paid'] ? 'ชำระเงินแล้ว' : 'ยังไม่ได้ชำระ'; ?></li>
                                        <li><span class="font-medium">ค่าใช้จ่ายทั้งหมด:</span> <?php echo number_format($res['total_cost'], 2); ?> บาท</li>
                                    </ul>
                                </div>
                                <form method="POST" action="payments.php" onsubmit="return confirm('คุณต้องการชำระการจองนี้?');">
                                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                                        ชำระ
                                    </button>
                                </form>
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
        function selectRoom(roomId, roomNumber, roomType) {
            document.getElementById('room-selection-section').classList.add('hidden');
            document.getElementById('add-reservation-form').classList.remove('hidden');
            
            document.getElementById('selected_room_display').textContent = roomNumber + ' (' + (roomType === 'standard' ? 'ห้องธรรมดา' : 'VIP') + ')';
            document.getElementById('room_id_input').value = roomId;
            document.getElementById('room_type_hidden').value = roomType;
            
            // รีเซ็ตค่าวันที่และราคา
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            calculatePrice();
        }

        function cancelSelection() {
            document.getElementById('room-selection-section').classList.remove('hidden');
            document.getElementById('add-reservation-form').classList.add('hidden');
            
            // รีเซ็ตค่าในฟอร์ม
            document.getElementById('cat_id').value = '0';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            calculatePrice();
        }

        function calculatePrice() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const roomType = document.getElementById('room_type_hidden').value;
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
                } else if (roomType === 'standard') {
                    pricePerNight = 150;
                }

                const totalCost = diffDays * pricePerNight;
                priceDisplay.textContent = `ค่าใช้จ่ายทั้งหมด: ${totalCost.toLocaleString()} บาท (${diffDays} คืน)`;
                totalCostInput.value = totalCost;
            } else {
                priceDisplay.textContent = `ค่าใช้จ่ายทั้งหมด: 0 บาท`;
                totalCostInput.value = 0;
            }
        }
    </script>
</body>
</html>