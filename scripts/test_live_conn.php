<?php
$host = 'localhost';
$user = 'middlehi_IDAHOSA_School_1';
$pass = 'middlehi_IDAHOSA_School_1';
$name = 'middlehi_IDAHOSA_School_1';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
    echo "SUCCESS: Connected to $name\n";
} catch (PDOException $e) {
    echo "FAILURE: " . $e->getMessage() . "\n";
}
