<?php
// register.php

include 'config.php'; // Database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the form data
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // The role is fixed as 'user' by default
    $role = 'user';
    
    // Validate the input data
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Check if the email already exists in the database
        $email_check_query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($email_check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "This email is already registered.";
        } else {
            // Hash the password before storing it in the database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert the user data into the database
            $insert_query = "INSERT INTO users (fullname, role, email, password) 
                             VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssss", $fullname, $role, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success_message = "Registration successful. You can now log in.";
            } else {
                $error_message = "Error in registration. Please try again.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BookCommerce</title>
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
        :where([class^="ri-"])::before { content: "\f3c2"; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Navigation Bar -->
    <nav class="bg-white shadow-sm fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="font-['Pacifico'] text-2xl text-primary">BookCommerce</a>
                    <div class="hidden md:flex space-x-8 ml-10">
                        <a href="index.php" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Home</a>
                        <a href="#" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Categories</a>
                        <a href="#" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Featured</a>
                        <a href="#" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium">Requests</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" id="loginBtn" class="text-gray-900 hover:text-primary px-3 py-2 text-sm font-medium !rounded-button">Login</a>
                    <a href="register.php" id="registerBtn" class="bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90 !rounded-button">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-16 pb-12 bg-gray-50">
        <section class="relative bg-white overflow-hidden">
            <div class="max-w-7xl mx-auto">
                <div class="relative z-10 pb-8 bg-white sm:pb-16 md:pb-20 lg:pb-28 xl:pb-32">
                    <div class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                        <div class="sm:text-center lg:text-left">
                            <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                                <span class="block">Create Your</span>
                                <span class="block text-primary">Book Marketplace Account</span>
                            </h1>
                            <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                                Join BookCommerce to buy, sell, and exchange books with other readers!
                            </p>
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
                <div class="w-full max-w-md mx-auto bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Create Your Account</h2>

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

                    <form action="register.php" method="POST">
                        <div class="mb-4">
                            <label for="fullname" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" id="fullname" name="fullname" required class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter your full name">
                        </div>

                        <!-- Role is fixed to 'user' in the backend and is no longer requested here -->
                        
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                            <input type="email" id="email" name="email" required class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter your email">
                        </div>

                        <div class="mb-6">
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" id="password" name="password" required class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter your password">
                        </div>

                        <div class="mb-6">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Confirm your password">
                        </div>

                        <button type="submit" class="w-full px-6 py-3 bg-primary text-white font-medium text-lg rounded-md hover:bg-primary/90 transition duration-300">
                            Register
                        </button>
                    </form>
                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-500">Already have an account? <a href="login.php" class="text-primary hover:text-primary/90">Login here</a></p>
                    </div>
                </div>
            </div>
        </section>
    </main>

</body>
</html>
