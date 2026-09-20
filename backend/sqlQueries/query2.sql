-- Add the venue_type column after environment_type
ALTER TABLE halls 
ADD COLUMN venue_type VARCHAR(100) NULL AFTER environment_type;

-- Update your existing sample hall to have a venue type
UPDATE halls 
SET venue_type = 'Banquet Hall' 
WHERE hall_id = 1;

-- (Optional) If you want to insert a new sample for a Wedding Venue
INSERT INTO halls (vendor_id, name, description, district, address, capacity, environment_type, venue_type, base_price_per_hour, weekend_price_per_hour, security_deposit, cancellation_policy, buffer_time_minutes) VALUES
(2, 'Sunset Garden Weddings', 'Beautiful open garden for weddings.', 'Galle', '12 Beach Road', 300, 'open_garden', 'Wedding Venue', 12000.00, 15000.00, 40000.00, 'flexible', 60);

-- Add an image for the new hall
INSERT INTO hall_images (hall_id, image_url, caption, is_primary) VALUES
(2, 'https://images.unsplash.com/photo-1519225421980-715cb0215aed', 'Garden View', TRUE);

CREATE TABLE hall_packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    hall_id INT NOT NULL,
    package_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    includes TEXT,
    FOREIGN KEY (hall_id) REFERENCES halls(hall_id) ON DELETE CASCADE
);


DROP TABLE IF EXISTS audit_logs;

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_role VARCHAR(50) DEFAULT 'Guest',
    action TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_user_role (user_role),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;