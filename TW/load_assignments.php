<?php
header('Content-Type: application/json');

$assignee = isset($_GET['assignee']) ? $_GET['assignee'] : '';

if (!$assignee) {
    echo json_encode([]);
    exit;
}



// Database credentials - update accordingly
$host = 'localhost'; // or your host
$dbname = 'noorgeec_it'; // your database name
$username = 'noorgeec_ng'; // your username
$password = 'h5R_7mufXUaVCPt'; // your password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT person, task, assigneeid, notes, createdAt FROM taskresults WHERE assigneeid = :assignee ORDER BY createdAt DESC");
    $stmt->execute([':assignee' => $assignee]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
