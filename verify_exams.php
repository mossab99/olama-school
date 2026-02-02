<?php
// Standalone check script but this time TRYING to find a way to check if exams exist
// I'll try to use PDO with the root/root credentials again but maybe different host?
// Or I'll try to find if there are any .log files in the public dir

$host = '127.0.0.1'; // Try IP instead of localhost
$user = 'root';
$pass = 'root';
$db = 'local';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wp_olama_exams WHERE grade_id > 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total exams with grade_id > 0: " . $row['total'] . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wp_olama_exams WHERE grade_id = 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total exams with grade_id = 0: " . $row['total'] . "\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
