<?php
// Start the session and include the database connection file
session_start();
include 'db_connect.php';

// Check if the user is logged in and has the 'user' role
// If not, redirect to the login page
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

// Define variables for alert messages
$error = '';
$success = '';

// Fetch user ID and related data
$stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// Fetch unpaid reservations for the current user to display in the payment form
// We also fetch the total_cost here to automatically populate the amount field later
$stmt = $conn->prepare("SELECT r.id, r.total_cost, r.date_from, r.date_to, c.name as cat_name FROM reservations r JOIN cats c ON r.cat_id = c.id WHERE r.customer_id = ? AND r.paid = 0 ORDER BY r.date_from DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unpaid_reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle new payment submission
if (isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $reservation_id = (int)$_POST['reservation_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];

    if ($reservation_id == 0) {
        $error = "กรุณาเลือกการจองที่ต้องการชำระเงิน";
    } else {
        // Start a transaction to ensure both updates succeed or fail together
        $conn->begin_transaction();

        try {
            // Update the 'paid' status in the reservations table
            $stmt = $conn->prepare("UPDATE reservations SET paid = 1 WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $reservation_id, $user_id);
            $stmt->execute();
            $stmt->close();

            // Insert a new record into the payments table
            $stmt = $conn->prepare("INSERT INTO payments (customer_id, reservation_id, amount, payment_method) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iids", $user_id, $reservation_id, $amount, $payment_method);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = "ชำระเงินเรียบร้อยแล้ว!";
            // Redirect to prevent form resubmission on refresh
            header("Location: payments.php?success=1");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = "เกิดข้อผิดพลาดในการชำระเงิน: " . $e->getMessage();
        }
    }
}

// Fetch all payment records for the user
$stmt = $conn->prepare("SELECT p.*, r.date_from, r.date_to, c.name as cat_name FROM payments p JOIN reservations r ON p.reservation_id = r.id JOIN cats c ON r.cat_id = c.id WHERE p.customer_id = ? ORDER BY p.payment_date DESC");
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
            <div class="text-xl font-semibold mb-8 text-center">User Dashboard</div>
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
                        <a href="payments.php" class="block w-full text-left py-2 px-4 rounded-md bg-gray-700 transition-colors duration-200">
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
            <h2 class="text-3xl font-bold mb-6 text-gray-800">การชำระเงินของฉัน</h2>

            <!-- Display alert messages -->
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">ชำระเงินเรียบร้อยแล้ว!</span>
                </div>
            <?php endif; ?>

            <!-- New Payment Form -->
            <div id="add-payment-form" class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">ชำระเงินสำหรับการจอง</h3>
                <?php if (count($unpaid_reservations) > 0): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_payment">

                        <!-- Dropdown to select a reservation -->
                        <div>
                            <label for="reservation_id" class="block text-sm font-medium text-gray-700">เลือกการจอง:</label>
                            <select id="reservation_id" name="reservation_id" required onchange="updateAmount()"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="0" data-cost="0">--- เลือกการจองที่ยังไม่ได้ชำระ ---</option>
                                <?php foreach($unpaid_reservations as $res): ?>
                                    <option value="<?php echo $res['id']; ?>" data-cost="<?php echo htmlspecialchars($res['total_cost']); ?>">
                                        <?php echo "แมว: " . htmlspecialchars($res['cat_name']) . " | วันที่: " . htmlspecialchars($res['date_from']) . " ถึง " . htmlspecialchars($res['date_to']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Payment Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">จำนวนเงิน:</label>
                            <input type="number" step="0.01" id="amount" name="amount" required readonly
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50"
                                   placeholder="เช่น 500.00">
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">ช่องทางการชำระเงิน:</label>
                            <select id="payment_method" name="payment_method" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="bank_transfer">โอนเงินผ่านธนาคาร</option>
                                <option value="credit_card">บัตรเครดิต</option>
                                <option value="cash">เงินสด</option>
                            </select>
                        </div>

                        <button type="submit"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                            ยืนยันการชำระเงิน
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-gray-500">ไม่มีการจองที่ต้องชำระเงิน</p>
                <?php endif; ?>
            </div>

            <!-- Current Payment Records -->
            <div id="payment-records" class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-2xl font-semibold mb-4 text-gray-700">ประวัติการชำระเงิน</h3>
                
                <?php if (count($payments) > 0): ?>
                    <div class="space-y-4">
                    <?php foreach($payments as $payment): ?>
                        <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                            <h5 class="text-lg font-semibold text-indigo-800 mb-2">การจอง: <?php echo htmlspecialchars($payment['cat_name']); ?></h5>
                            <ul class="text-indigo-700 space-y-1">
                                <li><span class="font-medium">จำนวนเงิน:</span> <?php echo number_format($payment['amount'], 2); ?> บาท</li>
                                <li><span class="font-medium">วันที่ชำระ:</span> <?php echo htmlspecialchars($payment['payment_date']); ?></li>
                                <li><span class="font-medium">วิธีการชำระ:</span> <?php echo htmlspecialchars($payment['payment_method']); ?></li>
                                <li><span class="font-medium">สำหรับวันที่:</span> <?php echo htmlspecialchars($payment['date_from']); ?> ถึง <?php echo htmlspecialchars($payment['date_to']); ?></li>
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
            // Get the selected option from the dropdown
            const selectElement = document.getElementById('reservation_id');
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            
            // Get the total cost from the data-cost attribute
            const totalCost = selectedOption.getAttribute('data-cost');
            
            // Find the amount input field
            const amountInput = document.getElementById('amount');

            // Update the value of the amount input
            amountInput.value = totalCost;
        }

        // Call the function on page load to set the initial amount if a reservation is pre-selected
        window.onload = updateAmount;
    </script>
</body>
</html>
