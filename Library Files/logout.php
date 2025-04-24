<?php
session_start();
include 'db_config.php';

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
redirect('index.php');
?>