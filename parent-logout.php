<?php
session_start();

// Destroy all session variables
session_destroy();

// Redirect to parent login page
header("Location: parent-login.php?logout=1");
exit();
?>
