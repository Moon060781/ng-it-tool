-- Create fu_users table for user registration & login
CREATE TABLE IF NOT EXISTS fu_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create fu_earn table for profit/earnings from rides
CREATE TABLE IF NOT EXISTS fu_earn (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ride_date DATE,
    ride_time TIME,
    route_desc VARCHAR(500),
    total_fare DECIMAL(10, 2),
    bonus_amount DECIMAL(10, 2),
    commission_amount DECIMAL(10, 2),
    fuel_efficiency DECIMAL(8, 4),
    petrol_price DECIMAL(8, 2),
    distance_km DECIMAL(10, 2),
    fuel_cost DECIMAL(10, 2),
    net_profit DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES fu_users(id) ON DELETE CASCADE
);

-- Create fu_expense table for fuel/expense details with map points
CREATE TABLE IF NOT EXISTS fu_expense (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    expense_date DATE,
    expense_time TIME,
    description VARCHAR(500),
    amount_spent DECIMAL(10, 2),
    petrol_rate DECIMAL(8, 2),
    fuel_vol_liters DECIMAL(10, 4),
    map_distance_km DECIMAL(10, 2),
    efficiency_l_km DECIMAL(8, 4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES fu_users(id) ON DELETE CASCADE
);

-- Create user_locations table for map points
CREATE TABLE IF NOT EXISTS user_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    location_title VARCHAR(255),
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES fu_users(id) ON DELETE CASCADE
);

-- Create indexes for better query performance
CREATE INDEX idx_fu_earn_user ON fu_earn(user_id);
CREATE INDEX idx_fu_expense_user ON fu_expense(user_id);
CREATE INDEX idx_user_locations_user ON user_locations(user_id);
