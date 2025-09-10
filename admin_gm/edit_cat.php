<?php
session_start();
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php");
    exit();
}

include '../db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$cat = null;
$owners = [];

// ดึงข้อมูลเจ้าของแมวทั้งหมดจากตาราง users เพื่อใช้ใน dropdown menu
$sql_owners = "SELECT id, username FROM users WHERE role = 'user'";
$result_owners = $conn->query($sql_owners);
if ($result_owners->num_rows > 0) {
    while($row = $result_owners->fetch_assoc()) {
        $owners[] = $row;
    }
}

// ถ้ามีการส่งค่ามาแบบ POST (เมื่อมีการกดปุ่มบันทึก)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $color = $_POST['color'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $user_id = $_POST['user_id'];

    $sql_update = "UPDATE cats SET name=?, color=?, age=?, gender=?, user_id=? WHERE id=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssisii", $name, $color, $age, $gender, $user_id, $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('แก้ไขข้อมูลแมวสำเร็จ'); window.location.href='../admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $stmt->error . "'); window.location.href='../admin_dashboard.php';</script>";
    }
    $stmt->close();
}

// ถ้ามีการส่งค่า id มาจาก URL (เมื่อกดปุ่มแก้ไขใน Dashboard)
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql_select = "SELECT * FROM cats WHERE id = ?";
    $stmt = $conn->prepare($sql_select);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cat = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลแมว</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: #e0e0e0; padding: 20px; }
        .container { max-width: 600px; margin: auto; background-color: #2a2a44; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.4); }
        h2 { text-align: center; color: #9c27b0; }
        label { display: block; margin-top: 10px; color: #b39ddb; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; background-color: #3a3a5a; color: #e0e0e0; border: 1px solid #4a4a6a; }
        button { background-color: #4a148c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        button:hover { background-color: #7b1fa2; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #b39ddb; text-decoration: none; }
        .back-link:hover { color: #ffffff; }
    </style>
</head>
<body>
    <div class="container">
        <h2>แก้ไขข้อมูลแมว</h2>
        <?php if ($cat): ?>
        <form action="edit_cat.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($cat['id']); ?>">
            
            <label for="name">ชื่อแมว:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" required>

            <label for="color">สี:</label>
            <input type="text" id="color" name="color" value="<?php echo htmlspecialchars($cat['color']); ?>">

            <label for="age">อายุ:</label>
            <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($cat['age']); ?>">

            <label for="gender">เพศ:</label>
            <select id="gender" name="gender" required>
                <option value="male" <?php echo ($cat['gender'] == 'male') ? 'selected' : ''; ?>>ผู้</option>
                <option value="female" <?php echo ($cat['gender'] == 'female') ? 'selected' : ''; ?>>เมีย</option>
            </select>
            
            <label for="user_id">เจ้าของ:</label>
            <select id="user_id" name="user_id" required>
                <?php foreach ($owners as $owner): ?>
                    <option value="<?php echo $owner['id']; ?>" <?php echo ($cat['user_id'] == $owner['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($owner['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">บันทึกการแก้ไข</button>
        </form>
        <?php else: ?>
        <p style="text-align: center; color: red;">ไม่พบข้อมูลแมวที่ต้องการแก้ไข</p>
        <?php endif; ?>
        <a href="../admin_dashboard.php" class="back-link">ย้อนกลับหน้า admin mode</a>
    </div>
</body>
</html>