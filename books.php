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
    $cartCount = $cartRow['cart_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-white min-h-screen">
    <nav class="border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="#" class="text-2xl font-['Pacifico'] text-primary">BookCommerce</a>
                </div>
                
                <div class="flex-1 max-w-2xl mx-8">
                    <div class="relative">
                        <form action="books.php" method="get">
                            <input type="text" name="q" value="<?php echo $searchTerm; ?>" placeholder="Search for books, authors, or genres..." class="w-full px-4 py-2 text-sm border rounded-full focus:outline-none focus:border-primary">
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
                        <a href="cart.php" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-primary">
                            <i class="ri-shopping-cart-line text-xl"></i>
                        </a>
                        <span class="absolute -top-1 -right-1 w-5 h-5 flex items-center justify-center bg-primary text-white text-xs rounded-full"><?php echo $cartCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex gap-8">
            <div class="w-64 flex-shrink-0">
                <div class="sticky top-8">
                    <div class="bg-white rounded-lg shadow-sm border p-4">
                        <h3 class="font-semibold mb-4">Filters</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-medium mb-2">Categories</h4>
                                <div class="space-y-2">
                                    <form action="books.php" method="get">
                                        <input type="hidden" name="q" value="<?php echo $searchTerm; ?>">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="categories[]" value="1" <?php echo in_array('1', $selectedCategories) ? 'checked' : ''; ?>> Fiction
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="categories[]" value="2" <?php echo in_array('2', $selectedCategories) ? 'checked' : ''; ?>> Non-Fiction
                                        </label>
                                    </form>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-medium mb-2">Price Range</h4>
                                <form action="books.php" method="get">
                                    <input type="hidden" name="q" value="<?php echo $searchTerm; ?>">
                                    <input type="number" name="min_price" value="<?php echo $minPrice; ?>" class="w-full px-2 py-1 border rounded-md mb-2">
                                    <input type="number" name="max_price" value="<?php echo $maxPrice; ?>" class="w-full px-2 py-1 border rounded-md mb-4">
                                    <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-md">Apply</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($books as $book): ?>
                    <div class="bg-white shadow-sm rounded-lg">
                        <img src="<?php echo $book['image_url']; ?>" alt="<?php echo $book['title']; ?>" class="w-full h-56 object-cover rounded-t-lg">
                        <div class="p-4">
                            <h4 class="font-semibold"><?php echo $book['title']; ?></h4>
                            <p class="text-sm text-gray-600">by <?php echo $book['author']; ?></p>
                            <p class="text-primary font-semibold">$<?php echo number_format($book['price'], 2); ?></p>
                            <form action="books.php" method="get">
                                <input type="hidden" name="add_to_cart" value="<?php echo $book['id']; ?>">
                                <button type="submit" class="w-full mt-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark">Add to Cart</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
