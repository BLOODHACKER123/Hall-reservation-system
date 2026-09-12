-- ---------------------------------------------------------
-- DATABASE CREATION
-- ---------------------------------------------------------
CREATE DATABASE IF NOT EXISTS hall_reservation_system;
USE hall_reservation_system;

-- ---------------------------------------------------------
-- 1. SECURITY & IDENTITY (BASE INHERITANCE)
-- ---------------------------------------------------------

-- 1. User Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('Customer', 'Vendor', 'Admin') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    password_reset_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Customer Table (Subclass)
CREATE TABLE customers (
    user_id INT PRIMARY KEY,
    preference TEXT NULL,
    booking_count INT DEFAULT 0,
    loyalty_points INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 3. Vendor Table (Subclass)
CREATE TABLE vendors (
    user_id INT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL,
    business_address TEXT NOT NULL,
    verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 4. Admin Table (Subclass)
CREATE TABLE admins (
    user_id INT PRIMARY KEY,
    admin_level INT NOT NULL,
    role VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 5. Audit_Log Table
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 2. HALL MANAGEMENT
-- ---------------------------------------------------------

-- 6. Hall Table
CREATE TABLE halls (
    hall_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    district VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    environment_type ENUM('indoor', 'open_garden', 'mixed') NOT NULL,
    base_price_per_hour DECIMAL(10,2) NOT NULL,
    weekend_price_per_hour DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL,
    cancellation_policy ENUM('flexible', 'moderate', 'strict') NOT NULL,
    buffer_time_minutes INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(user_id) ON DELETE CASCADE
);

-- 7. Hall_Images Table
CREATE TABLE hall_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    hall_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    caption VARCHAR(100) NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id) ON DELETE CASCADE
);

-- 8. Facility Table
CREATE TABLE facilities (
    facility_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon_url VARCHAR(255) NULL
);

-- 9. Hall_Facility Table (Junction)
CREATE TABLE hall_facilities (
    hall_id INT NOT NULL,
    facility_id INT NOT NULL,
    PRIMARY KEY (hall_id, facility_id),
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id) ON DELETE CASCADE,
    FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 3. BOOKINGS & PROMOTIONS
-- ---------------------------------------------------------

-- 10. Reservation Table
CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    hall_id INT NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Cancelled') DEFAULT 'Pending',
    locked_price_per_hour DECIMAL(10,2) NOT NULL,
    total_booking_amount DECIMAL(10,2) NOT NULL,
    guest_count INT NOT NULL,
    special_requests TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(user_id),
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id)
);

-- 11. Promotion Table
CREATE TABLE promotions (
    promo_id INT AUTO_INCREMENT PRIMARY KEY,
    hall_id INT NOT NULL,
    promo_code VARCHAR(50) UNIQUE NOT NULL,
    discount_rate DECIMAL(5,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Active', 'Draft', 'Expired') DEFAULT 'Draft',
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id) ON DELETE CASCADE
);

-- 12. Wishlist Table (Junction)
CREATE TABLE wishlists (
    customer_id INT NOT NULL,
    hall_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, hall_id),
    FOREIGN KEY (customer_id) REFERENCES customers(user_id) ON DELETE CASCADE,
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 4. TRANSACTIONS & COMMUNICATION
-- ---------------------------------------------------------

-- 13. Payment Base Table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('advance', 'balance', 'security_deposit', 'refund') NOT NULL,
    payment_method ENUM('card', 'bank_transfer', 'cash') NOT NULL,
    status ENUM('Success', 'Failed', 'Pending') DEFAULT 'Pending',
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
);

-- 14. Payment_Cash Table (Subclass)
CREATE TABLE payment_cash (
    payment_id INT PRIMARY KEY,
    receipt_number VARCHAR(100) UNIQUE NOT NULL,
    collected_by VARCHAR(100) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
);

-- 15. Payment_Card Table (Subclass)
CREATE TABLE payment_card (
    payment_id INT PRIMARY KEY,
    transaction_reference VARCHAR(255) UNIQUE NOT NULL,
    card_last4 VARCHAR(4) NOT NULL,
    card_type VARCHAR(50) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
);

-- 16. Payment_Online Table (Subclass)
CREATE TABLE payment_online (
    payment_id INT PRIMARY KEY,
    transaction_reference VARCHAR(255) UNIQUE NOT NULL,
    gateway_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
);

-- 17. Message Table
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    hall_id INT NOT NULL,
    content TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id),
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id)
);

-- 18. Review Table
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reservation_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(user_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
);

-- 19. Notification Table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 5. SAMPLE DATA INSERTION
-- ---------------------------------------------------------

-- Insert Base Users
INSERT INTO users (first_name, last_name, email, phone, password, user_type) VALUES
('Jane', 'Doe', 'jane.customer@email.com', '+94771234567', '$2y$10$SampleBcryptHashHere', 'Customer'),
('John', 'Smith', 'vendor@grandhalls.com', '+94719876543', '$2y$10$SampleBcryptHashHere', 'Vendor'),
('Admin', 'Super', 'admin@system.com', '+94701112223', '$2y$10$SampleBcryptHashHere', 'Admin');

-- Insert Subclass Profiles
INSERT INTO customers (user_id, preference, booking_count, loyalty_points) VALUES 
(1, 'Vegetarian catering preferred', 1, 100);

INSERT INTO vendors (user_id, business_name, business_address, verification_status) VALUES 
(2, 'Grand Halls Ltd', '123 Main St, Colombo', 'Verified');

INSERT INTO admins (user_id, admin_level, role) VALUES 
(3, 1, 'Super Admin');

-- Insert Hall Data
INSERT INTO halls (vendor_id, name, description, district, address, capacity, environment_type, base_price_per_hour, weekend_price_per_hour, security_deposit, cancellation_policy, buffer_time_minutes) VALUES
(2, 'Crystal Banquet Hall', 'A luxury indoor venue for weddings and corporate events.', 'Colombo', '45 Central Avenue', 500, 'indoor', 15000.00, 20000.00, 50000.00, 'strict', 120);

INSERT INTO hall_images (hall_id, image_url, caption, is_primary) VALUES
(1, 'https://storage.com/crystal_main.jpg', 'Main Stage', TRUE);

-- Insert Facilities
INSERT INTO facilities (name, icon_url) VALUES 
('WiFi', '/icons/wifi.png'),
('Catering', '/icons/food.png');

INSERT INTO hall_facilities (hall_id, facility_id) VALUES 
(1, 1), (1, 2);

-- Insert Reservation
INSERT INTO reservations (customer_id, hall_id, start_datetime, end_datetime, status, locked_price_per_hour, total_booking_amount, guest_count, special_requests) VALUES
(1, 1, '2026-10-15 18:00:00', '2026-10-15 23:00:00', 'Confirmed', 15000.00, 75000.00, 400, 'Need early access for decoration');

-- Insert Payment
INSERT INTO payments (reservation_id, amount, payment_type, payment_method, status, paid_at) VALUES
(1, 75000.00, 'advance', 'card', 'Success', CURRENT_TIMESTAMP);

INSERT INTO payment_card (payment_id, transaction_reference, card_last4, card_type) VALUES
(1, 'TXN-987654321', '4242', 'Visa');

-- Insert Communication & Reviews
INSERT INTO messages (sender_id, receiver_id, hall_id, content) VALUES
(1, 2, 1, 'Is the projector included in the base price?');

INSERT INTO reviews (customer_id, reservation_id, rating, comment) VALUES
(1, 1, 5, 'Beautiful hall with excellent management.');