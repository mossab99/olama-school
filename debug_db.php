<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = 'root';
$db = 'local';

echo "Attempting to connect to $db at $host as $user...\n";

// Try MySQLi
if (extension_loaded('mysqli')) {
    echo "MySQLi extension loaded.\n";
    $conn = @new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        echo "MySQLi connection failed: " . $conn->connect_error . "\n";
    } else {
        echo "MySQLi connection successful.\n";
        check_table($conn);
        exit;
    }
} else {
    echo "MySQLi extension NOT loaded.\n";
}

// Try PDO
if (extension_loaded('pdo_mysql')) {
    echo "PDO MySQL extension loaded.\n";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        echo "PDO connection successful.\n";
        check_table_pdo($pdo);
        exit;
    } catch (PDOException $e) {
        echo "PDO connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "PDO MySQL extension NOT loaded.\n";
}

function check_table($conn)
{
    $table = 'wp_olama_semester_exams';
    echo "Checking table: $table\n";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo " - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

function check_table_pdo($pdo)
{
    $table = 'wp_olama_semester_exams';
    echo "Checking table: $table\n";
    $stmt = $pdo->query("DESCRIBE $table");
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo " - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "Error fetching columns.\n";
    }
}
