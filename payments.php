<?php
// Start the session and include the database connection file
session_start();
include 'db_connect.php';

// Check if the user is logged in and has the 'user' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// Define variables for alert messages
$error = '';
$success = '';

// Fetch user ID and related data
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = isset($user['id']) ? (int)$user['id'] : 0;
$stmt->close();

if (!$user_id) {
    die("ไม่พบผู้ใช้");
}

// Handle new payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    // Basic inputs
    $reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';

    // Require a valid reservation
    if (!$reservation_id) {
        $error = "กรุณาเลือกการจองที่ต้องชำระเงิน";
    } elseif ($amount <= 0) {
        $error = "จำนวนเงินไม่ถูกต้อง";
    } elseif (!in_array($payment_method, ['bank_transfer', 'qrcode'])) {
        $error = "วิธีชำระเงินไม่ถูกต้อง";
    } elseif (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
        $error = "กรุณาอัปโหลดสลิปการโอนเงิน";
    }

    // Validate the reservation belongs to this user, is confirmed, and is unpaid
    if (!$error) {
        $stmt = $conn->prepare("SELECT total_cost FROM reservations WHERE id = ? AND customer_id = ? AND paid = 0 AND status = 'confirmed'");
        $stmt->bind_param("ii", $reservation_id, $user_id);
        $stmt->execute();
        $res_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res_check) {
            $error = "ไม่พบการจองที่เลือก, หรือสถานะยังไม่ได้รับการยืนยัน, หรือชำระแล้ว";
        } else {
            $expected = (float)$res_check['total_cost'];
            if (abs($expected - $amount) > 0.01) {
                $error = "จำนวนเงินไม่ตรงกับยอดที่ต้องชำระ";
            }
        }
    }

    // Validate and move uploaded slip
    $saved_path = '';
    if (!$error) {
        $uploadDir = __DIR__ . "/uploads/";
        $publicDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['slip']['tmp_name']);
        $allowed_mime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed_mime[$mime])) {
            $error = "อนุญาตเฉพาะไฟล์รูปภาพ JPG/PNG/WebP เท่านั้น";
        }

        if (!$error) {
            $maxSize = 5 * 1024 * 1024;
            if ($_FILES['slip']['size'] > $maxSize) {
                $error = "ขนาดไฟล์ใหญ่เกินกำหนด (สูงสุด 5MB)";
            }
        }

        if (!$error) {
            $ext = $allowed_mime[$mime];
            $safeName = "slip_" . $user_id . "_" . $reservation_id . "_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
            $dest = $uploadDir . $safeName;

            if (!move_uploaded_file($_FILES['slip']['tmp_name'], $dest)) {
                $error = "อัปโหลดไฟล์ล้มเหลว";
            } else {
                $saved_path = $publicDir . $safeName;
            }
        }
    }

    // DB changes with transaction
    if (!$error) {
        $conn->begin_transaction();
        try {
            // Mark reservation as paid
            $stmt = $conn->prepare("UPDATE reservations SET paid = 1 WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $reservation_id, $user_id);
            $stmt->execute();
            $stmt->close();

            // Insert payment with slip_path
            $stmt = $conn->prepare("INSERT INTO payments (customer_id, reservation_id, amount, payment_method, slip_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidss", $user_id, $reservation_id, $amount, $payment_method, $saved_path);
            $stmt->execute();
            $stmt->close();

            // **NEW: Fetch the checkin_code after successful payment**
            $stmt = $conn->prepare("SELECT checkin_code FROM reservations WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $reservation = $result->fetch_assoc();
            $checkin_code = $reservation['checkin_code'];
            $stmt->close();

            $conn->commit();
            
            // Set success message with the checkin code
            $_SESSION['payment_success'] = "ชำระเงินเรียบร้อยแล้ว! 🥳 รหัสเช็คอินของคุณคือ: <span class='font-bold text-green-700'>" . htmlspecialchars($checkin_code) . "</span>";
            
            header("Location: payments.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ($saved_path && file_exists(__DIR__ . '/' . $saved_path)) {
                @unlink(__DIR__ . '/' . $saved_path);
            }
            $error = "เกิดข้อผิดพลาดในการชำระเงิน: " . $e->getMessage();
        }
    }
}

// Check for success message in session after redirect
if (isset($_SESSION['payment_success'])) {
    $success = $_SESSION['payment_success'];
    unset($_SESSION['payment_success']); // Clear the message after displaying
}

// Fetch confirmed and unpaid reservations for the current user
$stmt = $conn->prepare("
    SELECT r.id, r.total_cost, r.date_from, r.date_to, c.name as cat_name
    FROM reservations r
    JOIN cats c ON r.cat_id = c.id
    WHERE r.customer_id = ? AND r.paid = 0 AND r.status = 'confirmed'
    ORDER BY r.date_from DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unpaid_reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all payment records for the user
$stmt = $conn->prepare("
    SELECT p.*, r.date_from, r.date_to, c.name as cat_name
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    JOIN cats c ON r.cat_id = c.id
    WHERE p.customer_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex min-h-screen">
    <aside class="w-64 bg-gray-800 text-white p-6 shadow-lg">
        <div class="text-xl font-semibold mb-8 text-center">CAT_HOTEL</div>
        <nav>
            <ul>
                <li class="mb-2"><a href="user_index.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">หน้าหลัก</a></li>
                <li class="mb-2"><a href="my_cats.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">จัดการข้อมูลเเมว</a></li>
                <li class="mb-2"><a href="reservations.php" class="block w-full text-left py-2 px-4 rounded-md hover:bg-gray-700 transition-colors duration-200">การจอง</a></li>
                <li class="mb-2"><a href="user_reservations.php" class="block w-full py-2 px-4 rounded-md hover:bg-gray-700">เช็คอินเข้าพัก</a></li>
                <li class="mb-2"><a href="payments.php" class="block w-full text-left py-2 px-4 rounded-md bg-gray-700 transition-colors duration-200">ชำระเงิน</a></li>
                <li class="mt-8"><a href="logout.php" class="block py-2 px-4 rounded-md bg-red-600 hover:bg-red-700 transition-colors duration-200 text-center">ออกจากระบบ</a></li>
            </ul>
        </nav>
    </aside>

    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">การชำระเงินของฉัน</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <div id="add-payment-form" class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-2xl font-semibold mb-4 text-gray-700">ชำระเงินสำหรับการจอง</h3>
            
            <?php if (count($unpaid_reservations) > 0): ?>
                <form method="POST" class="space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_payment">

                    <div>
                        <label for="reservation_id" class="block text-sm font-medium text-gray-700">เลือกการจอง:</label>
                        <select id="reservation_id" name="reservation_id" required onchange="updateAmount()"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="0" data-cost="0">--- เลือกการจองที่ยังไม่ได้ชำระ ---</option>
                            <?php foreach($unpaid_reservations as $res): ?>
                                <option value="<?php echo (int)$res['id']; ?>" data-cost="<?php echo htmlspecialchars($res['total_cost']); ?>">
                                    <?php
                                        echo "แมว: " . htmlspecialchars($res['cat_name']) .
                                             " | วันที่: " . htmlspecialchars($res['date_from']) .
                                             " ถึง " . htmlspecialchars($res['date_to']) .
                                             " (ราคา: " . number_format($res['total_cost'], 2) . " บาท)";
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">จำนวนเงินที่ต้องชำระ:</label>
                        <input type="number" step="0.01" id="amount" name="amount" required readonly
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50"
                                placeholder="เช่น 500.00">
                    </div>

                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700">ช่องทางการชำระเงิน:</label>
                        <select id="payment_method" name="payment_method" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">เลือกการชำระ</option>
                            <option value="bank_transfer">โอนเงินผ่านธนาคาร</option>
                            <option value="qrcode">สแกน QR Code</option>
                        </select>
                    </div>

                    <div id="payment-details-container" class="mt-4 hidden">
                        <div id="bank-box" class="hidden text-center">
                            <p class="text-sm text-gray-600 mb-2">โอนเข้าบัญชีธนาคาร:</p>
                            <div class="bg-gray-100 p-4 rounded-lg shadow inline-block text-left">
                                <p><span class="font-semibold">ธนาคาร:</span> กสิกรไทย</p>
                                <p><span class="font-semibold">เลขที่บัญชี:</span> 123-4-56789-0</p>
                                <p><span class="font-semibold">ชื่อบัญชี:</span> นายทดสอบ ระบบ</p>
                            </div>
                        </div>

                        <div id="qrcode-box" class="hidden">
                            <p class="text-sm text-gray-600 mt-2 mb-2 text-center">สแกน QR Code เพื่อชำระเงิน:</p>
                            <div class="flex justify-center mb-4">
                                <img src="images/qrcode.png" alt="QR Code" class="w-56 h-56 border rounded-lg shadow">
                            </div>
                        </div>

                        <div id="slip-box" class="mt-4">
                            <label for="slip" class="block text-sm font-medium text-gray-700">อัปโหลดสลิปการโอน ≤ 5MB:</label>
                            <input type="file" id="slip" name="slip" accept="image/*" required
                                    class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md shadow-sm cursor-pointer focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <div id="slip-preview" class="mt-3 hidden text-center">
                                <p class="text-sm text-gray-600 mb-2">ตัวอย่างสลิป:</p>
                                <img id="slip-preview-img" src="#" alt="preview" class="mx-auto w-48 h-auto rounded shadow" />
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        ยืนยันการชำระเงิน
                    </button>
                </form>
            <?php else: ?>
                <p class="text-gray-500">ไม่มีการจองที่ได้รับการยืนยันและยังไม่ได้ชำระเงิน</p>
                <p class="text-gray-500">โปรดไปที่หน้า <a href="reservations.php" class="text-indigo-600 hover:underline">การจอง</a> เพื่อตรวจสอบสถานะ</p>
            <?php endif; ?>
        </div>

        <div id="payment-records" class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-2xl font-semibold mb-4 text-gray-700">ประวัติการชำระเงิน</h3>
            <?php if (count($payments) > 0): ?>
                <div class="space-y-4">
                    <?php foreach($payments as $payment): ?>
                        <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                            <h5 class="text-lg font-semibold text-indigo-800 mb-2">
                                การจอง: <?php echo htmlspecialchars($payment['cat_name']); ?>
                            </h5>
                            <ul class="text-indigo-700 space-y-1">
                                <li><span class="font-medium">จำนวนเงิน:</span> <?php echo number_format((float)$payment['amount'], 2); ?> บาท</li>
                                <li><span class="font-medium">วันที่ชำระ:</span> <?php echo htmlspecialchars($payment['payment_date']); ?></li>
                                <li><span class="font-medium">วิธีการชำระ:</span> <?php echo htmlspecialchars($payment['payment_method']); ?></li>
                                <li><span class="font-medium">สำหรับวันที่:</span> <?php echo htmlspecialchars($payment['date_from']); ?> ถึง <?php echo htmlspecialchars($payment['date_to']); ?></li>
                                <?php if (!empty($payment['slip_path'])): ?>
                                    <li>
                                        <span class="font-medium">สลิป:</span>
                                        <a href="<?php echo htmlspecialchars($payment['slip_path']); ?>" target="_blank" class="text-blue-600 underline">ดูสลิป</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500">คุณยังไม่มีประวัติการชำระเงิน</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function updateAmount() {
        const selectElement = document.getElementById('reservation_id');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const totalCost = selectedOption.getAttribute('data-cost');
        document.getElementById('amount').value = totalCost || 0;
    }

    const paymentSelect = document.getElementById('payment_method');
    const paymentDetailsContainer = document.getElementById('payment-details-container');
    const bankBox = document.getElementById('bank-box');
    const qrBox = document.getElementById('qrcode-box');
    const slipBox = document.getElementById('slip-box');
    const slipInput = document.getElementById('slip');
    const slipPreview = document.getElementById('slip-preview');
    const slipPreviewImg = document.getElementById('slip-preview-img');

    function togglePaymentBoxes() {
        const val = paymentSelect.value;
        if (val === 'bank_transfer' || val === 'qrcode') {
            paymentDetailsContainer.classList.remove('hidden');
            slipBox.classList.remove('hidden');
            slipInput.required = true;
            if (val === 'bank_transfer') {
                bankBox.classList.remove('hidden');
                qrBox.classList.add('hidden');
            } else {
                bankBox.classList.add('hidden');
                qrBox.classList.remove('hidden');
            }
        } else {
            paymentDetailsContainer.classList.add('hidden');
            bankBox.classList.add('hidden');
            qrBox.classList.add('hidden');
            slipBox.classList.add('hidden');
            slipInput.required = false;
        }
    }

    if (paymentSelect) {
        paymentSelect.addEventListener('change', togglePaymentBoxes);
    }

    if (slipInput) {
        slipInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                slipPreview.classList.add('hidden');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(ev) {
                slipPreviewImg.src = ev.target.result;
                slipPreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    }

    window.addEventListener('load', function () {
        updateAmount();
        togglePaymentBoxes();
    });
</script>
</body>
</html>