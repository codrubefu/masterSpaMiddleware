<?php
// Database connection details
$host = 'sqlserver';
$db = 'spa';
$user = 'sa';
$pass = 'YourStrong!Passw0rd';
$charset = 'utf8';

if(!$_POST){
    echo "<form method='POST' action='index2.php'><input type='text' name='name' value=''><input type='submit' value='test'></form>";
    die(1);
}
// Add TrustServerCertificate=true to the DSN
$dsn = "sqlsrv:Server=$host;Database=$db;TrustServerCertificate=true";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create a new PDO instance
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Insert query
    $sql = "INSERT INTO testCodrut (prenume) VALUES (:prenume)";
    $stmt = $pdo->prepare($sql);

    // Bind and execute
    $prenume = $_POST['name']; // Replace 'John' with the value you want to insert
    $stmt->bindParam(':prenume', $prenume);
    $stmt->execute();

    echo "Record inserted successfully.";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
file_put_contents('../storage/logs/executari.txt', date('Y-m-d H:i:s') . " | " . $_SERVER['REQUEST_METHOD'] . " | " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

die(2);
