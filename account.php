<?php
include 'config.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}


$user_id = $_SESSION['user_id'];
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

// Fetch user details
$user_query = "SELECT id, fullname, email, role, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$user_fullname = htmlspecialchars($user['fullname']);
$user_email = htmlspecialchars($user['email']);
$user_role = htmlspecialchars($user['role']);
$created_at = htmlspecialchars($user['created_at']);

// Fetch user's orders
$orders_query = "SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);

// Fetch user's books (both for sale and exchange)
$books_query = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, c.name AS category_name, b.for_exchange
                FROM books b
                JOIN categories c ON b.category_id = c.id
                WHERE b.user_id = ?";
$books_stmt = $conn->prepare($books_query);
$books_stmt->bind_param("i", $user_id);
$books_stmt->execute();
$books_result = $books_stmt->get_result();
$user_books = $books_result->fetch_all(MYSQLI_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_fullname = trim($_POST['fullname']);
    $new_email = trim($_POST['email']);
    
    if (!empty($new_fullname) && !empty($new_email) && filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $email_check_stmt = $conn->prepare($email_check_query);
        $email_check_stmt->bind_param("si", $new_email, $user_id);
        $email_check_stmt->execute();
        $email_result = $email_check_stmt->get_result();

        if ($email_result->num_rows > 0) {
            $error = "This email is already registered by another user.";
        } else {
            $update_query = "UPDATE users SET fullname = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssi", $new_fullname, $new_email, $user_id);
            if ($update_stmt->execute()) {
                $success = "Profile updated successfully.";
                $user_fullname = htmlspecialchars($new_fullname);
                $user_email = htmlspecialchars($new_email);
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    } else {
        $error = "Invalid input. Full name and email are required, and email must be valid.";
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
    <title>Account - BookCommerce</title>
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
    <div class="pt-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">My Account</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Profile Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Profile Information</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="update_profile" value="1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="fullname" value="<?php echo $user_fullname; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="<?php echo $user_email; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <input type="text" value="<?php echo $user_role; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Joined</label>
                        <input type="text" value="<?php echo date('F d, Y', strtotime($created_at)); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <button type="submit" class="w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Update Profile</button>
                </form>
            </div>

            <!-- Orders Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Order History</h2>
                <?php if (empty($orders)): ?>
                    <p class="text-gray-500">No orders found.</p>
                <?php else: ?>
                    <ul class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($orders as $order): ?>
                            <li class="p-4 bg-gray-50 rounded-md">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Order #<?php echo $order['id']; ?></span>
                                    <span class="text-sm font-medium text-gray-800">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="text-sm text-gray-600">Status: <?php echo htmlspecialchars($order['status']); ?></div>
                                <div class="text-sm text-gray-600">Date: <?php echo date('F d, Y', strtotime($order['created_at'])); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- User's Books Section -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">My Books</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($user_books as $book): ?>
                    <div class="group bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <div class="relative aspect-[3/4] rounded-t-lg overflow-hidden">
                            <img src="<?php echo htmlspecialchars($book['image_url'] ?: 'https://via.placeholder.com/200x300?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/200x300?text=No+Image';">
                            <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <a href="#" class="bg-white text-gray-900 px-4 py-2 rounded-button text-sm font-medium hover:bg-gray-200 transition duration-200">View Details</a>
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-medium text-gray-800 line-clamp-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="text-sm text-gray-600 mt-1">by <?php echo htmlspecialchars($book['author']); ?></p>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($book['category_name']); ?></p>
                            <?php if ($book['for_exchange']): ?>
                                <span class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">For Exchange</span>
                            <?php else: ?>
                                <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">For Sale ($<?php echo number_format($book['price'], 2); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($user_books)): ?>
                    <p class="col-span-full text-center text-gray-500 text-lg py-8">No books listed.</p>
                <?php endif; ?>
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