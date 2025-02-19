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
$result = $stmt->get_result();
$user_full_name = '';
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_full_name = htmlspecialchars($user['fullname']);
} else {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Function to fetch user's books available for exchange
function getUserExchangeableBooks($conn, $user_id) {
    $sql = "SELECT b.id, b.title, b.author, b.image_url, c.name AS category_name
            FROM books b
            JOIN categories c ON b.category_id = c.id
            WHERE b.user_id = ? AND b.for_exchange = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    return $books;
}

// Function to fetch exchangeable books (offered by other users)
function getExchangeableBooks($conn, $user_id, $searchTerm = '', $categories = []) {
    $sql = "SELECT b.id, b.title, b.author, b.image_url, c.name AS category_name, u.fullname AS owner_name
            FROM books b
            JOIN categories c ON b.category_id = c.id
            JOIN users u ON b.user_id = u.id
            WHERE b.for_exchange = TRUE AND b.user_id != ? AND b.title LIKE ?";
    
    $params = [$user_id, "%$searchTerm%"];
    $types = "ss";

    if (!empty($categories)) {
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $sql .= " AND b.category_id IN ($placeholders)";
        $params = array_merge($params, array_map('intval', $categories)); // Ensure integers
        $types .= str_repeat("i", count($categories));
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    return $books;
}

// Fetch categories for the form
$categories_query = "SELECT id, name FROM categories";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Handle search and filter
$searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
$selectedCategories = isset($_GET['categories']) ? (is_array($_GET['categories']) ? $_GET['categories'] : explode(',', $_GET['categories'])) : [];

$exchangeableBooks = getExchangeableBooks($conn, $user_id, $searchTerm, $selectedCategories);
$userBooks = getUserExchangeableBooks($conn, $user_id);

// Handle adding a book for exchange
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book_for_exchange'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category_id = intval($_POST['category_id']);
    $image_url = trim($_POST['image_url']); // Optional, can be empty if no image

    if (!empty($title) && !empty($author) && $category_id > 0) {
        $add_book_sql = "INSERT INTO books (title, author, price, book_condition, image_url, category_id, user_id, for_exchange) VALUES (?, ?, 0.00, 'Used - Good', ?, ?, ?, TRUE)";
        $add_book_stmt = $conn->prepare($add_book_sql);
        $add_book_stmt->bind_param("sssis", $title, $author, $image_url, $category_id, $user_id);
        if ($add_book_stmt->execute()) {
            header('Location: exchange_book.php?success=2');
            exit();
        } else {
            $error = "Failed to add book for exchange. Please try again.";
        }
    } else {
        $error = "All fields are required and must be valid.";
    }
}

// Handle exchange request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exchange_book'])) {
    $offered_book_id = $_POST['offered_book_id'];
    $requested_book_id = $_POST['requested_book_id'];

    // Check if the books exist and are available for exchange
    $check_book_sql = "SELECT * FROM books WHERE id = ? AND for_exchange = TRUE AND user_id IS NOT NULL";
    $check_stmt = $conn->prepare($check_book_sql);
    $check_stmt->bind_param("i", $offered_book_id);
    $check_stmt->execute();
    $offered_book = $check_stmt->get_result()->fetch_assoc();

    $check_stmt->bind_param("i", $requested_book_id);
    $check_stmt->execute();
    $requested_book = $check_stmt->get_result()->fetch_assoc();

    if ($offered_book && $requested_book) {
        if ($offered_book['user_id'] != $user_id) {
            $error = "You can only offer your own books for exchange.";
        } else {
            $exchange_sql = "INSERT INTO exchange_requests (user_id, offered_book_id, requested_book_id, status) VALUES (?, ?, ?, 'pending')";
            $exchange_stmt = $conn->prepare($exchange_sql);
            $exchange_stmt->bind_param("iii", $user_id, $offered_book_id, $requested_book_id);
            if ($exchange_stmt->execute()) {
                header('Location: exchange_book.php?success=1');
                exit();
            } else {
                $error = "Failed to submit exchange request. Please try again.";
            }
        }
    } else {
        $error = "One or both books are not available for exchange.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange Books - BookCommerce</title>
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
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .close-modal {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            font-size: 20px;
            color: #666;
        }
        @media (max-width: 640px) {
            .modal-content {
                width: 85%;
            }
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
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <button id="mobileMenuButton" class="md:hidden p-2 text-gray-900 hover:text-primary">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                    <a href="index.php" class="font-['Pacifico'] text-2xl text-primary">BookCommerce</a>
                </div>
                
                <div class="flex-1 max-w-xl mx-4 md:mx-8 hidden md:block">
                    <div class="relative">
                        <form action="books.php" method="get">
                            <input type="text" name="q" placeholder="Search for books, authors, or genres..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                            <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-gray-400">
                                <i class="ri-search-line"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (!empty($user_full_name)): ?>
                        <div class="flex items-center space-x-2">
                            <span class="text-gray-900 font-medium hidden md:inline"><?php echo $user_full_name; ?></span>
                            <a href="account.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">
                                <i class="ri-user-line text-xl"></i>
                            </a>
                            <a href="logout.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">
                                Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">Login</a>
                        <a href="register.php" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md hidden md:inline">Register</a>
                    <?php endif; ?>

                    <div class="relative">
                        <a href="cart.php" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary">
                            <i class="ri-shopping-cart-line text-xl"></i>
                        </a>
                        <span class="absolute -top-1 -right-1 w-5 h-5 flex items-center justify-center bg-primary text-white text-xs rounded-full">0</span>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="md:hidden hidden bg-white shadow-md absolute w-full top-16 left-0">
                <form action="books.php" method="get" class="px-4 py-2">
                    <input type="text" name="q" placeholder="Search for books, authors, or genres..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-gray-400">
                        <i class="ri-search-line"></i>
                    </button>
                </form>
                <a href="index.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Home</a>
                <a href="books.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Books</a>
                <a href="cart.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Cart</a>
                <a href="exchange_book.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Exchange</a>
                <?php if (!empty($user_full_name)): ?>
                    <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">
                        <i class="ri-user-line mr-2"></i> Account
                    </a>
                    <a href="logout.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Login</a>
                    <a href="register.php" class="block bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md mx-4 my-2">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] == 1): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6" role="alert">
                    <span class="block sm:inline">Exchange request submitted successfully!</span>
                </div>
            <?php elseif ($_GET['success'] == 2): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6" role="alert">
                    <span class="block sm:inline">Book added for exchange successfully!</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-6" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row gap-6">
            <!-- Sidebar for User's Books and Add Button -->
            <aside class="w-full md:w-1/4 lg:w-1/5 flex-shrink-0 order-2 md:order-1">
                <div class="bg-white rounded-lg shadow-md border p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800 text-lg">Your Books for Exchange</h3>
                        <button id="openModalBtn" class="text-primary hover:text-primary/80 text-sm font-medium">Add a Book</button>
                    </div>
                    <ul class="space-y-3 max-h-60 overflow-y-auto">
                        <?php foreach ($userBooks as $book): ?>
                            <li class="flex items-center justify-between text-sm text-gray-600 p-2 bg-gray-50 rounded-md hover:bg-gray-100 transition duration-200">
                                <span class="line-clamp-1"><?php echo htmlspecialchars($book['title']); ?></span>
                                <button onclick="setExchangeBook(<?php echo $book['id']; ?>)" class="text-primary hover:text-primary/80 text-xs">Use</button>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($userBooks)): ?>
                            <li class="text-center text-gray-500 p-2">No books available for exchange.</li>
                        <?php endif; ?>
                    </ul>
                    <form id="exchangeForm" method="POST" class="mt-4 hidden">
                        <input type="hidden" name="offered_book_id" id="offeredBookId">
                        <input type="hidden" name="exchange_book" value="1">
                        <button type="submit" class="w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Request Exchange</button>
                    </form>
                </div>
            </aside>

            <!-- Main Content for Exchangeable Books -->
            <main class="flex-1 order-1 md:order-2">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Books Available for Exchange</h2>
                <div class="space-y-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search books..." class="w-full md:w-3/4 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" onkeyup="searchBooks(this.value)">
                        <form method="GET" class="w-full md:w-1/4">
                            <label class="block text-sm font-medium text-gray-700 mb-1 md:hidden">Categories</label>
                            <select name="categories[]" multiple class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="1">Fiction</option>
                                <option value="2">Non-Fiction</option>
                                <option value="3">Children's Books</option>
                                <option value="4">Academic</option>
                            </select>
                            <button type="submit" class="mt-2 w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Filter</button>
                        </form>
                    </div>

                    <div id="exchangeBooksGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach ($exchangeableBooks as $book): ?>
                            <div class="group bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                                <div class="relative aspect-[3/4] rounded-t-lg overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($book['image_url'] ?: 'https://via.placeholder.com/200x300?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/200x300?text=No+Image';">
                                    <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button onclick="requestExchange(<?php echo $book['id']; ?>)" class="bg-white text-gray-900 px-4 py-2 rounded-button text-sm font-medium hover:bg-gray-200 transition duration-200">Exchange</button>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3 class="font-medium text-gray-800 line-clamp-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">by <?php echo htmlspecialchars($book['author']); ?></p>
                                    <p class="text-gray-500 text-sm mt-1">Owner: <?php echo htmlspecialchars($book['owner_name']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($exchangeableBooks)): ?>
                            <p class="col-span-full text-center text-gray-500 text-lg py-8">No books available for exchange matching your criteria.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal for Adding a Book for Exchange -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">×</span>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Book for Exchange</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_book_for_exchange" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Author</label>
                    <input type="text" name="author" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Image URL (optional)</label>
                    <input type="text" name="image_url" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <button type="submit" class="w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Add Book</button>
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

        let selectedBookId = null;

        function setExchangeBook(bookId) {
            selectedBookId = bookId;
            document.getElementById('offeredBookId').value = bookId;
            document.getElementById('exchangeForm').classList.remove('hidden');
        }

        function requestExchange(requestedBookId) {
            if (!selectedBookId) {
                alert('Please select a book to offer for exchange.');
                return;
            }

            if (confirm('Are you sure you want to request an exchange for this book?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'exchange_book.php';

                const offeredInput = document.createElement('input');
                offeredInput.type = 'hidden';
                offeredInput.name = 'offered_book_id';
                offeredInput.value = selectedBookId;

                const requestedInput = document.createElement('input');
                requestedInput.type = 'hidden';
                requestedInput.name = 'requested_book_id';
                requestedInput.value = requestedBookId;

                const exchangeInput = document.createElement('input');
                exchangeInput.type = 'hidden';
                exchangeInput.name = 'exchange_book';
                exchangeInput.value = '1';

                form.appendChild(offeredInput);
                form.appendChild(requestedInput);
                form.appendChild(exchangeInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        function searchBooks(query) {
            fetch(`exchange_book.php?q=${encodeURIComponent(query)}&categories=${encodeURIComponent(JSON.stringify(<?php echo json_encode($selectedCategories); ?>))}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newGrid = doc.getElementById('exchangeBooksGrid');
                if (newGrid) {
                    document.getElementById('exchangeBooksGrid').innerHTML = newGrid.innerHTML;
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Modal functionality
        document.getElementById('openModalBtn').addEventListener('click', function() {
            document.getElementById('addBookModal').style.display = 'flex';
        });

        function closeModal() {
            document.getElementById('addBookModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addBookModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>

</body>
</html>