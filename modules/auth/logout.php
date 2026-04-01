<?php
require_once __DIR__ . '/../../includes/auth.php';

// Perform secure logout
perform_logout();

// Redirect to login
header("Location: login.php");
exit();
?>
