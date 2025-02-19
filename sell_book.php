<?php
session_start();
include('config.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = ""; // To display success or error messages

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

// Handle book submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $price = floatval($_POST['price']);
    $book_condition = trim($_POST['book_condition']);
    $category_id = intval($_POST['category_id']);
    $user_id = $_SESSION['user_id'];

    // Handle image upload
    $image_url = "";
    if (isset($_FILES["book_image"]) && $_FILES["book_image"]["error"] == 0) {
        $target_dir = "uploads/";
        $image_name = basename($_FILES["book_image"]["name"]);
        $target_file = $target_dir . time() . "_" . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ["jpg", "jpeg", "png", "gif"];

        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES["book_image"]["tmp_name"], $target_file)) {
                $image_url = $target_file;
            } else {
                $message = "Error uploading image.";
            }
        } else {
            $message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }

    if (!empty($title) && !empty($author) && $price > 0 && !empty($book_condition) && $category_id > 0) {
        $stmt = $conn->prepare("INSERT INTO books (title, author, price, book_condition, image_url, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdssi", $title, $author, $price, $book_condition, $image_url, $category_id);

        if ($stmt->execute()) {
            $message = "Book listed successfully!";
        } else {
            $message = "Error listing book.";
        }
        $stmt->close();
    } else {
        $message = "All fields are required!";
    }
}

// Fetch categories for dropdown
$categories = [];
$result = $conn->query("SELECT id, name FROM categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell a Book</title>
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
</head>
<body class="bg-gray-100 min-h-screen">
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

    <!-- Main Content with Padding to Avoid Overlap with Fixed Navbar -->
    <div class="pt-20 max-w-4xl mx-auto p-6 bg-white shadow-md rounded-md">
        <h2 class="text-2xl font-bold mb-4">List Your Book for Sale</h2>
        
        <?php if (!empty($message)): ?>
            <div class="p-3 mb-4 text-white bg-blue-500 rounded">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="sell_book.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block font-medium">Book Title</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border rounded-md">
            </div>

            <div>
                <label class="block font-medium">Author</label>
                <input type="text" name="author" required class="w-full px-3 py-2 border rounded-md">
            </div>

            <div>
                <label class="block font-medium">Price ($)</label>
                <input type="number" step="0.01" name="price" required class="w-full px-3 py-2 border rounded-md">
            </div>

            <div>
                <label class="block font-medium">Condition</label>
                <select name="book_condition" required class="w-full px-3 py-2 border rounded-md">
                    <option value="New">New</option>
                    <option value="Like New">Like New</option>
                    <option value="Used - Good">Used - Good</option>
                    <option value="Used - Acceptable">Used - Acceptable</option>
                </select>
            </div>

            <div>
                <label class="block font-medium">Category</label>
                <select name="category_id" required class="w-full px-3 py-2 border rounded-md">
                    <option value="">Select a Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-medium">Upload Image</label>
                <input type="file" name="book_image" accept="image/*" required class="w-full px-3 py-2 border rounded-md">
            </div>

            <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">List Book</button>
        </form>
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
    </script>
</body>
</html>