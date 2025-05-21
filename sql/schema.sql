-- Create the database
CREATE DATABASE IF NOT EXISTS pos_system;
USE pos_system;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier') DEFAULT 'cashier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reset_token VARCHAR(255) DEFAULT NULL,    -- Added for forgot password
    reset_expires DATETIME DEFAULT NULL,      -- Added for forgot password expiration
    profile_image VARCHAR(255) DEFAULT NULL
);

-- Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers table
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_info VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table (with cost_price, supplier_id, and description)
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    category_id INT,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL,
    cost_price DECIMAL(10, 2) DEFAULT 0.00,
    supplier_id INT,
    description TEXT,
    image VARCHAR(255) DEFAULT 'default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
);

-- Orders table
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(100),
    address TEXT,
    phone_no VARCHAR(20),
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'credit_card', 'mobile_payment') DEFAULT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Order Items table
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Insert sample data for users
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@laptopsstore.com', '$2a$12$500LFI5QtLyYUAh87rh9EOQWdFPzXUvMWKprbDk17ICPaIKjim25i', 'admin'),
('cashier1', 'cashier1@laptopsstore.com', '$2a$12$6cV7Me1dsfWyxHOuPuBvW.cXg8fnwSUMffLN3pVFOxTGFoejK3YNq', 'cashier');

-- Insert sample data for categories
INSERT INTO categories (category_name) VALUES 
('Laptops'),
('Accessories');

-- Insert sample data for suppliers
INSERT INTO suppliers (supplier_name, contact_info) VALUES 
('Dell Supplier', 'dell@supplier.com'),
('Apple Supplier', 'apple@supplier.com');

-- Insert sample data for products (removed duplicates)
INSERT INTO products (product_name, category_id, price, stock_quantity, cost_price, supplier_id, description, image) VALUES 
('Dell XPS 13', 1, 1200.00, 10, 960.00, 1, 'A high-performance laptop with 13-inch display and 512GB SSD.', 'dell_xps.jpg'),
('MacBook Pro', 1, 1500.00, 8, 1125.00, 2, 'A premium laptop with M1 chip and 16GB RAM.', 'macbook_pro.jpg'),
('Mouse', 2, 20.00, 50, 18.00, 1, 'A wired optical mouse with ergonomic design.', 'mouse.jpg'),
('HP Pavilion 15', 1, 900.00, 15, 720.00, 1, 'A powerful laptop for everyday use with 16GB RAM and 512GB SSD.', 'hp_pavilion.jpg'),
('Logitech Keyboard', 2, 30.00, 40, 27.00, 1, 'A wireless keyboard with long battery life.', 'logitech_keyboard.jpg'),
('Samsung Monitor', 2, 150.00, 20, 135.00, 2, 'A 24-inch LED monitor with Full HD resolution.', 'samsung_monitor.jpg'),
('Lenovo ThinkPad X1', 1, 1400.00, 5, 1120.00, 1, 'A premium business laptop with 14-inch display and 1TB SSD.', 'thinkpad_x1.jpg'),
('USB-C Hub', 2, 25.00, 60, 22.50, 2, 'A multi-port USB-C hub for connecting multiple devices.', 'usb_c_hub.jpg');