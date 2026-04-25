<?php
// Debug script to test form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    echo "<h2>Files:</h2>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
} else {
    echo "<p>No POST data received. This script should be called via form submission.</p>";
}
?>