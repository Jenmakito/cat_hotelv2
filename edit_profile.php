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

// ดึงข้อมูลผู้ใช้ปัจจุบันจากฐานข้อมูล
$stmt = $conn->prepare("SELECT id, username, phone, email, address FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// ตรวจสอบวิธีการส่งข้อมูล (Method)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_password = $_POST['password'];
    $new_phone = trim($_POST['phone']);
    $new_email = trim($_POST['email']);
    $new_address = trim($_POST['address']);

    // ตรวจสอบว่าชื่อผู้ใช้ใหม่ซ้ำกับชื่อผู้ใช้ในระบบอื่นหรือไม่ (ยกเว้นชื่อผู้ใช้ปัจจุบัน)
    if ($new_username !== $user['username']) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $new_username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาเลือกชื่ออื่น";
            $check_stmt->close();
        }
    }

    // ถ้าไม่มีข้อผิดพลาด ให้ทำการอัปเดตข้อมูล
    if (empty($error)) {
        // อัปเดตข้อมูลผู้ใช้
        if (!empty($new_password)) {
            // ถ้ามีการป้อนรหัสผ่านใหม่ ให้ทำการแฮชรหัสผ่าน
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET username = ?, password = ?, phone = ?, email = ?, address = ? WHERE id = ?");
            $update->bind_param("sssssi", $new_username, $hashed_password, $new_phone, $new_email, $new_address, $user['id']);
        } else {
            // ถ้าไม่ได้ป้อนรหัสผ่านใหม่ ให้อัปเดตเฉพาะชื่อผู้ใช้และข้อมูลอื่น ๆ
            $update = $conn->prepare("UPDATE users SET username = ?, phone = ?, email = ?, address = ? WHERE id = ?");
            $update->bind_param("ssssi", $new_username, $new_phone, $new_email, $new_address, $user['id']);
        }

        if ($update->execute()) {
            $success = "แก้ไขข้อมูลเรียบร้อยแล้ว!";
            $_SESSION['username'] = $new_username; // อัปเดตชื่อผู้ใช้ในเซสชัน
            // ดึงข้อมูลผู้ใช้ล่าสุดอีกครั้งเพื่อแสดงในฟอร์ม
            $user['username'] = $new_username;
            $user['phone'] = $new_phone;
            $user['email'] = $new_email;
            $user['address'] = $new_address;
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
        }
        $update->close();
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-semibold text-center mb-6 text-gray-800">แก้ไขข้อมูลส่วนตัว</h2>

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

        <!-- ฟอร์มแก้ไขข้อมูล -->
        <form method="POST" action="" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 font-medium mb-1">ชื่อผู้ใช้</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="password" class="block text-gray-700 font-medium mb-1">รหัสผ่านใหม่</label>
                <input type="password" name="password" id="password" placeholder="เว้นว่างถ้าไม่เปลี่ยน"
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="phone" class="block text-gray-700 font-medium mb-1">เบอร์โทรศัพท์</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="email" class="block text-gray-700 font-medium mb-1">อีเมล</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="address" class="block text-gray-700 font-medium mb-1">ที่อยู่</label>
                <textarea name="address" id="address" rows="3"
                          class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>
            <div class="flex justify-center">
                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    บันทึกข้อมูล
                </button>
            </div>
        </form>

        <a href="user_index.php" class="block text-center mt-6 text-indigo-600 hover:underline">
            กลับหน้า HOME
        </a>
    </div>
</body>
</html>
