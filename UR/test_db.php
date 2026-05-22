<?php
$servername = "localhost";
$username = "noorgeec_ng";
$password = "h5R_7mufXUaVCPt"; // Make sure no spaces are here
$dbname = "noorgeec_it";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
echo "✅ Connected successfully to database: " . $dbname;
?>