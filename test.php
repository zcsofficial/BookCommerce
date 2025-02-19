<?php
session_start();
include('config.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = ""; // To display success or error messages

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
<nav class="bg-white shadow-md fixed top-0 left-0 w-full z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-6">
                <a href="index.php" class="font-['Pacifico'] text-2xl text-primary">BookCommerce</a>
                <div class="hidden md:flex space-x-6">
                    <a href="index.php" class="text-gray-900 hover:text-primary text-sm font-medium">Home</a>
                    <a href="books.php" class="text-gray-900 hover:text-primary text-sm font-medium">Books</a>
                    <a href="cart.php" class="text-gray-900 hover:text-primary text-sm font-medium">Cart</a>
                    <a href="#" class="text-gray-900 hover:text-primary text-sm font-medium">Requests</a>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-900 font-medium"><?php echo $_SESSION['user_full_name'] ?? 'User'; ?></span>
                        <a href="account.php" class="text-gray-900 hover:text-primary">
                            <i class="ri-user-line text-xl"></i>
                        </a>
                        <a href="logout.php" class="text-gray-900 hover:text-primary">Logout</a>
                    </div>
                    <div class="relative">
                        <a href="cart.php" class="text-gray-900 hover:text-primary">
                            <i class="ri-shopping-cart-line text-xl"></i>
                            <span class="absolute top-0 right-0 bg-primary text-white text-xs px-2 py-1 rounded-full">
                                <?php echo $cart_count ?? 0; ?>
                            </span>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-gray-900 hover:text-primary">Login</a>
                    <a href="register.php" class="bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-4xl mx-auto p-6 bg-white shadow-md rounded-md mt-20">
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

        <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">List Book</button>
    </form>
</div>
</body>
</html>
