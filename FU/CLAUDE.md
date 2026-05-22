# NoorGee IT Tools - CLAUDE.md

## Project Introduction
This is an online tools platform: **it.noorgee.com**

### Available Tools (6 Tools)
| Tool | Link | Description |
|------|------|-------------|
| **Fuel Utility (FU)** | it.noorgee.com/FU | Fuel tracker for bike/car riding services |
| **Name Suggestion (NM)** | it.noorgee.com/NM | Name suggestions for newborn babies |
| **Task Wheel (TW)** | it.noorgee.com/TW | Random task assignment tool |
| **Prompt Maker (PM)** | it.noorgee.com/PM | AI-powered prompt enhancement tool |
| **Urdu Keyboard (UR)** | it.noorgee.com/UR | Online Urdu keyboard with different layouts |
| **News Writer (NW)** | it.noorgee.com/NW | News writer in Pakistani news channels style |

---

## Fuel Utility (FU) Tool Details

### Purpose
Fuel expense and profit calculator for online bike and car riding service providers.

### Tech Stack
| Technology | Usage |
|------------|-------|
| HTML5 | Structure |
| JavaScript (Vanilla) | Logic |
| Tailwind CSS (CDN) | Styling |
| FontAwesome (CDN) | Icons |
| Leaflet.js (CDN) | Map (OpenStreetMap) |
| PHP | Backend |
| MySQL | Database |

---

## Database Structure

### Database Name: `noorgeec_it`

### Table 1: `fu_users` (User Registration)
```sql
CREATE TABLE IF NOT EXISTS `fu_users` (
   `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
   `username` VARCHAR(50) NOT NULL UNIQUE,
   `password` VARCHAR(255) NOT NULL, -- Hashed password
   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table 2: `fu_expense` (Fuel Expense)
```sql
CREATE TABLE IF NOT EXISTS `fu_expense` (
   `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
   `user_id` VARCHAR(50) NOT NULL,
   `expense_date` DATE NOT NULL,
   `expense_time` TIME NOT NULL,
   `description` VARCHAR(255),
   `amount_spent` DECIMAL(10,2) NOT NULL,
   `petrol_rate` DECIMAL(10,2) NOT NULL,
   `fuel_vol_liters` DECIMAL(10,4) NOT NULL,
   `map_distance_km` DECIMAL(10,2) NOT NULL,
   `efficiency_l_km` DECIMAL(10,4) NOT NULL,
   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table 3: `fu_earn` (Profit Earnings)
```sql
CREATE TABLE IF NOT EXISTS `fu_earn` (
   `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
   `user_id` VARCHAR(50) NOT NULL,
   `ride_date` DATE NOT NULL,
   `ride_time` TIME NOT NULL,
   `route_desc` VARCHAR(255),
   `total_fare` DECIMAL(10,2) NOT NULL,
   `bonus_amount` DECIMAL(10,2) DEFAULT 0.00,
   `commission_amount` DECIMAL(10,2) NOT NULL,
   `fuel_efficiency` DECIMAL(10,4) NOT NULL,
   `petrol_price` DECIMAL(10,2) NOT NULL,
   `distance_km` DECIMAL(10,2) NOT NULL,
   `fuel_cost` DECIMAL(10,2) NOT NULL,
   `net_profit` DECIMAL(10,2) NOT NULL,
   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table 4: `user_locations` (Map Points)
```sql
CREATE TABLE user_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(100),
    location_title VARCHAR(255),
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## File Structure

```
it.noorgee.com/FU/
├── index.html              # Main Application (Single File)
├── save_data.php           # Data Save/Load API
├── auth.php                # User Authentication
├── admin.php               # Admin Panel (View Messages)
├── deploy.php              # Deploy Console
├── test_db.php             # Database Test
├── privacy_policy.html     # Privacy Policy
├── send_message.php        # User Message Submission
├── CLAUDE.md               # This File
└── .gitignore/
    └── it-fu.env           # Environment Variables (Credentials)
