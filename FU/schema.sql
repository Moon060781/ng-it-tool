-- Table structure for user_locations to support Leaflet map points
CREATE TABLE IF NOT EXISTS user_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(100),
    location_title VARCHAR(255),
    -- Using DECIMAL for high precision coordinates
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
