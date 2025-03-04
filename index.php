<?php
include 'config.php';
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
    $user_full_name = htmlspecialchars($user['fullname'] ?? 'Guest');

    // Fetch cart count for logged-in user
    $cart_query = "SELECT COUNT(*) AS cart_count FROM cart WHERE user_id = ?";
    $cart_stmt = $conn->prepare($cart_query);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart_data = $cart_result->fetch_assoc();
    $cart_count = $cart_data['cart_count'] ?? 0;
}

// Fetch ALL categories from the database (removed LIMIT 4)
$category_query = "SELECT * FROM categories";
$category_result = $conn->query($category_query);

// Fetch featured books from the database
$book_query = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, c.name as category_name 
               FROM books b 
               JOIN categories c ON b.category_id = c.id 
               WHERE b.price > 0 LIMIT 4";
$book_result = $conn->query($book_query);

// Add item to cart
if (isset($_POST['add_to_cart'])) {
    if (isset($_SESSION['user_id'])) {
        $book_id = $_POST['book_id'];
        $quantity = max(1, (int)$_POST['quantity']); // Ensure quantity is at least 1

        // Check if the book is already in the cart
        $check_cart_query = "SELECT * FROM cart WHERE user_id = ? AND book_id = ?";
        $check_cart_stmt = $conn->prepare($check_cart_query);
        $check_cart_stmt->bind_param("ii", $user_id, $book_id);
        $check_cart_stmt->execute();
        $cart_result = $check_cart_stmt->get_result();

        if ($cart_result->num_rows > 0) {
            // Update quantity if book already in cart
            $update_cart_query = "UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND book_id = ?";
            $update_cart_stmt = $conn->prepare($update_cart_query);
            $update_cart_stmt->bind_param("iii", $quantity, $user_id, $book_id);
            $update_cart_stmt->execute();
        } else {
            // Insert new item to cart
            $add_to_cart_query = "INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, ?)";
            $add_to_cart_stmt = $conn->prepare($add_to_cart_query);
            $add_to_cart_stmt->bind_param("iii", $user_id, $book_id, $quantity);
            $add_to_cart_stmt->execute();
        }

        // Redirect back to the page after adding to the cart
        header("Location: index.php");
        exit();
    } else {
        // Redirect to login if user is not logged in
        header("Location: login.php");
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
    <title>BookCommerce - Exchange, Buy & Sell Books</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .book-categories::-webkit-scrollbar {
            display: none;
        }
        .modal {
            transition: opacity 0.25s ease;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

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
                    <a href="exchange_requests.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Requests</a>
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
                                    <?php echo $cart_count; ?>
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
                <a href="exchange_requests.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Requests</a>
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

    <!-- Main Content with Padding to Avoid Overlap with Fixed Navbar -->
    <main class="pt-16 pb-12 bg-gray-50">
        <section class="relative bg-white overflow-hidden">
            <div class="max-w-7xl mx-auto">
                <div class="relative z-10 pb-8 bg-white sm:pb-16 md:pb-20 lg:pb-28 xl:pb-32">
                    <div class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                        <div class="sm:text-center lg:text-left">
                            <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                                <span class="block">Exchange, Buy & Sell</span>
                                <span class="block text-primary">Your Book Marketplace</span>
                            </h1>
                            <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                                Join our community of book lovers. Find your next read, sell your finished books, or exchange with fellow readers.
                            </p>
                            <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                                <div class="rounded-md shadow">
                                    <a href="sell_book.php">
                                        <button class="w-full sm:w-auto flex items-center justify-center px-6 py-3 text-white bg-primary hover:bg-primary/90 md:px-10 md:py-4 text-base sm:text-lg font-medium rounded-md">
                                            Sell Book
                                        </button>
                                    </a>
                                </div>
                                <div class="mt-3 sm:mt-0 sm:ml-3">
                                    <a href="exchange_book.php">
                                        <button class="w-full sm:w-auto flex items-center justify-center px-6 py-3 text-primary bg-primary/10 hover:bg-primary/20 md:px-10 md:py-4 text-base sm:text-lg font-medium rounded-md">
                                            Exchange Book
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2">
                <img class="h-56 w-full object-cover sm:h-72 md:h-96 lg:w-full lg:h-full object-center" src="https://public.readdy.ai/ai/img_res/375cb984f3e7b4e738a9eba3d54eb00c.jpg" alt="Library">
            </div>
        </section>

        <section class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-8">Popular Categories</h2>
                <div class="book-categories flex space-x-6 overflow-x-auto pb-4 px-4">
                    <?php while ($category = $category_result->fetch_assoc()): ?>
                        <div class="flex-none">
                            <div class="w-40 h-48 bg-primary/5 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:bg-primary/10 transition-all duration-200 ease-in-out">
                                <div class="w-12 h-12 flex items-center justify-center">
                                    <i class="ri-book-line text-primary text-2xl"></i>
                                </div>
                                <span class="mt-4 text-gray-900 font-medium"><?php echo htmlspecialchars($category['name']); ?></span>
                                <span class="text-sm text-gray-500">Books: <?php echo $category['book_count']; ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <div class="max-w-7xl mx-auto px-4 py-16">
            <h2 class="text-3xl font-semibold text-gray-900 mb-6">Featured Books</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                <?php while ($book = $book_result->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <img class="h-64 w-full object-cover" src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="Book Cover">
                        <div class="p-4">
                            <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="text-gray-500"><?php echo htmlspecialchars($book['author']); ?></p>
                            <p class="mt-2 text-gray-900"><?php echo htmlspecialchars($book['category_name']); ?></p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xl font-semibold text-primary"><?php echo number_format($book['price'], 2); ?> USD</span>
                                <form action="index.php" method="post" class="flex items-center">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <input type="number" name="quantity" value="1" min="1" class="border border-gray-300 rounded-md px-2 py-1 w-16">
                                    <button type="submit" name="add_to_cart" class="bg-primary text-white px-4 py-2 ml-2 rounded-md">Add to Cart</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

    </main>

    <!-- Cart Modal -->
    <div id="cartModal" class="hidden fixed inset-0 z-50 bg-black bg-opacity-50 flex justify-center items-center">
        <div class="bg-white w-96 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Your Cart</h2>
            <div id="cartItems" class="space-y-4">
                <!-- Cart items will be dynamically loaded here -->
            </div>
            <div class="mt-4 flex justify-between">
                <button class="bg-primary text-white px-6 py-2 rounded-md" onclick="checkout()">Checkout</button>
                <button class="bg-gray-500 text-white px-6 py-2 rounded-md" onclick="closeCartModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Toggle mobile menu
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        // Close mobile menu when a link is clicked
        document.querySelectorAll('#mobileMenu a').forEach(link => {
            link.addEventListener('click', function() {
                document.getElementById('mobileMenu').classList.add('hidden');
            });
        });

        // Show Cart Modal
        document.getElementById("cartBtn")?.addEventListener("click", function() {
            document.getElementById("cartModal").classList.remove("hidden");
            loadCartItems();
        });

        // Close Cart Modal
        function closeCartModal() {
            document.getElementById("cartModal").classList.add("hidden");
        }

        // Load Cart Items
        function loadCartItems() {
            fetch('load_cart.php')
                .then(response => response.json())
                .then(data => {
                    const cartItems = data.map(item => `
                        <div class="flex justify-between items-center">
                            <span>${item.title}</span>
                            <span>${item.quantity} x $${item.price}</span>
                        </div>
                    `).join('');
                    document.getElementById('cartItems').innerHTML = cartItems || '<p>Your cart is empty.</p>';
                }).catch(error => console.error('Error loading cart:', error));
        }

        // Checkout Functionality
        function checkout() {
            window.location.href = "checkout.php";
        }
    </script>
</body>
</html>