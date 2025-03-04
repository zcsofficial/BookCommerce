<?php
include('config.php');

// Function to fetch books with search, filter, and sorting functionality
function getBooks($conn, $searchTerm = '', $selectedCategories = [], $priceRange = [0, 100], $ratingFilter = null, $sortBy = 'b.title', $sortOrder = 'ASC') {
    // Base SQL query
    $sql = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, c.name AS category_name, 
                   COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.rating) AS rating_count
            FROM books b
            LEFT JOIN categories c ON b.category_id = c.id
            LEFT JOIN ratings r ON b.id = r.book_id";

    $params = [];
    $types = "";
    $whereConditions = [];

    // Search term filter
    if ($searchTerm) {
        $whereConditions[] = "b.title LIKE ?";
        $params[] = "%$searchTerm%";
        $types .= "s";
    }

    // Category filter
    if (!empty($selectedCategories)) {
        $placeholders = implode(',', array_fill(0, count($selectedCategories), '?'));
        $whereConditions[] = "b.category_id IN ($placeholders)";
        $types .= str_repeat('i', count($selectedCategories));
        $params = array_merge($params, $selectedCategories);
    }

    // Price range filter
    $whereConditions[] = "b.price BETWEEN ? AND ?";
    $types .= "dd";
    $params[] = $priceRange[0];
    $params[] = $priceRange[1];

    // Combine WHERE conditions
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }

    // Group by book to calculate average rating
    $sql .= " GROUP BY b.id, b.title, b.author, b.price, b.book_condition, b.image_url, c.name";

    // Rating filter using HAVING
    if ($ratingFilter !== null) {
        $sql .= " HAVING COALESCE(AVG(r.rating), 0) >= ?";
        $types .= "d";
        $params[] = $ratingFilter;
    }

    // Add sorting
    $validSortColumns = ['b.title' => 'b.title', 'b.price' => 'b.price', 'avg_rating' => 'avg_rating'];
    $sortBy = $validSortColumns[$sortBy] ?? 'b.title';
    $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
    $sql .= " ORDER BY $sortBy $sortOrder";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    return $books;
}

// Function to fetch all categories
function getCategories($conn) {
    $sql = "SELECT id, name FROM categories";
    $result = $conn->query($sql);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

// Handle search, filters, and sorting
session_start();
$searchTerm = $_GET['q'] ?? '';
// Fix: Handle categories as an array directly from $_GET
$selectedCategories = isset($_GET['categories']) && is_array($_GET['categories']) ? array_map('intval', $_GET['categories']) : [];
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 100;
$ratingFilter = isset($_GET['rating']) && $_GET['rating'] !== '' ? floatval($_GET['rating']) : null;
$sortBy = $_GET['sort_by'] ?? 'b.title';
$sortOrder = $_GET['sort_order'] ?? 'ASC';

// Fetch books and categories
$books = getBooks($conn, $searchTerm, $selectedCategories, [$minPrice, $maxPrice], $ratingFilter, $sortBy, $sortOrder);
$categories = getCategories($conn);

// Add to cart functionality
if (isset($_GET['add_to_cart']) && isset($_SESSION['user_id'])) {
    $book_id = intval($_GET['add_to_cart']);
    $user_id = $_SESSION['user_id'];

    $checkCart = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND book_id = ?");
    $checkCart->bind_param("ii", $user_id, $book_id);
    $checkCart->execute();
    $result = $checkCart->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $updateCart = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND book_id = ?");
        $newQuantity = $row['quantity'] + 1;
        $updateCart->bind_param("iii", $newQuantity, $user_id, $book_id);
        $updateCart->execute();
    } else {
        $insertCart = $conn->prepare("INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, 1)");
        $insertCart->bind_param("ii", $user_id, $book_id);
        $insertCart->execute();
    }

    header("Location: books.php?" . http_build_query($_GET));
    exit();
}

