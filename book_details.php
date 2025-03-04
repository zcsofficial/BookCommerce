<?php
include 'config.php';
session_start();

// Check if book ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: books.php');
    exit();
}

$book_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'] ?? null;

// Fetch book details
$book_query = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, b.for_exchange, c.name AS category_name, u.fullname AS owner_name
               FROM books b
               JOIN categories c ON b.category_id = c.id
               LEFT JOIN users u ON b.user_id = u.id
               WHERE b.id = ?";
$book_stmt = $conn->prepare($book_query);
$book_stmt->bind_param("i", $book_id);
$book_stmt->execute();
$book_result = $book_stmt->get_result();
$book = $book_result->fetch_assoc();

if (!$book) {
    header('Location: books.php');
    exit();
}

// Fetch user details if logged in
$user_fullname = 'Guest';
$cart_count = 0;
if ($user_id) {
    $user_query = "SELECT fullname FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_fullname = htmlspecialchars($user['fullname'] ?? 'Guest');

    $cart_query = "SELECT COUNT(*) AS cart_count FROM cart WHERE user_id = ?";
    $cart_stmt = $conn->prepare($cart_query);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart_data = $cart_result->fetch_assoc();
    $cart_count = $cart_data['cart_count'] ?? 0;
}

// Add to cart
if (isset($_POST['add_to_cart']) && $user_id) {
    $quantity = max(1, (int)$_POST['quantity']);
    $check_cart_query = "SELECT * FROM cart WHERE user_id = ? AND book_id = ?";
    $check_cart_stmt = $conn->prepare($check_cart_query);
    $check_cart_stmt->bind_param("ii", $user_id, $book_id);
    $check_cart_stmt->execute();
    $cart_result = $check_cart_stmt->get_result();

    if ($cart_result->num_rows > 0) {
        $update_cart_query = "UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND book_id = ?";
        $update_cart_stmt = $conn->prepare($update_cart_query);
        $update_cart_stmt->bind_param("iii", $quantity, $user_id, $book_id);
        $update_cart_stmt->execute();
    } else {
        $add_to_cart_query = "INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, ?)";
        $add_to_cart_stmt = $conn->prepare($add_to_cart_query);
        $add_to_cart_stmt->bind_param("iii", $user_id, $book_id, $quantity);
        $add_to_cart_stmt->execute();
    }
    header("Location: book_details.php?id=$book_id");
    exit();
}

// Request exchange (basic implementation)
if (isset($_POST['request_exchange']) && $user_id) {
    $exchange_query = "INSERT INTO exchange_requests (user_id, offered_book_id, requested_book_id) VALUES (?, ?, ?)";
    $exchange_stmt = $conn->prepare($exchange_query);
    $offered_book_id = (int)$_POST['offered_book_id'];
    $exchange_stmt->bind_param("iii", $user_id, $offered_book_id, $book_id);
    $exchange_stmt->execute();
    $success = "Exchange request sent successfully!";
}

// Fetch user's books for exchange dropdown
$exchange_books = [];
if ($user_id) {
    $exchange_books_query = "SELECT id, title FROM books WHERE user_id = ? AND for_exchange = 1";
    $exchange_books_stmt = $conn->prepare($exchange_books_query);
    $exchange_books_stmt->bind_param("i", $user_id);
    $exchange_books_stmt->execute();
    $exchange_books_result = $exchange_books_stmt->get_result();
    $exchange_books = $exchange_books_result->fetch_all(MYSQLI_ASSOC);
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
    <title><?php echo htmlspecialchars($book['title']); ?> - BookCommerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#4F46E5', secondary: '#10B981' },
                    borderRadius: { 'button': '8px' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
    <!-- Navigation -->
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
                    <?php if ($user_id): ?>
                        <span class="text-gray-900 font-medium hidden md:inline"><?php echo $user_fullname; ?></span>
                        <a href="account.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium"><i class="ri-user-line text-xl"></i></a>
                        <a href="?logout=true" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">Logout</a>
                        <div class="relative">
                            <button id="cartBtn" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                <i class="ri-shopping-cart-line text-xl"></i>
                                <span class="absolute -top-1 -right-2 rounded-full bg-primary text-white text-xs px-2 py-1"><?php echo $cart_count; ?></span>
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Login</a>
                        <a href="register.php" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md">Register</a>
                    <?php endif; ?>
                </div>
            </div>
            <div id="mobileMenu" class="md:hidden hidden bg-white shadow-md absolute w-full top-16 left-0">
                <a href="index.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Home</a>
                <a href="books.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Books</a>
                <a href="cart.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Cart</a>
                <a href="exchange_requests.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Requests</a>
                <?php if ($user_id): ?>
                    <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b"><i class="ri-user-line mr-2"></i>Account</a>
                    <a href="?logout=true" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Login</a>
                    <a href="register.php" class="block bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md mx-4 my-2">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <img src="<?php echo htmlspecialchars($book['image_url'] ?: 'https://via.placeholder.com/400x600?text=No+Image'); ?>" 
                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                         class="w-full h-auto rounded-md" 
                         onerror="this.src='https://via.placeholder.com/400x600?text=No+Image';">
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($book['title']); ?></h1>
                    <p class="text-gray-600 mb-2">by <?php echo htmlspecialchars($book['author']); ?></p>
                    <p class="text-sm text-gray-500 mb-4">Category: <?php echo htmlspecialchars($book['category_name']); ?></p>
                    <p class="text-sm text-gray-600 mb-4">Condition: <?php echo htmlspecialchars($book['book_condition']); ?></p>
                    <p class="text-sm text-gray-600 mb-4">Listed by: <?php echo htmlspecialchars($book['owner_name'] ?: 'Unknown'); ?></p>
                    <div class="mb-6">
                        <?php if ($book['for_exchange']): ?>
                            <span class="text-lg font-semibold text-green-600">Available for Exchange</span>
                        <?php else: ?>
                            <span class="text-lg font-semibold text-primary">$<?php echo number_format($book['price'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($user_id && $book['user_id'] != $user_id): ?>
                        <?php if (!$book['for_exchange']): ?>
                            <form method="POST" class="flex items-center space-x-4 mb-4">
                                <input type="number" name="quantity" value="1" min="1" class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <button type="submit" name="add_to_cart" class="bg-primary text-white px-6 py-2 rounded-md hover:bg-primary/90">Add to Cart</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($book['for_exchange'] && !empty($exchange_books)): ?>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Select a book to offer:</label>
                                    <select name="offered_book_id" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" required>
                                        <option value="">-- Choose a book --</option>
                                        <?php foreach ($exchange_books as $ex_book): ?>
                                            <option value="<?php echo $ex_book['id']; ?>"><?php echo htmlspecialchars($ex_book['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="request_exchange" class="bg-secondary text-white px-6 py-2 rounded-md hover:bg-secondary/90">Request Exchange</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (!$user_id): ?>
                        <p class="text-gray-600">Please <a href="login.php" class="text-primary hover:underline">log in</a> to purchase or request an exchange.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('mobileMenuButton').addEventListener('click', () => {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });
        document.querySelectorAll('#mobileMenu a').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('mobileMenu').classList.add('hidden');
            });
        });
    </script>
</body>
</html>