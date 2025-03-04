INSERT INTO categories (name, description, book_count) VALUES 
('Fiction', 'A collection of fictional books including novels and short stories.', 10),
('Science', 'Books based on various scientific fields including physics, biology, and chemistry.', 8),
('History', 'Books that cover different historical events and figures.', 5),
('Technology', 'Books related to the development and use of technology.', 7);

INSERT INTO books (title, author, price, book_condition, image_url, category_id) VALUES
('The Great Adventure', 'John Doe', 19.99, 'New', 'https://img.freepik.com/free-photo/open-book-on-yellow-background-education-or-bookstore-concept-3d-rendering_1150-5223.jpg', 1),
('Science Explained', 'Jane Smith', 24.99, 'Used', 'https://img.freepik.com/free-photo/red-hardcover-book-front-cover_1150-4241.jpg', 2),
('World War II History', 'Sarah Johnson', 14.99, 'New', 'https://img.freepik.com/free-photo/girl-flipping-page_1150-5242.jpg', 3),
('The Future of AI', 'James Lee', 29.99, 'Like New', 'https://img.freepik.com/free-photo/open-book-with-bookmark-bestseller_1150-5223.jpg', 4);

INSERT INTO categories (name, description, book_count) VALUES 
('Fantasy', 'Books featuring imaginative worlds, magic, and mythical creatures.', 12),
('Biography', 'True stories about the lives of notable individuals.', 6),
('Mystery', 'Books centered around solving crimes or uncovering secrets.', 9),
('Self-Help', 'Guides and advice for personal growth and improvement.', 4),
('Cooking', 'Books with recipes, cooking techniques, and culinary inspiration.', 3),
('Travel', 'Books exploring destinations, cultures, and travel experiences.', 5);