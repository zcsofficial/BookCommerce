<?php
include 'config.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details (e.g., full name)
$user_query = "SELECT fullname FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_full_name = $user['fullname'];

// Fetch cart items
$cart_query = "SELECT c.quantity, b.id, b.title, b.author, b.price, b.image_url, ca.name AS category_name
               FROM cart c
               JOIN books b ON c.book_id = b.id
               JOIN categories ca ON b.category_id = ca.id
               WHERE c.user_id = ?";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}

// Remove item from cart
if (isset($_GET['remove_id'])) {
    $remove_id = $_GET['remove_id'];
    $delete_query = "DELETE FROM cart WHERE user_id = ? AND book_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $user_id, $remove_id);
    $stmt->execute();
    header("Location: cart.php");
    exit();
}

// Update cart item quantity
if (isset($_GET['update_id']) && isset($_GET['quantity'])) {
    $update_id = $_GET['update_id'];
    $quantity = $_GET['quantity'];
    
    if ($quantity > 0) {
        $update_query = "UPDATE cart SET quantity = ? WHERE user_id = ? AND book_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iii", $quantity, $user_id, $update_id);
        $stmt->execute();
    } else {
        // If quantity is 0 or less, remove the item
        $delete_query = "DELETE FROM cart WHERE user_id = ? AND book_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("ii", $user_id, $update_id);
        $stmt->execute();
    }
    header("Location: cart.php");
    exit();
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 4.99;
$tax = $subtotal * 0.08; // 8% tax
$total = $subtotal + $shipping + $tax;

// Proceed to checkout logic
if (isset($_POST['proceed_to_checkout'])) {
    // Perform checkout processing (e.g., create order, redirect to payment gateway)
    header("Location: checkout.php");  // Redirect to checkout page
    exit();
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
    <title>Shopping Cart | BookCommerce</title>
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
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-semibold text-gray-800">Shopping Cart</h1>
            <a href="index.php" class="text-primary hover:text-primary/80 flex items-center gap-2">
                <i class="ri-arrow-left-line w-5 h-5 flex items-center justify-center"></i>
                <span class="text-sm font-medium">Continue Shopping</span>
            </a>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Cart Items -->
            <div class="flex-1">
                <div id="cartItems" class="space-y-4">
                    <?php if (empty($cart_items)): ?>
                        <div id="emptyCart" class="text-center py-16 bg-white rounded-lg shadow-md">
                            <div class="w-16 h-16 mx-auto mb-4 text-gray-400 flex items-center justify-center">
                                <i class="ri-shopping-cart-line text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Your cart is empty</h3>
                            <p class="text-gray-600 mb-6 text-sm">Looks like you haven't added anything to your cart yet</p>
                            <a href="index.php" class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-md text-base font-medium text-white bg-primary hover:bg-primary/90 transition duration-200">Start Shopping</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cart_items as $item): ?>
                            <div class="bg-white rounded-lg shadow-md p-4 flex gap-6 items-center">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-24 h-36 object-cover rounded-md">
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-gray-800 text-lg"><?php echo htmlspecialchars($item['title']); ?></h3>
                                            <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($item['author']); ?></p>
                                        </div>
                                        <a href="?remove_id=<?php echo $item['id']; ?>" class="text-gray-500 hover:text-red-600 transition duration-200">
                                            <i class="ri-delete-bin-line w-5 h-5 flex items-center justify-center"></i>
                                        </a>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <a href="?update_id=<?php echo $item['id']; ?>&quantity=<?php echo max(1, $item['quantity'] - 1); ?>" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                                                <i class="ri-subtract-line text-gray-600"></i>
                                            </a>
                                            <span class="w-8 text-center text-gray-800 font-medium"><?php echo $item['quantity']; ?></span>
                                            <a href="?update_id=<?php echo $item['id']; ?>&quantity=<?php echo $item['quantity'] + 1; ?>" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                                                <i class="ri-add-line text-gray-600"></i>
                                            </a>
                                        </div>
                                        <span class="font-medium text-gray-800 text-lg">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="w-full lg:w-96">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Summary</h2>
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-medium text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span class="font-medium text-gray-800">$<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Tax</span>
                            <span class="font-medium text-gray-800">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="border-t pt-4">
                            <div class="flex justify-between text-gray-800">
                                <span class="font-semibold">Total</span>
                                <span class="font-semibold text-lg">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <button type="submit" name="proceed_to_checkout" class="w-full bg-primary text-white py-3 rounded-md hover:bg-primary/90 font-medium transition duration-200">
                            Proceed to Checkout
                        </button>
                    </form>
                </div>
            </div>
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