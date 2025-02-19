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

// Fetch user details (e.g., full name)
$user_query = "SELECT fullname FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$user_full_name = ''; // Default value in case no user is found
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_full_name = htmlspecialchars($user['fullname']);
} else {
    // If no user is found, log out or handle error
    session_destroy();
    header('Location: login.php');
    exit();
}

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
$tax = 0.10 * $subtotal; // 10% tax
$total = $subtotal + $shipping + $tax;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the checkout form data
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zipcode = trim($_POST['zipcode']);
    $payment_method = trim($_POST['payment']);

    // Validate inputs (basic validation)
    if (empty($fullname) || empty($email) || empty($phone) || empty($address) || empty($city) || empty($state) || empty($zipcode) || empty($payment_method)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Insert the order into the database
        $order_sql = "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')";
        $order_stmt = $conn->prepare($order_sql);
        $order_stmt->bind_param("id", $user_id, $total);
        if ($order_stmt->execute()) {
            $order_id = $order_stmt->insert_id;

            // Insert order shipping information
            $country = 'USA'; // Default country
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
        } else {
            $error = "An error occurred while processing your order. Please try again.";
        }
    }
}
// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
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
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        .rounded-button {
            border-radius: 8px;
        }
        .error {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
    </style>
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
<body class="bg-gray-50 min-h-screen font-sans">

    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo and Hamburger Menu for Mobile -->
                <div class="flex items-center">
                    <button id="mobileMenuButton" class="md:hidden p-2 text-gray-900 hover:text-primary">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                    <a href="index.php" class="font-['Pacifico'] text-2xl text-primary ml-2 md:ml-0">BookCommerce</a>
                </div>

                <!-- Navigation Links (Hidden on Mobile, Shown on Medium and Up) -->
                <div class="hidden md:flex md:items-center md:space-x-8">
                    <a href="index.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Home</a>
                    <a href="books.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Books</a>
                    <a href="cart.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Cart</a>
                    <a href="#" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Requests</a>
                </div>

                <!-- User Actions and Cart -->
                <div class="flex items-center space-x-4">
                    <?php if (isset($user_full_name)): ?>
                        <div class="flex items-center space-x-2">
                            <span class="text-gray-900 font-medium hidden md:inline"><?php echo $user_full_name; ?></span>
                            <a href="account.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                <i class="ri-user-line text-xl"></i>
                            </a>
                            <a href="?logout=true" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">
                                Logout
                            </a>
                        </div>

                        <!-- Cart Icon -->
                        <div class="relative">
                            <button id="cartBtn" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                <i class="ri-shopping-cart-line text-xl"></i>
                                <span class="absolute -top-1 -right-2 rounded-full bg-primary text-white text-xs px-2 py-1">
                                    <?php echo $cart_count ?? 0; ?>
                                </span>
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Login</a>
                        <a href="register.php" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md">Register</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile Menu (Hidden by default, shown when hamburger is clicked) -->
            <div id="mobileMenu" class="md:hidden hidden bg-white shadow-md absolute w-full top-16 left-0">
                <a href="index.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Home</a>
                <a href="books.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Books</a>
                <a href="cart.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Cart</a>
                <a href="#" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Requests</a>
                <?php if (isset($user_full_name)): ?>
                    <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">
                        <i class="ri-user-line mr-2"></i> Account
                    </a>
                    <a href="?logout=true" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Login</a>
                    <a href="register.php" class="block bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md mx-4 my-2">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-16 max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Checkout</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zipcode" value="<?php echo isset($_POST['zipcode']) ? htmlspecialchars($_POST['zipcode']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-medium text-gray-800 mb-4">Payment Method</h3>
                    <label class="flex items-center mb-2">
                        <input type="radio" name="payment" value="credit" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary" <?php echo !isset($_POST['payment']) || $_POST['payment'] === 'credit' ? 'checked' : ''; ?> required>
                        <span class="ml-2 text-sm text-gray-700">Credit Card</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="payment" value="paypal" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary" <?php echo isset($_POST['payment']) && $_POST['payment'] === 'paypal' ? 'checked' : ''; ?>>
                        <span class="ml-2 text-sm text-gray-700">PayPal</span>
                    </label>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-4">Order Summary</h3>
                    <div class="space-y-3 text-gray-700">
                        <p class="flex justify-between"><span>Subtotal:</span> <span class="font-medium">$<?php echo number_format($subtotal, 2); ?></span></p>
                        <p class="flex justify-between"><span>Shipping:</span> <span class="font-medium">$<?php echo number_format($shipping, 2); ?></span></p>
                        <p class="flex justify-between"><span>Tax:</span> <span class="font-medium">$<?php echo number_format($tax, 2); ?></span></p>
                        <p class="flex justify-between font-semibold text-gray-800"><span>Total:</span> <span>$<?php echo number_format($total, 2); ?></span></p>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 mt-6 bg-primary text-white rounded-md hover:bg-primary/90 font-medium transition duration-200">Complete Checkout</button>
            </form>
        </div>
    </div>

    <script>
        // Toggle mobile menu
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        // Close mobile menu when a link or form is interacted with
        document.querySelectorAll('#mobileMenu a, #mobileMenu form').forEach(element => {
            element.addEventListener('click', function() {
                document.getElementById('mobileMenu').classList.add('hidden');
            });
            if (element.tagName === 'FORM') {
                element.addEventListener('submit', function() {
                    document.getElementById('mobileMenu').classList.add('hidden');
                });
            }
        });
    </script>

</body>
</html>