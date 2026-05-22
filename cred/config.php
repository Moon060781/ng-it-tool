<?php
/**
 * DATABASE CONFIGURATION
 * Edit the values below with the credentials provided by your hosting panel (cPanel).
 */

// Usually 'localhost' if the DB is on the same server, 
// otherwise use the IP address provided by your host.
define('DB_SERVER', 'localhost'); 
define('DB_USER', 'noorgeec_pm'); // Replace with your DB Username
define('DB_PASS', 'Pr0Mt@10dec'); // Replace with your DB Password
define('DB_NAME', 'noorgeec_it');   // Replace with your DB Name

// Error reporting for debugging (disable in production)
mysqli_report(MYSQLI_REPORT_OFF); 
?>