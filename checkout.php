<?php
// Include database connection and necessary functions
include 'config.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get cart items from the database
$sql = "SELECT b.id, b.title, b.author, b.price, c.quantity, b.image_url
        FROM cart c
        JOIN books b ON c.book_id = b.id
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 4.99; // Example shipping cost
$tax = 0.10 * $subtotal; // Example tax rate
$total = $subtotal + $shipping + $tax;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the checkout form data
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $zipcode = $_POST['zipcode'];
    $payment_method = $_POST['payment'];

    // Insert the order into the database
    $order_sql = "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("id", $user_id, $total);
    $order_stmt->execute();
    $order_id = $order_stmt->insert_id;

    // Insert order shipping information
    $country = 'USA'; // Defining the country as a variable
    $shipping_sql = "INSERT INTO order_shipping_address (order_id, address_line1, city, state, postal_code, country, phone) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    $shipping_stmt = $conn->prepare($shipping_sql);
    $shipping_stmt->bind_param("issssss", $order_id, $address, $city, $state, $zipcode, $country, $phone);
    $shipping_stmt->execute();

    // Insert the order items into the database
    foreach ($cart_items as $item) {
        $order_item_sql = "INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)";
        $order_item_stmt = $conn->prepare($order_item_sql);
        $order_item_stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
        $order_item_stmt->execute();
    }

    // Clear the cart after checkout
    $clear_cart_sql = "DELETE FROM cart WHERE user_id = ?";
    $clear_cart_stmt = $conn->prepare($clear_cart_sql);
    $clear_cart_stmt->bind_param("i", $user_id);
    $clear_cart_stmt->execute();

    // Redirect to order confirmation page with order ID
    header('Location: order_confirmed.php?order_id=' . $order_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BookCommerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#10B981'
                    },
                    borderRadius: {
                        'none': '0px',
                        'sm': '4px',
                        DEFAULT: '8px',
                        'md': '12px',
                        'lg': '16px',
                        'xl': '20px',
                        '2xl': '24px',
                        '3xl': '32px',
                        'full': '9999px',
                        'button': '8px'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="font-['Pacifico'] text-2xl">BookCommerce</h1>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="fullname" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="tel" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <input type="text" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input type="text" name="city" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <input type="text" name="state" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                    <input type="text" name="zipcode" class="w-full px-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
            </div>

            <h3 class="font-medium mb-4">Payment Method</h3>
            <label class="flex items-center">
                <input type="radio" name="payment" value="credit" class="payment-radio mr-2" required>
                <span class="text-sm">Credit Card</span>
            </label>
            <label class="flex items-center">
                <input type="radio" name="payment" value="paypal" class="payment-radio mr-2">
                <span class="text-sm">PayPal</span>
            </label>

            <button type="submit" class="w-full py-3 mt-6 bg-primary text-white rounded-button">Complete Checkout</button>
        </form>
    </div>
</body>
</html>