```

### Environment File Location
```
/home/noorgeec/it-fu.env
```

---

## Application Features

### Tab 1: Expense (Fuel Expense) - Red Color
- **Trip Details Section**:
  - Date (ddd, d-mmm-yy format)
  - Time
  - Route (Auto-filled from map - Start Point, Road Name, Area Name, Coordinates, End Point)
  - Map Distance
  - Amount Spent (PKR)
  - Petrol Rate (per Liter)

- **Expense History Table**:
  - Date/Time
  - Route Description
  - Cost (Rs) | Vol (L) | P. Rate (merged cells)
  - Average L/km
  - Action (Edit/Delete)

### Tab 2: Earn Profit - Green Color
- **Trip Details Section**:
  - Date (ddd, dd-mmm-y format)
  - Time
  - Route (Auto-filled from map - Start Point, Road Name, Area Name, Coordinates, End Point)
  - Earn Amount (Total Fare)
  - Addition Amount (+bonus)
  - Deductions Amount (-commission)
  - Fuel Efficiency (L/km)
  - Petrol Price

- **Earn History Table**:
  - Date/Time
  - Route
  - Fare (for Dist)
  - P.Price | Avg L/KM
  - +Bonus (Rs) | -Comm (Rs) | Fuel Cost (Rs)
  - Net Profit
  - Action (Edit/Delete)

---

## Map Integration (Leaflet.js)

### Key Logic
1. User clicks to add points
2. Wrong points can be deleted
3. **Distance Calculation**: Straight line × 1.3 = Road Distance (for traffic and curves)
4. Clear Map button

### Route Format
```
Start Point, (Road Name + Area Name)
Coordinate: xx.xxxx, xx.xxxx
End Point, (Road Name + Area Name)
Coordinate: xx.xxxx, xx.xxxx
```

---

## Calculation Formulas

### Fuel Average
```
Average (L/km) = Fuel Volume (Liters) ÷ Distance (km)
```

### Fuel Volume
```
Volume (L) = Amount Spent (PKR) ÷ Petrol Rate (PKR/L)
```

### Net Profit
```
Profit = Fare + Bonus - Commission - Fuel Cost
Fuel Cost = Distance × Efficiency × Petrol Price
```

---

## Navigation Menu

### File Menu
- User Login
- User Registration
- User Log Out
- Print

### Our Sites Menu
- noorgee.com
- blog.noorgee.com
- noorgee.pk
- lw.noorgee.pk
- noorgee.com/Web
- it.noorgee.com
- it.noorgee.com/FU
- it.noorgee.com/NM
- it.noorgee.com/TW
- it.noorgee.com/PM
- it.noorgee.com/UR
- it.noorgee.com/NW

### Help Menu
- Helping Guide (Popup Box)
- Contact Us (+92-332-3320369, admin@noorgee.com)
- Privacy Policy (Popup Box 90% Screen)

---

## Footer (3 Columns)

### Column 1: Brand Info
- Brand Name
- Copyright
- Last Update (dd-mmm-yy format + Time)
- Deploy Button

### Column 2: Partner Network
- lw.noorgee.pk
- noorgee.com/Web
- noorgee.com
- noorgee.pk
- blog.noorgee.com
- it.noorgee.com

### Column 3: Support & Resources
- Send Message (send_message.php)
- FAQ
- Help Guide
- Privacy Policy

---

## Design System

### Colors
| Element | Color |
|---------|-------|
| Expense Tab | Red |
| Profit Tab | Green |
| Expense Page Background | Light Red |
| Profit Page Background | Light Green |
| Headers | Blue-900 |
| Primary Buttons | Blue-600 |
| Profit | Green |
| Cost | Red |

### Glassmorphism
- White semi-transparent background
- Blur effect
- Slate-100 background

### UI Principles
- Mobile First
- Sticky Navigation
- Sticky Tabs
- Responsive Design
- Custom Modals (no alert boxes)

---

## Security

### How to Store Credentials Safely
```
/home/noorgeec/it-fu.env
```

### Add to .gitignore
```
it-fu.env
*.env
```

### Reading env File in PHP
```php
$envFile = '/home/noorgeec/it-fu.env';
$env = parse_ini_file($envFile);
$db_host = $env['DB_HOST'];
$db_name = $env['DB_NAME'];
$db_user = $env['DB_USER'];
$db_pass = $env['DB_PASS'];
```

---

## API Endpoints

### save_data.php
| Action | Method | Parameters |
|--------|--------|------------|
| load_all | GET | user={username} |
| save_trip | POST | user, date, time, route, fare, bonus, commission, efficiency, price, distance, fuel_cost, profit |
| save_expense | POST | user, date, time, desc, amount, rate, vol, distance, efficiency |
| delete_trip | POST | id, user |
| delete_expense | POST | id, user |
| edit_trip | POST | id, user, (all fields) |
| edit_expense | POST | id, user, (all fields) |

### auth.php
| Action | Method | Parameters |
|--------|--------|------------|
| register | POST | username, password |
| login | POST | username, password |
| logout | POST | - |

---

## Development History

### February 2026
**04-Feb-2026**: Privacy Policy popup, Send Message function, Internal Page

**10-Feb-2026**: Privacy Policy popup box, Send Message enabled, Contact Details added

**15-Feb-2026**: 
- "Trip History" → "Re-Fuel History"
- "Trip History" → "Earn History"
- Description route auto-fill
- Petrol rate API integration (PKR 257/L)

**17-Feb-2026**: 
- Route includes area name and distance
- Edit options enabled
- Time added with date

**18-Feb-2026**: MySQL structure added, user_locations table

**21-Feb-2026**: 
- Tab colors (Green/Red)
- Sticky tabs
- Route format includes parent area name

**22-Feb-2026**: 
- Page background colors (Green/Red)
- Route includes road name

**24-Feb-2026**: 
- Tables renamed (fu_users, fu_expense, fu_earn)
- env file location changed
- "Calculate & Save" → "Save Profit detail"

**25-Feb-2026**: 
- Frontend-Backend connection fixed
- Cloud sync enabled
- test_db.php added

**28-Feb-2026**: 
- User GPS tracking option
- Date format: ddd, d-mmm-yy
- Route format includes road name, area name, coordinates
- Map distance field
- Last Update format in footer

---

### March 2026
**01-Mar-2026**: 
- GPS tracking option added
- Trip Details heading deleted
- Route format updated (road name, area name, coordinates)
- Expense History table columns merged
- Earn History table +Bonus, -Comm, Fuel Cost merged
- "Commission" → "Deductions amount"
- "Profit" → "Earn Profit"
- Tab order changed (Expense first, then Profit)
- Edit/Delete buttons fixed

**04-Mar-2026**: 
- Earn History table Fare & Dist merged
- P.Price & Avg L/KM merged
- +Bonus format: "Rs +0#.#"
- Help Menu sections separated

---

## Future Features
- [ ] GPS tracking enabled
- [ ] Weekly/Monthly reports
- [ ] CSV Export
- [ ] Multiple vehicles
- [ ] Notifications
- [ ] Offline support

---

## Development Guidelines

### 1. Coding Style
```javascript
// File name: kebab-case
// Example: save-data.js, auth.php

// Variables: camelCase
const fuelAverage = 15.5;

// Functions: start with verb
function calculateProfit(rideData) {}
function saveUserData(userId, data) {}
```

### 2. Before Starting Work
1. Read existing code
2. Clarify the need for changes
3. Write a plan
4. Test

### 3. After Completing Work
1. Test all features
2. Check on mobile
3. Clean up code
4. Update documentation

---

## Contact
- Website: it.noorgee.com
- Phone: +92-332-3320369
- Email: admin@noorgee.com

---

*Last Updated: 04-Mar-2026*
