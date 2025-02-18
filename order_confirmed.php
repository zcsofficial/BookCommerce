<?php
// Include necessary files
include 'config.php'; // Database connection file
session_start();
// Fetch user details from the database
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT fullname FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_full_name = htmlspecialchars($user['fullname']);

    // Fetch cart count for logged-in user
    $cart_query = "SELECT COUNT(*) AS cart_count FROM cart WHERE user_id = ?";
    $cart_stmt = $conn->prepare($cart_query);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart_data = $cart_result->fetch_assoc();
    $cart_count = $cart_data['cart_count'];
}

// Get order_id from URL query parameters
if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id']; // Get the order_id from the URL query parameter
} else {
    // Redirect if no order_id is provided
    header("Location: index.php");
    exit();
}

// Fetch order details from the database
$order_query = "SELECT * FROM orders WHERE id = '$order_id'";
$order_result = mysqli_query($conn, $order_query);

// Check if order exists
if (mysqli_num_rows($order_result) == 0) {
    die("Order not found.");
}
$order = mysqli_fetch_assoc($order_result);

// Fetch items in the order
$items_query = "SELECT oi.*, b.title, b.author, b.image_url 
                FROM order_items oi
                JOIN books b ON oi.book_id = b.id
                WHERE oi.order_id = '$order_id'";
$items_result = mysqli_query($conn, $items_query);

// Fetch shipping info from the order_shipping_address table
$shipping_query = "SELECT * FROM order_shipping_address WHERE order_id = '$order_id'";
$shipping_result = mysqli_query($conn, $shipping_query);
$shipping = mysqli_fetch_assoc($shipping_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#34D399'
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
    <!-- Navbar -->
    <nav class="bg-white shadow-sm fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="font-['Pacifico'] text-2xl text-primary">BookCommerce</a>
                    <div class="hidden md:flex space-x-8 ml-10">
                        <a href="index.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Home</a>
                        <a href="books.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Books</a>
                        <a href="cart.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Cart</a>
                        <a href="#" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Requests</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isset($user_full_name)): ?>
                        <div class="flex items-center space-x-2">
                            <span class="text-gray-900 font-medium"><?php echo $user_full_name; ?></span>
                            <a href="account.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                <i class="ri-user-line text-xl"></i>
                            </a>
                            <a href="?logout=true" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                Logout
                            </a>
                        </div>

                        <!-- Cart Icon (only visible if user is logged in) -->
                        <div class="relative">
                            <button id="cartBtn" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                <i class="ri-shopping-cart-line text-xl"></i>
                                <span class="absolute top-0 right-0 rounded-full bg-primary text-white text-xs px-2 py-1">
                                    <?php echo $cart_count ?? 0; ?>
                                </span>
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Login</a>
                        <a href="register.php" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Order Confirmation Section -->
    <div class="max-w-4xl mx-auto p-6 space-y-8">
        <header class="text-center space-y-4">
            <div class="w-16 h-16 mx-auto bg-secondary rounded-full flex items-center justify-center">
                <i class="ri-check-line text-white ri-2x"></i>
            </div>
            <h1 class="text-3xl font-semibold text-gray-900">Order Confirmed!</h1>
            <p class="text-gray-600">Order #<?php echo $order['id']; ?></p>
            <p class="text-sm text-gray-500">A confirmation email has been sent to your email address.</p>
        </header>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div class="space-y-4">
                <h2 class="text-xl font-semibold text-gray-900">Order Summary</h2>
                <div class="divide-y divide-gray-200">
                    <?php while($item = mysqli_fetch_assoc($items_result)): ?>
                    <div class="flex py-4 space-x-4">
                        <img src="<?php echo $item['image_url']; ?>" alt="Book cover" class="w-20 h-30 object-cover rounded">
                        <div class="flex-1">
                            <h3 class="font-medium text-gray-900"><?php echo $item['title']; ?></h3>
                            <p class="text-sm text-gray-500">by <?php echo $item['author']; ?></p>
                            <div class="mt-1 flex justify-between text-sm">
                                <p class="text-gray-500">Qty: <?php echo $item['quantity']; ?></p>
                                <p class="font-medium text-gray-900">$<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="border-t border-gray-200 pt-4">
                <dl class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Subtotal</dt>
                        <dd class="font-medium text-gray-900">$<?php echo number_format($order['total_amount'], 2); ?></dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Shipping</dt>
                        <dd class="font-medium text-gray-900">$<?php echo number_format($order['shipping'], 2); ?></dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500">Tax</dt>
                        <dd class="font-medium text-gray-900">$<?php echo number_format($order['tax'], 2); ?></dd>
                    </div>
                    <div class="flex justify-between text-base font-medium pt-2 border-t">
                        <dt class="text-gray-900">Total</dt>
                        <dd class="text-primary">$<?php echo number_format($order['total_amount'], 2); ?></dd>
                    </div>
                </dl>
            </div>
            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-2">
                    <h3 class="font-medium text-gray-900">Shipping Information</h3>
                    <div class="text-sm text-gray-500">
                        <p><?php echo $shipping['address_line1']; ?></p>
                        <p><?php echo $shipping['address_line2']; ?></p>
                        <p><?php echo $shipping['city'] . ', ' . $shipping['state'] . ' ' . $shipping['postal_code']; ?></p>
                        <p><?php echo $shipping['country']; ?></p>
                    </div>
                    <p class="text-sm text-gray-500">Estimated delivery: <?php echo $shipping['delivery_date']; ?></p>
                </div>
            </div>
        </div>

        <div class="text-center text-sm text-gray-500">
            <p>Need help? Contact our support team at support@bookcommerce.com</p>
            <p class="mt-1">Return policy: 30-day money-back guarantee</p>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="max-w-7xl mx-auto text-center">
            <p>&copy; 2025 BookCommerce. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
