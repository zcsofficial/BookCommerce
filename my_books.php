<?php
include 'config.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$user_query = "SELECT fullname FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$user_fullname = htmlspecialchars($user['fullname'] ?? 'Guest');

// Fetch cart count
$cart_query = "SELECT COUNT(*) AS cart_count FROM cart WHERE user_id = ?";
$cart_stmt = $conn->prepare($cart_query);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_data = $cart_result->fetch_assoc();
$cart_count = $cart_data['cart_count'] ?? 0;

// Pagination setup
$per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Fetch total number of books
$total_query = "SELECT COUNT(*) AS total FROM books WHERE user_id = ?";
$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_data = $total_result->fetch_assoc();
$total_books = $total_data['total'];
$total_pages = ceil($total_books / $per_page);

// Fetch user's books
$books_query = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, c.name AS category_name, b.for_exchange
                FROM books b JOIN categories c ON b.category_id = c.id
                WHERE b.user_id = ? LIMIT ? OFFSET ?";
$books_stmt = $conn->prepare($books_query);
$books_stmt->bind_param("iii", $user_id, $per_page, $offset);
$books_stmt->execute();
$books_result = $books_stmt->get_result();
$user_books = $books_result->fetch_all(MYSQLI_ASSOC);

// Handle book deletion
if (isset($_POST['delete_book'])) {
    $book_id = (int)$_POST['book_id'];
    $delete_query = "DELETE FROM books WHERE id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $book_id, $user_id);
    if ($delete_stmt->execute()) {
        header("Location: my_books.php?page=$page");
        exit();
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
    <title>My Books - BookCommerce</title>
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
                    <span class="text-gray-900 font-medium hidden md:inline"><?php echo $user_fullname; ?></span>
                    <a href="account.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium"><i class="ri-user-line text-xl"></i></a>
                    <a href="?logout=true" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">Logout</a>
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
                <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b"><i class="ri-user-line mr-2"></i>Account</a>
                <a href="?logout=true" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">My Books</h1>
            <a href="sell_book.php" class="bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90">Add New Book</a>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if (empty($user_books)): ?>
                <p class="text-gray-500 text-center py-4">No books listed yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($user_books as $book): ?>
                        <div class="group bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                            <div class="relative aspect-[3/4] rounded-t-lg overflow-hidden">
                                <img src="<?php echo htmlspecialchars($book['image_url'] ?: 'https://via.placeholder.com/200x300?text=No+Image'); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                     class="w-full h-full object-cover" 
                                     onerror="this.src='https://via.placeholder.com/200x300?text=No+Image';">
                                <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2">
                                    <a href="book_details.php?id=<?php echo $book['id']; ?>" class="bg-white text-gray-900 px-3 py-1 rounded-md text-sm hover:bg-gray-200">View</a>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" name="delete_book" class="bg-red-500 text-white px-3 py-1 rounded-md text-sm hover:bg-red-600">Delete</button>
                                    </form>
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
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary/90">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="px-4 py-2 <?php echo $i === $page ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-md hover:bg-primary/90 hover:text-white"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary/90">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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