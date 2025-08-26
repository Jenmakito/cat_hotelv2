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
$user_email = $user['email'];
$user_address = $user['address'];
$user_phone = $user['phone'];
$stmt->close();

// ดึงข้อมูลแมวของผู้ใช้
$stmt = $conn->prepare("SELECT * FROM cats WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cats = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลแมวของฉัน</title>
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
    <div class="flex min-h-screen">
        <!-- Sidebar (เมนูด้านซ้าย) -->
        <aside class="w-64 bg-gray-800 text-white p-6 shadow-lg">
            <div class="text-xl font-semibold mb-8 text-center">CAT_HOTEL</div>
            <nav>
                <ul>
                    <li class="mb-2">
                        <a href="edit_profile.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">
                            เพิ่มข้อมูลลูกค้า
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="my_cats.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">
                            จัดการข้อมูลเเมว
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="reservations.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">
                            การจอง
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="payments.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">
                            ชำระเงิน
                        </a>
                    </li>
                    <li class="mt-8">
                        <a href="logout.php" class="block py-2 px-4 rounded-md bg-red-600 hover:bg-red-700 transition-colors duration-200 text-center">
                            ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content (พื้นที่แสดงผลด้านขวา) -->
        <main class="flex-1 p-8">
            <h2 class="text-3xl font-bold mb-6 text-gray-800">ยินดีต้อนรับ, <?php echo $_SESSION['username']; ?></h2>

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

            <!-- ส่วนแสดงข้อมูลผู้ใช้ -->
            <div class="mb-6 p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                <h4 class="text-lg font-semibold text-indigo-800 mb-2">ข้อมูลผู้ใช้</h4>
                <ul class="text-indigo-700 space-y-1">
                    <li><span class="font-medium">ชื่อผู้ใช้:</span> <?php echo htmlspecialchars($_SESSION['username']); ?></li>
                    <li><span class="font-medium">อีเมล:</span> <?php echo htmlspecialchars($user_email); ?></li>
                    <li><span class="font-medium">เบอร์โทรศัพท์:</span> <?php echo htmlspecialchars($user_phone); ?></li>
                    <li><span class="font-medium">ที่อยู่:</span> <?php echo htmlspecialchars($user_address); ?></li>
                </ul>
            </div>

            <!-- ส่วนของข้อมูลแมว -->
            <div id="cat-data" class="content-section bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">ข้อมูลแมวของฉัน</h3>
                
                <?php if (count($cats) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-lg overflow-hidden shadow">
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
                                    <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($cat['color']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo $cat['age']; ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo $cat['gender'] == 'male' ? 'ผู้' : 'เมีย'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">คุณยังไม่มีข้อมูลแมว</p>
                <?php endif; ?>
            </div>

            <!-- ส่วนของข้อมูลการจอง (Placeholder) -->
            <div id="reservations" class="content-section bg-white p-6 rounded-lg shadow-md hidden">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">ข้อมูลการจอง</h3>
                <p>ส่วนนี้จะแสดงข้อมูลการจอง</p>
            </div>

            <!-- ส่วนของข้อมูลการชำระเงิน (Placeholder) -->
            <div id="payments" class="content-section bg-white p-6 rounded-lg shadow-md hidden">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">ข้อมูลการชำระเงิน</h3>
                <p>ส่วนนี้จะแสดงข้อมูลการชำระเงิน</p>
            </div>
        </main>
    </div>

    <script>
        function showContent(sectionId) {
            // ซ่อนทุกส่วนเนื้อหา
            const sections = document.querySelectorAll('.content-section');
            sections.forEach(section => {
                section.classList.add('hidden');
            });

            // แสดงส่วนที่ต้องการ
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.remove('hidden');
            }
        }
        function showEditForm(catId) {
            // ฟังก์ชันนี้ถูกลบออกไปเนื่องจากไม่มีการแก้ไข
        }
        function hideEditForm(catId) {
            // ฟังก์ชันนี้ถูกลบออกไปเนื่องจากไม่มีการแก้ไข
        }
    </script>
</body>
</html>
