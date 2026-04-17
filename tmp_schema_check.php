<?php
$pdo = new PDO('mysql:host=localhost;dbname=coaching_db','root','');
$tables = ['classes', 'students', 'results', 'class_routine'];
foreach ($tables as $table) {
    echo "TABLE: $table\n";
    foreach ($pdo->query("SHOW COLUMNS FROM $table") as $row) {
        echo $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Null'] . ' ' . $row['Key'] . ' ' . $row['Extra'] . PHP_EOL;
    }
    echo "\n";
}
