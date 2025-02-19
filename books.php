<?php
include('config.php');

// Function to fetch books from the database with search and filter functionality
function getBooks($conn, $searchTerm = '', $selectedCategories = [], $priceRange = [0, 100], $ratingFilter = null) {
    // Construct SQL query with filters
    $sql = "SELECT books.id, books.title, books.author, books.price, books.book_condition, books.image_url, categories.name AS category_name
            FROM books 
            JOIN categories ON books.category_id = categories.id
            WHERE books.title LIKE ?";

    // Adding filters
    if (!empty($selectedCategories)) {
        $categoriesPlaceholder = implode(',', array_fill(0, count($selectedCategories), '?'));
        $sql .= " AND books.category_id IN ($categoriesPlaceholder)";
    }

    if ($priceRange[0] >= 0 && $priceRange[1] <= 100) {
        $sql .= " AND books.price BETWEEN ? AND ?";
    }

    if ($ratingFilter) {
        $sql .= " AND books.rating >= ?";
    }

    $stmt = $conn->prepare($sql);
    $searchTerm = "%$searchTerm%";

    // Initialize the types string and parameters array
    $types = "s"; // For the search term (string)
    $params = [$searchTerm];

    // Add category filter parameters if selected
    if (!empty($selectedCategories)) {
        $types .= str_repeat("i", count($selectedCategories)); // For category IDs (integers)
        $params = array_merge($params, $selectedCategories);
    }

    // Add price range parameters
    $params[] = $priceRange[0]; // For the minimum price
    $params[] = $priceRange[1]; // For the maximum price
    $types .= "ii"; // For the price range (two integers)

    // Add rating filter if provided
    if ($ratingFilter) {
        $params[] = $ratingFilter;
        $types .= "i"; // For rating (integer)
    }

    // Bind dynamically
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $books = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
    }
    return $books;
}

// Search and filter handling
$searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
$selectedCategories = isset($_GET['categories']) ? explode(',', $_GET['categories']) : [];
$minPrice = isset($_GET['min_price']) ? $_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? $_GET['max_price'] : 100;
$ratingFilter = isset($_GET['rating']) ? $_GET['rating'] : null;

// Fetch books with the applied filters
$books = getBooks($conn, $searchTerm, $selectedCategories, [$minPrice, $maxPrice], $ratingFilter);

// Add to cart functionality (session-based cart system)
session_start();
if (isset($_GET['add_to_cart']) && isset($_SESSION['user_id'])) {
    $book_id = $_GET['add_to_cart'];
    $user_id = $_SESSION['user_id'];

    // Add to the cart or update quantity if already in the cart
    $checkCart = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND book_id = ?");
    $checkCart->bind_param("ii", $user_id, $book_id);
    $checkCart->execute();
    $result = $checkCart->get_result();

    if ($result->num_rows > 0) {
        // Book is already in the cart, so we update the quantity
        $updateCart = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND book_id = ?");
        $updateCart->bind_param("ii", $user_id, $book_id);
        $updateCart->execute();
    } else {
        // Add a new entry to the cart
        $insertCart = $conn->prepare("INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, 1)");
        $insertCart->bind_param("ii", $user_id, $book_id);
        $insertCart->execute();
    }

    // Redirect back to the same page to refresh the cart count
    header("Location: books.php?q=" . urlencode($searchTerm) . "&categories=" . implode(',', $selectedCategories) . "&min_price=" . $minPrice . "&max_price=" . $maxPrice . "&rating=" . $ratingFilter);
    exit();
}

