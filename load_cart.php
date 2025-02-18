<?php
include 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Fetch cart items along with book and category details
    $cart_query = "
        SELECT 
            c.quantity, 
            b.id, 
            b.title, 
            b.author, 
            b.price, 
            b.image_url, 
            ca.name as category_name
        FROM 
            cart c
        JOIN 
            books b ON c.book_id = b.id
        JOIN 
            categories ca ON b.category_id = ca.id
        WHERE 
            c.user_id = ?";

    // Prepare and execute query
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Store cart items in an array
    $cart_items = [];
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
    }

    // Return cart items as JSON response
    echo json_encode($cart_items);
} else {
    // No user is logged in, return empty array
    echo json_encode([]);
}
?>
