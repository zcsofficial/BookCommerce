<?php
// forgot_password.php

include 'config.php'; // Database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    if (empty($email)) {
        $error_message = "Please enter your email address.";
    } else {
        // Check if the email exists in the database
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Send reset link (simplified version for this example)
            $reset_token = bin2hex(random_bytes(50)); // Generate a random token
            $reset_link = "https://yourwebsite.com/reset_password.php?token=$reset_token";

            // Store the token in the database with the email (in a real app, you'd also store an expiration time)
            $update_query = "UPDATE users SET reset_token = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ss", $reset_token, $email);
            $update_stmt->execute();

            // Send reset link to user's email (simplified for this example)
            mail($email, "Password Reset", "Click the following link to reset your password: $reset_link");

            $success_message = "Password reset link has been sent to your email.";
        } else {
            $error_message = "No account found with that email.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BookCommerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <!-- Navigation Bar (same as login.php) -->

    <main class="pt-16 pb-12 bg-gray-50">
        <section class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="w-full max-w-md mx-auto bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Forgot Password</h2>

                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-100 text-red-700 p-4 mb-4 rounded">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success_message)): ?>
                        <div class="bg-green-100 text-green-700 p-4 mb-4 rounded">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <form action="forgot_password.php" method="POST">
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Enter your email address</label>
                            <input type="email" id="email" name="email" required class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter your email">
                        </div>
                        <button type="submit" class="w-full px-6 py-3 bg-primary text-white font-medium text-lg rounded-md hover:bg-primary/90 transition duration-300">
                            Send Reset Link
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

</body>
</html>