// Get the cart count for the current user
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $cartQuery = $conn->prepare("SELECT SUM(quantity) AS cart_count FROM cart WHERE user_id = ?");
    $cartQuery->bind_param("i", $user_id);
    $cartQuery->execute();
    $cartResult = $cartQuery->get_result();
    $cartRow = $cartResult->fetch_assoc();
    $cartCount = $cartRow['cart_count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            background: #57b5e7;
            border-radius: 50%;
            cursor: pointer;
        }
        .group:hover .overlay {
            opacity: 1;
        }
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
<body class="bg-white min-h-screen font-sans">

    <!-- Navigation Bar -->
    <nav class="border-b fixed w-full z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <button id="mobileMenuButton" class="md:hidden p-2 text-gray-900 hover:text-primary">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                    <a href="index.php" class="text-2xl font-['Pacifico'] text-primary">BookCommerce</a>
                </div>
                
                <div class="flex-1 max-w-2xl mx-8">
                    <div class="relative">
                        <form action="books.php" method="get">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search for books, authors, or genres..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                            <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-gray-400">
                                <i class="ri-search-line"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="account.php" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary">
                        <i class="ri-user-line text-xl"></i>
                    </a>
                    <div class="relative">
                        <a href="cart.php" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary" id="cartBtn">
                            <i class="ri-shopping-cart-line text-xl"></i>
                        </a>
                        <span class="absolute -top-1 -right-1 w-5 h-5 flex items-center justify-center bg-primary text-white text-xs rounded-full"><?php echo $cartCount; ?></span>
                    </div>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium hidden md:inline">Login</a>
                        <a href="register.php" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md hidden md:inline">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="md:hidden hidden bg-white shadow-md absolute w-full top-16 left-0">
            <form action="books.php" method="get" class="px-4 py-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search for books, authors, or genres..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-gray-400">
                    <i class="ri-search-line"></i>
                </button>
            </form>
            <a href="index.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Home</a>
            <a href="books.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Books</a>
            <a href="cart.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Cart</a>
            <a href="#" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Requests</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">
                    <i class="ri-user-line mr-2"></i> Account
                </a>
                <a href="?logout=true" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Logout</a>
            <?php else: ?>
                <a href="login.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Login</a>
                <a href="register.php" class="block bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 rounded-md mx-4 my-2">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-16 max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="w-full md:w-64 flex-shrink-0 md:sticky md:top-24">
                <div class="bg-white rounded-lg shadow-md border p-4">
                    <h3 class="font-semibold mb-4 text-gray-800">Filters</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Categories</h4>
                            <form action="books.php" method="get" class="space-y-2">
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                                <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                                <label class="flex items-center">
                                    <input type="checkbox" name="categories[]" value="1" <?php echo in_array('1', $selectedCategories) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">Fiction</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="categories[]" value="2" <?php echo in_array('2', $selectedCategories) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">Non-Fiction</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="categories[]" value="3" <?php echo in_array('3', $selectedCategories) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">Children's Books</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="categories[]" value="4" <?php echo in_array('4', $selectedCategories) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">Academic</span>
                                </label>
                                <button type="submit" class="mt-4 w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Apply Filters</button>
                            </form>
                        </div>

                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Price Range</h4>
                            <form action="books.php" method="get" class="space-y-3">
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <input type="hidden" name="categories" value="<?php echo implode(',', $selectedCategories); ?>">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Min Price</label>
                                    <input type="number" name="min_price" value="<?php echo $minPrice; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" placeholder="0">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Max Price</label>
                                    <input type="number" name="max_price" value="<?php echo $maxPrice; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary" placeholder="100">
                                </div>
                                <button type="submit" class="mt-4 w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Apply Price</button>
                            </form>
                        </div>

                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Rating</h4>
                            <div class="space-y-2">
                                <form action="books.php" method="get" class="space-y-2">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                    <input type="hidden" name="categories" value="<?php echo implode(',', $selectedCategories); ?>">
                                    <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                                    <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="rating" value="4" <?php echo $ratingFilter >= 4 ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                        <span class="ml-2 flex items-center text-sm text-gray-600">
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                        </span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="rating" value="3" <?php echo $ratingFilter >= 3 ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                        <span class="ml-2 flex items-center text-sm text-gray-600">
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-fill text-yellow-400"></i>
                                            <i class="ri-star-line text-gray-300"></i>
                                        </span>
                                    </label>
                                    <button type="submit" class="mt-2 w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Apply Rating</button>
                                </form>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Format</h4>
                            <form action="books.php" method="get" class="space-y-2">
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <input type="hidden" name="categories" value="<?php echo implode(',', $selectedCategories); ?>">
                                <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                                <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                                <input type="hidden" name="rating" value="<?php echo $ratingFilter; ?>">
                                <label class="flex items-center">
                                    <input type="checkbox" name="format[]" value="hardcover" class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">Hardcover</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="format[]" value="paperback" class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">Paperback</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="format[]" value="ebook" class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600">E-Book</span>
                                </label>
                                <button type="submit" class="mt-4 w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition duration-200">Apply Format</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Books Grid -->
            <div class="flex-1">
                <section class="mb-12">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Bestsellers</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php 
                        $bestsellers = array_slice($books, 0, 4); // Assuming first 4 are bestsellers
                        foreach ($bestsellers as $book): ?>
                            <div class="group">
                                <div class="relative aspect-[3/4] rounded-lg overflow-hidden mb-3">
                                    <img src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center overlay">
                                        <button class="bg-white text-gray-900 px-4 py-2 rounded-button text-sm font-medium" onclick="showQuickView('<?php echo htmlspecialchars($book['title']); ?>', '<?php echo htmlspecialchars($book['author']); ?>', '$<?php echo number_format($book['price'], 2); ?>')">Quick View</button>
                                    </div>
                                </div>
                                <h3 class="font-medium mb-1 text-gray-800"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-1">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="flex items-center mb-2">
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-half-fill text-yellow-400"></i>
                                    <span class="text-sm text-gray-600 ml-1">(124)</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-800">$<?php echo number_format($book['price'], 2); ?></span>
                                    <form action="books.php" method="get" class="inline">
                                        <input type="hidden" name="add_to_cart" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        <input type="hidden" name="categories" value="<?php echo implode(',', $selectedCategories); ?>">
                                        <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                                        <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                                        <button type="submit" class="text-primary hover:text-primary/80">
                                            <i class="ri-shopping-cart-line text-xl"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="mb-12">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800">New Arrivals</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php 
                        $newArrivals = array_slice($books, 4, 4); // Assuming next 4 are new arrivals
                        foreach ($newArrivals as $book): ?>
                            <div class="group">
                                <div class="relative aspect-[3/4] rounded-lg overflow-hidden mb-3">
                                    <img src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-full object-cover">
                                    <div class="absolute top-2 right-2 bg-primary text-white px-2 py-1 text-xs rounded-full">New</div>
                                    <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center overlay">
                                        <button class="bg-white text-gray-900 px-4 py-2 rounded-button text-sm font-medium" onclick="showQuickView('<?php echo htmlspecialchars($book['title']); ?>', '<?php echo htmlspecialchars($book['author']); ?>', '$<?php echo number_format($book['price'], 2); ?>')">Quick View</button>
                                    </div>
                                </div>
                                <h3 class="font-medium mb-1 text-gray-800"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-1">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="flex items-center mb-2">
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-fill text-yellow-400"></i>
                                    <i class="ri-star-line text-gray-300"></i>
                                    <span class="text-sm text-gray-600 ml-1">(42)</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-800">$<?php echo number_format($book['price'], 2); ?></span>
                                    <form action="books.php" method="get" class="inline">
                                        <input type="hidden" name="add_to_cart" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        <input type="hidden" name="categories" value="<?php echo implode(',', $selectedCategories); ?>">
                                        <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                                        <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                                        <button type="submit" class="text-primary hover:text-primary/80">
                                            <i class="ri-shopping-cart-line text-xl"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($newArrivals) && empty($bestsellers)): ?>
                            <p class="col-span-full text-center text-gray-500 text-lg py-8">No books found matching your criteria.</p>
                        <?php endif; ?>
                    </div>
                </section>
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

        // Quick View Modal
        function showQuickView(title, author, price) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800">${title}</h3>
                        <button class="text-gray-500 hover:text-gray-700" onclick="this.closest('.fixed').remove()">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                    <div class="mb-4">
                        <p class="text-gray-600">${author}</p>
                        <p class="font-medium mt-2 text-gray-800">${price}</p>
                    </div>
                    <p class="text-gray-600 mb-4">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                    <form action="books.php" method="get" class="inline">
                        <input type="hidden" name="add_to_cart" value="<?php echo $book['id']; ?>">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <input type="hidden" name="categories" value="<?php echo implode(',', $selectedCategories); ?>">
                        <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                        <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                        <button type="submit" class="w-full bg-primary text-white py-2 rounded-button hover:bg-primary/90 transition duration-200">Add to Cart</button>
                    </form>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
    </script>

</body>
</html>