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

// Fetch cart count for logged-in user
$cart_query = "SELECT COUNT(*) AS cart_count FROM cart WHERE user_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_data = $cart_result->fetch_assoc();
$cart_count = $cart_data['cart_count'] ?? 0;

// Fetch user's orders
$orders_query = "SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);

// Fetch user's books (both for sale and exchange)
$books_query = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, c.name AS category_name, b.for_exchange
                FROM books b
                JOIN categories c ON b.category_id = c.id
                WHERE b.user_id = ? LIMIT 6";
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
    <style>
        .profile-card {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
        }
        .book-grid::-webkit-scrollbar {
            height: 6px;
        }
        .book-grid::-webkit-scrollbar-thumb {
            background-color: #4F46E5;
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans">

    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <button id="mobileMenuButton" class="md:hidden p-2 text-gray-900 hover:text-primary">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                    <a href="index.php" class="font-['Pacifico'] text-2xl text-primary ml-2 md:ml-0">BookCommerce</a>
                </div>
                <div class="hidden md:flex md:items-center md:space-x-8">
                    <a href="index.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Home</a>
                    <a href="books.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Books</a>
                    <a href="cart.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Cart</a>
                    <a href="exchange_requests.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Requests</a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-900 font-medium hidden md:inline"><?php echo $user_fullname; ?></span>
                        <a href="account.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                            <i class="ri-user-line text-xl"></i>
                        </a>
                        <a href="?logout=true" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">Logout</a>
                    </div>
                    <div class="relative">
                        <button id="cartBtn" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                            <i class="ri-shopping-cart-line text-xl"></i>
                            <span class="absolute -top-1 -right-2 rounded-full bg-primary text-white text-xs px-2 py-1"><?php echo $cart_count; ?></span>
                        </button>
                    </div>
                </div>
            </div>
            <div id="mobileMenu" class="md:hidden hidden bg-white shadow-md absolute w-full top-16 left-0">
                <a href="index.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Home</a>
                <a href="books.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Books</a>
                <a href="cart.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Cart</a>
                <a href="exchange_requests.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Requests</a>
                <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">
                    <i class="ri-user-line mr-2"></i> Account
                </a>
                <a href="?logout=true" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Profile Header -->
        <div class="profile-card rounded-lg shadow-lg p-6 text-white mb-8">
            <div class="flex items-center space-x-6">
                <div class="w-24 h-24 rounded-full bg-white flex items-center justify-center text-primary text-4xl font-bold">
                    <?php echo strtoupper(substr($user_fullname, 0, 1)); ?>
                </div>
                <div>
                    <h1 class="text-3xl font-bold"><?php echo $user_fullname; ?></h1>
                    <p class="text-sm opacity-80"><?php echo $user_email; ?></p>
                    <p class="text-sm mt-1">Joined: <?php echo date('F d, Y', strtotime($created_at)); ?> • Role: <span class="capitalize"><?php echo $user_role; ?></span></p>
                </div>
            </div>
            <div class="mt-4 flex space-x-4">
                <button id="editProfileBtn" class="bg-white text-primary px-4 py-2 rounded-md font-medium hover:bg-gray-100 transition">Edit Profile</button>
                <a href="sell_book.php" class="bg-secondary text-white px-4 py-2 rounded-md font-medium hover:bg-secondary/90 transition">Add New Book</a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Edit Profile Modal -->
        <div id="editProfileModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Edit Profile</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="update_profile" value="1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" name="fullname" value="<?php echo $user_fullname; ?>" class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="<?php echo $user_email; ?>" class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" id="closeEditModal" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary/90">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($orders); ?></p>
                </div>
                <i class="ri-shopping-bag-line text-3xl text-primary"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Books Listed</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($user_books); ?></p>
                </div>
                <i class="ri-book-line text-3xl text-primary"></i>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Cart Items</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $cart_count; ?></p>
                </div>
                <i class="ri-shopping-cart-line text-3xl text-primary"></i>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Recent Orders</h2>
                <a href="orders.php" class="text-primary hover:underline text-sm">View All</a>
            </div>
            <?php if (empty($orders)): ?>
                <p class="text-gray-500 text-center py-4">No recent orders.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($orders as $order): ?>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-md">
                            <div>
                                <p class="text-sm font-medium text-gray-800">Order #<?php echo $order['id']; ?></p>
                                <p class="text-xs text-gray-600"><?php echo date('F d, Y', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-800">$<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p class="text-xs capitalize <?php echo $order['status'] === 'completed' ? 'text-green-600' : 'text-yellow-600'; ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Books Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">My Books</h2>
                <a href="my_books.php" class="text-primary hover:underline text-sm">View All</a>
            </div>
            <?php if (empty($user_books)): ?>
                <p class="text-gray-500 text-center py-4">No books listed yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 book-grid overflow-x-auto">
                    <?php foreach ($user_books as $book): ?>
                        <div class="group bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                            <div class="relative aspect-[3/4] rounded-t-lg overflow-hidden">
                                <img src="<?php echo htmlspecialchars($book['image_url'] ?: 'https://via.placeholder.com/200x300?text=No+Image'); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                     class="w-full h-full object-cover" 
                                     onerror="this.src='https://via.placeholder.com/200x300?text=No+Image';">
                                <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <a href="book_details.php?id=<?php echo $book['id']; ?>" class="bg-white text-gray-900 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 transition">View</a>
                                </div>
                            </div>
                            <div class="p-4">
                                <h3 class="font-medium text-gray-800 line-clamp-1"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="text-sm text-gray-600 line-clamp-1"><?php echo htmlspecialchars($book['author']); ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($book['category_name']); ?></p>
                                <p class="text-sm font-semibold mt-2 <?php echo $book['for_exchange'] ? 'text-green-600' : 'text-primary'; ?>">
                                    <?php echo $book['for_exchange'] ? 'For Exchange' : '$' . number_format($book['price'], 2); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
        document.getElementById('mobileMenuButton').addEventListener('click', () => {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        // Close mobile menu on link click
        document.querySelectorAll('#mobileMenu a').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('mobileMenu').classList.add('hidden');
            });
        });

        // Edit Profile Modal
        const editBtn = document.getElementById('editProfileBtn');
        const editModal = document.getElementById('editProfileModal');
        const closeModalBtn = document.getElementById('closeEditModal');

        editBtn.addEventListener('click', () => {
            editModal.classList.remove('hidden');
        });

        closeModalBtn.addEventListener('click', () => {
            editModal.classList.add('hidden');
        });

        // Close modal on outside click
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) {
                editModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>