// Get cart count
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $cartQuery = $conn->prepare("SELECT SUM(quantity) AS cart_count FROM cart WHERE user_id = ?");
    $cartQuery->bind_param("i", $user_id);
    $cartQuery->execute();
    $cartResult = $cartQuery->get_result();
    $cartCount = $cartResult->fetch_assoc()['cart_count'] ?? 0;
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
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            background: #57b5e7;
            border-radius: 50%;
            cursor: pointer;
        }
        .group:hover .overlay { opacity: 1; }
        .rounded-button { border-radius: 8px; }
    </style>
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
                    <form action="books.php" method="get">
                        <div class="relative">
                            <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search books..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary">
                            <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="account.php" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary">
                        <i class="ri-user-line text-xl"></i>
                    </a>
                    <div class="relative">
                        <a href="cart.php" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary">
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
            <div class="px-4 py-2">
                <form action="books.php" method="get">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search books..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary">
                    <button type="submit" class="absolute right-8 top-4 text-gray-400"><i class="ri-search-line"></i></button>
                </form>
            </div>
            <a href="index.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Home</a>
            <a href="books.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Books</a>
            <a href="cart.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Cart</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="account.php" class="block text-gray-900 hover:text-primary px-4 py-2 text-sm font-medium border-b">Account</a>
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
                    <form action="books.php" method="get" class="space-y-4">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <!-- Categories -->
                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Categories</h4>
                            <?php foreach ($categories as $category): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $selectedCategories) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-600"><?php echo htmlspecialchars($category['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!-- Price Range -->
                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Price Range</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Min Price</label>
                                    <input type="number" name="min_price" value="<?php echo $minPrice; ?>" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary" min="0" step="0.01">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Max Price</label>
                                    <input type="number" name="max_price" value="<?php echo $maxPrice; ?>" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <!-- Rating -->
                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Minimum Rating</h4>
                            <label class="flex items-center">
                                <input type="radio" name="rating" value="" <?php echo $ratingFilter === null ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-600">Any</span>
                            </label>
                            <?php for ($i = 4; $i >= 1; $i--): ?>
                                <label class="flex items-center">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $ratingFilter == $i ? 'checked' : ''; ?> class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span class="ml-2 flex items-center text-sm text-gray-600">
                                        <?php for ($j = 1; $j <= 5; $j++): ?>
                                            <i class="ri-star-<?php echo $j <= $i ? 'fill' : 'line'; ?> text-yellow-400"></i>
                                        <?php endfor; ?>
                                    </span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <!-- Sorting -->
                        <div>
                            <h4 class="font-medium mb-2 text-gray-700">Sort By</h4>
                            <select name="sort_by" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="b.title" <?php echo $sortBy === 'b.title' ? 'selected' : ''; ?>>Title</option>
                                <option value="b.price" <?php echo $sortBy === 'b.price' ? 'selected' : ''; ?>>Price</option>
                                <option value="avg_rating" <?php echo $sortBy === 'avg_rating' ? 'selected' : ''; ?>>Rating</option>
                            </select>
                            <select name="sort_order" class="w-full mt-2 px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full py-2 bg-primary text-white rounded-md hover:bg-primary/90">Apply Filters</button>
                    </form>
                </div>
            </div>

            <!-- Books Grid -->
            <div class="flex-1">
                <section class="mb-12">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Books</h2>
                    <?php if (empty($books)): ?>
                        <p class="text-center text-gray-500 text-lg py-8">No books found matching your criteria.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                            <?php foreach ($books as $book): ?>
                                <div class="group">
                                    <div class="relative aspect-[3/4] rounded-lg overflow-hidden mb-3">
                                        <img src="<?php echo htmlspecialchars($book['image_url'] ?: 'default_book.jpg'); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center overlay">
                                            <button class="bg-white text-gray-900 px-4 py-2 rounded-button text-sm font-medium" onclick="showQuickView('<?php echo htmlspecialchars($book['title']); ?>', '<?php echo htmlspecialchars($book['author']); ?>', '$<?php echo number_format($book['price'], 2); ?>')">Quick View</button>
                                        </div>
                                    </div>
                                    <h3 class="font-medium mb-1 text-gray-800"><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mb-1">by <?php echo htmlspecialchars($book['author'] ?: 'Unknown'); ?></p>
                                    <div class="flex items-center mb-2">
                                        <?php
                                        $avgRating = round($book['avg_rating'], 1);
                                        for ($i = 1; $i <= 5; $i++):
                                            $icon = $i <= $avgRating ? 'fill' : ($i - 0.5 <= $avgRating ? 'half-fill' : 'line');
                                        ?>
                                            <i class="ri-star-<?php echo $icon; ?> text-yellow-400"></i>
                                        <?php endfor; ?>
                                        <span class="text-sm text-gray-600 ml-1">(<?php echo $book['rating_count']; ?>)</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-800">$<?php echo number_format($book['price'], 2); ?></span>
                                        <form action="books.php" method="get" class="inline">
                                            <input type="hidden" name="add_to_cart" value="<?php echo $book['id']; ?>">
                                            <?php foreach ($_GET as $key => $value): if ($key !== 'add_to_cart'): ?>
                                                <?php if (is_array($value)): ?>
                                                    <?php foreach ($value as $cat): ?>
                                                        <input type="hidden" name="<?php echo $key; ?>[]" value="<?php echo htmlspecialchars($cat); ?>">
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                                <?php endif; ?>
                                            <?php endif; endforeach; ?>
                                            <button type="submit" class="text-primary hover:text-primary/80">
                                                <i class="ri-shopping-cart-line text-xl"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('mobileMenuButton').addEventListener('click', () => {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        document.querySelectorAll('#mobileMenu a, #mobileMenu form').forEach(el => {
            el.addEventListener('click', () => document.getElementById('mobileMenu').classList.add('hidden'));
            if (el.tagName === 'FORM') {
                el.addEventListener('submit', () => document.getElementById('mobileMenu').classList.add('hidden'));
            }
        });

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
                    <p class="text-gray-600 mb-4">Add a description from the database in a future update.</p>
                    <form action="books.php" method="get" class="inline">
                        <input type="hidden" name="add_to_cart" value="<?php echo $book['id']; ?>">
                        <?php foreach ($_GET as $key => $value): if ($key !== 'add_to_cart'): ?>
                            <?php if (is_array($value)): ?>
                                <?php foreach ($value as $cat): ?>
                                    <input type="hidden" name="${key}[]" value="${cat}">
                                <?php endforeach; ?>
                            <?php else: ?>
                                <input type="hidden" name="${key}" value="${value}">
                            <?php endif; ?>
                        <?php endif; endforeach; ?>
                        <button type="submit" class="w-full bg-primary text-white py-2 rounded-button hover:bg-primary/90 transition duration-200">Add to Cart</button>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }
    </script>
</body>
</html>