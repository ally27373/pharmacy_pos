<?php

require_once '../../config/session.php';

// Remove all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");

exit;