<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

// Ensure cart is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$pdo = getLicenseDbConnection();
$customer_id = $_SESSION['customer_id'];
$cart_items = $_SESSION['cart'];
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

$payment_status = '';
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // --- Simulate Payment Processing ---
    // In a real application, this is where you would integrate with a payment gateway (e.g., Stripe, PayPal).
    // For this example, we'll simulate a successful payment.
    $payment_successful = true; // Assume payment is always successful for now

    if ($payment_successful) {
        try {
            $pdo->beginTransaction();

            // 1. Create the Order
            $stmt = $pdo->prepare("INSERT INTO `orders` (customer_id, total_amount, status) VALUES (?, ?, 'completed')");
            $stmt->execute([$customer_id, $total_amount]);
            $order_id = $pdo->lastInsertId();

            // 2. Add Order Items and Generate Licenses
            foreach ($cart_items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];

                // Fetch product details to get max_devices and duration
                $stmt = $pdo->prepare("SELECT max_devices, license_duration_days FROM `products` WHERE id = ?");
                $stmt->execute([$product_id]);
                $product_details = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product_details) {
                    throw new Exception("Product details not found for ID: " . $product_id);
                }

                $max_devices = $product_details['max_devices'];
                $license_duration_days = $product_details['license_duration_days'];

                // Generate a unique license key
                $license_key = generateLicenseKey();
                $expires_at = date('Y-m-d H:i:s', strtotime("+$license_duration_days days"));

                // Insert into licenses table
                $stmt = $pdo->prepare("INSERT INTO `licenses` (customer_id, product_id, license_key, status, max_devices, expires_at) VALUES (?, ?, ?, 'active', ?, ?)");
                $stmt->execute([$customer_id, $product_id, $license_key, $max_devices, $expires_at]);

                // Add to order_items, linking the generated license key
                $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, product_id, quantity, price, license_key_generated) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $product_id, $quantity, $price, $license_key]);
            }

            $pdo->commit();
            $_SESSION['cart'] = []; // Clear cart after successful order
            $payment_status = 'success';
            header('Location: dashboard.php?order_success=' . $order_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Payment processing error: " . $e->getMessage());
            $payment_status = 'error';
        }
    } else {
        $payment_status = 'failed';
    }
}

portal_header("Checkout - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-gray-900 mb-8 text-center">Checkout</h1>

<?php if ($payment_status === 'error'): ?>
    <div class="alert-error mb-4">
        Payment failed due to an internal error. Please try again or contact support.
    </div>
<?php elseif ($payment_status === 'failed'): ?>
    <div class="alert-error mb-4">
        Your payment could not be processed. Please check your details and try again.
    </div>
<?php endif; ?>

<div class="max-w-2xl mx-auto card">
    <h2 class="text-2xl font-semibold mb-4">Order Details</h2>
    <div class="space-y-3 mb-6">
        <?php foreach ($cart_items as $item): ?>
            <div class="flex justify-between items-center border-b pb-2">
                <span class="text-lg"><?= htmlspecialchars($item['name']) ?></span>
                <span class="font-bold">$<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-between text-xl font-bold mb-6 border-t pt-4">
        <span>Total Amount:</span>
        <span>$<?= htmlspecialchars(number_format($total_amount, 2)) ?></span>
    </div>

    <h2 class="text-2xl font-semibold mb-4">Payment Information</h2>
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-6">
        <p class="font-bold">Payment Gateway Placeholder</p>
        <p class="text-sm">In a real application, you would integrate with a payment provider like Stripe or PayPal here.</p>
        <p class="text-sm">For this demo, clicking "Confirm Payment" will simulate a successful transaction.</p>
    </div>

    <form action="payment.php" method="POST">
        <button type="submit" name="confirm_payment" class="btn-primary w-full">
            <i class="fas fa-credit-card mr-2"></i>Confirm Payment
        </button>
    </form>
</div>

<?php portal_footer(); ?>