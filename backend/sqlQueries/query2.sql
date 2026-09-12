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