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

// ดึง id ผู้ใช้
$stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// เพิ่ม/แก้ไข/ลบแมว
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $name = trim($_POST['name']);
        $color = trim($_POST['color']);
        $age = (int)$_POST['age'];
        $gender = $_POST['gender'];

        if (empty($name)) {
            $error = "กรุณาป้อนชื่อแมว";
        } else {
            $stmt = $conn->prepare("INSERT INTO cats (user_id, name, color, age, gender) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $user_id, $name, $color, $age, $gender);
            if ($stmt->execute()) {
                $success = "เพิ่มแมวเรียบร้อยแล้ว!";
            } else {
                $error = "เกิดข้อผิดพลาดในการเพิ่มแมว";
            }
            $stmt->close();
        }
    } elseif ($action === 'edit') {
        $cat_id = (int)$_POST['cat_id'];
        $name = trim($_POST['name']);
        $color = trim($_POST['color']);
        $age = (int)$_POST['age'];
        $gender = $_POST['gender'];

        if (empty($name)) {
            $error = "กรุณาป้อนชื่อแมว";
        } else {
            $stmt = $conn->prepare("UPDATE cats SET name = ?, color = ?, age = ?, gender = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssiiii", $name, $color, $age, $gender, $cat_id, $user_id);
            if ($stmt->execute()) {
                $success = "แก้ไขข้อมูลแมวเรียบร้อยแล้ว!";
            } else {
                $error = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลแมว";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $cat_id = (int)$_POST['cat_id'];

        $stmt = $conn->prepare("DELETE FROM cats WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cat_id, $user_id);
        if ($stmt->execute()) {
            $success = "ลบแมวเรียบร้อยแล้ว!";
        } else {
            $error = "เกิดข้อผิดพลาดในการลบแมว";
        }
        $stmt->close();
    }
}

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
<body class="bg-gray-100 font-sans flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-4xl">
        <h2 class="text-3xl font-bold mb-6 text-gray-800 text-center">จัดการข้อมูลแมวของฉัน</h2>
        
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
        
        <div class="mb-8">
            <h4 class="text-xl font-semibold mb-4 text-gray-700">เพิ่มแมวใหม่</h4>
            <form method="POST" class="space-y-4 flex flex-col items-center md:items-stretch">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="ชื่อ" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                <input type="text" name="color" placeholder="สี"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                <input type="number" name="age" placeholder="อายุ" min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="male">ผู้</option>
                    <option value="female">เมีย</option>
                </select>
                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    เพิ่มแมว
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <h4 class="text-xl font-semibold mb-4 text-gray-700">รายชื่อแมวของฉัน</h4>
            <table class="min-w-full bg-white rounded-lg overflow-hidden shadow">
                <thead class="bg-gray-200">
                    <tr class="text-gray-700 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">ชื่อแมว</th>
                        <th class="py-3 px-6 text-left">สีแมว</th>
                        <th class="py-3 px-6 text-left">อายุ</th>
                        <th class="py-3 px-6 text-left">เพศ</th>
                        <th class="py-3 px-6 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach($cats as $cat): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($cat['color']); ?></td>
                        <td class="py-3 px-6 text-left"><?php echo $cat['age']; ?></td>
                        <td class="py-3 px-6 text-left"><?php echo $cat['gender'] == 'male' ? 'ผู้' : 'เมีย'; ?></td>
                        <td class="py-3 px-6 text-center">
                            <div class="flex item-center justify-center">
                                <!-- ปุ่มแก้ไข -->
                                <button type="button" onclick="showEditForm(<?php echo $cat['id']; ?>)" class="w-6 h-6 mr-2 transform hover:text-blue-500 hover:scale-110">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                <!-- ปุ่มลบ -->
                                <form method="POST" class="inline-block" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบแมวตัวนี้?');">
                                    <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="w-6 h-6 transform hover:text-red-500 hover:scale-110">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.34 21H7.66a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="edit-form-<?php echo $cat['id']; ?>" class="hidden border-t border-gray-200 bg-gray-50">
                        <td colspan="5" class="py-4 px-4">
                            <form method="POST" class="space-y-2">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                <div class="flex flex-wrap gap-2 items-center">
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" placeholder="ชื่อ" required
                                           class="flex-1 min-w-[120px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <input type="text" name="color" value="<?php echo htmlspecialchars($cat['color']); ?>" placeholder="สี"
                                           class="flex-1 min-w-[120px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <input type="number" name="age" value="<?php echo $cat['age']; ?>" placeholder="อายุ" min="0"
                                           class="flex-1 min-w-[100px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <select name="gender" class="flex-1 min-w-[100px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="male" <?php if($cat['gender']=='male') echo 'selected'; ?>>ผู้</option>
                                        <option value="female" <?php if($cat['gender']=='female') echo 'selected'; ?>>เมีย</option>
                                    </select>
                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                                        บันทึก
                                    </button>
                                    <button type="button" onclick="hideEditForm(<?php echo $cat['id']; ?>)" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-md transition-colors duration-200">
                                        ยกเลิก
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-8 text-center">
             <a href="user_index.php" class="text-indigo-600 hover:underline inline-block">กลับหน้า HOME</a>
        </div>
    </div>
    
    <script>
        function showEditForm(catId) {
            const form = document.getElementById(`edit-form-${catId}`);
            if (form) {
                form.classList.remove('hidden');
            }
        }
        function hideEditForm(catId) {
            const form = document.getElementById(`edit-form-${catId}`);
            if (form) {
                form.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
