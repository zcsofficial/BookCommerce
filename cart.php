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
$tax = $subtotal * 0.08;
$total = $subtotal + $shipping + $tax;

// Proceed to checkout logic
if (isset($_POST['proceed_to_checkout'])) {
    // Perform checkout processing (e.g., create order, redirect to payment gateway)
    header("Location: checkout.php");  // Redirect to checkout page
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Bookstore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>:where([class^="ri-"])::before { content: "\f3c2"; }</style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#F59E0B'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Navigation Bar -->
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
                        <a href="logout.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                            Logout
                        </a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Login</a>
                    <a href="register.php" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold text-gray-900">Shopping Cart</h1>
        <a href="index.php" class="text-primary hover:text-primary/80 flex items-center gap-2">
            <i class="ri-arrow-left-line w-5 h-5 flex items-center justify-center"></i>
            <span>Continue Shopping</span>
        </a>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        <div class="flex-1">
            <div id="cartItems" class="space-y-4">
                <?php if (empty($cart_items)): ?>
                    <div id="emptyCart" class="text-center py-16">
                        <div class="w-16 h-16 mx-auto mb-4 text-gray-400 flex items-center justify-center">
                            <i class="ri-shopping-cart-line ri-2x"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Your cart is empty</h3>
                        <p class="text-gray-500 mb-6">Looks like you haven't added anything to your cart yet</p>
                        <a href="index.php" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-button text-white bg-primary hover:bg-primary/90">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex gap-6 p-4 bg-white rounded shadow-sm">
                            <img src="<?= $item['image_url']; ?>" alt="<?= $item['title']; ?>" class="w-24 h-36 object-cover rounded">
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <div>
                                        <h3 class="font-medium text-gray-900"><?= $item['title']; ?></h3>
                                        <p class="text-gray-500 text-sm"><?= $item['author']; ?></p>
                                    </div>
                                    <a href="?remove_id=<?= $item['id']; ?>" class="text-gray-400 hover:text-gray-500">
                                        <i class="ri-delete-bin-line w-5 h-5 flex items-center justify-center"></i>
                                    </a>
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <a href="?update_id=<?= $item['id']; ?>&quantity=<?= max(1, $item['quantity'] - 1); ?>" class="w-8 h-8 flex items-center justify-center border rounded-button hover:bg-gray-50">
                                            <i class="ri-subtract-line"></i>
                                        </a>
                                        <span class="w-8 text-center"><?= $item['quantity']; ?></span>
                                        <a href="?update_id=<?= $item['id']; ?>&quantity=<?= $item['quantity'] + 1; ?>" class="w-8 h-8 flex items-center justify-center border rounded-button hover:bg-gray-50">
                                            <i class="ri-add-line"></i>
                                        </a>
                                    </div>
                                    <span class="font-medium text-gray-900"><?= number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="w-full lg:w-96">
            <div class="bg-white rounded shadow-sm p-6 sticky top-4">
                <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                <div class="space-y-4 mb-6">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium">$<?= number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping</span>
                        <span class="font-medium">$<?= number_format($shipping, 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax</span>
                        <span class="font-medium">$<?= number_format($tax, 2); ?></span>
                    </div>
                    <div class="border-t pt-4">
                        <div class="flex justify-between">
                            <span class="font-semibold">Total</span>
                            <span class="font-semibold">$<?= number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <button type="submit" name="proceed_to_checkout" class="w-full bg-primary text-white py-3 rounded-button hover:bg-primary/90 font-medium">
                        Proceed to Checkout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
