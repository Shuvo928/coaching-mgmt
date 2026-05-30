<?php
require 'includes/db.php';
$q = "SELECT s.*, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.id DESC";
$r = mysqli_query($conn,$q) or die(mysqli_error($conn));
echo "OK: ".mysqli_num_rows($r).PHP_EOL;
