CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,   -- Unique ID for each user
    fullname VARCHAR(255) NOT NULL,       -- Full name of the user
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',  -- Default role is 'user'
    email VARCHAR(255) NOT NULL UNIQUE,   -- Email address of the user (must be unique)
    password VARCHAR(255) NOT NULL,       -- Encrypted password
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Date and time of user registration
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- Date and time of last update
);


-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    book_count INT DEFAULT 0
);

CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    price DECIMAL(10, 2),
    book_condition VARCHAR(100),  -- Changed to book_condition
    image_url TEXT,
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,      -- Unique order ID
    user_id INT NOT NULL,                   -- Foreign key to users table
    total_amount DECIMAL(10, 2) NOT NULL,   -- Total order amount
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',  -- Order status
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Date and time of order placement
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Date and time of last update
    FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,    -- Unique ID for the order item
    order_id INT NOT NULL,                 -- Foreign key to orders table
    book_id INT NOT NULL,                  -- Foreign key to books table
    quantity INT DEFAULT 1,                -- Quantity of the book in the order
    price DECIMAL(10, 2) NOT NULL,         -- Price of the book at the time of order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,  -- Ensures that when an order is deleted, its items are deleted as well
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE   -- Ensures that when a book is deleted, related order items are deleted
);
CREATE TABLE order_shipping_address (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Unique ID for the shipping address
    order_id INT NOT NULL,                 -- Foreign key to the orders table
    address_line1 VARCHAR(255) NOT NULL,   -- First line of the address
    address_line2 VARCHAR(255),            -- Second line of the address (optional)
    city VARCHAR(100) NOT NULL,            -- City
    state VARCHAR(100),                    -- State
    postal_code VARCHAR(20) NOT NULL,      -- Postal code
    country VARCHAR(100) NOT NULL,         -- Country
    phone VARCHAR(20),                     -- Phone number (optional)
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE TABLE ratings (
    book_id INT,
    rating DECIMAL(3, 2),
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (book_id, user_id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);

-- Add for_exchange column to books table (if not already present)
ALTER TABLE books
ADD COLUMN for_exchange BOOLEAN DEFAULT FALSE;

-- Create exchange_requests table to store exchange requests
CREATE TABLE exchange_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,              -- Unique ID for each exchange request
    user_id INT NOT NULL,                          -- User offering the book
    offered_book_id INT NOT NULL,                  -- Book being offered for exchange
    requested_book_id INT NOT NULL,                -- Book being requested in exchange
    status ENUM('pending', 'accepted', 'rejected', 'completed') DEFAULT 'pending',  -- Status of the exchange
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Date and time of request
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,      -- Date and time of last update
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (offered_book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_book_id) REFERENCES books(id) ON DELETE CASCADE
);
ALTER TABLE books
ADD COLUMN user_id INT,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;