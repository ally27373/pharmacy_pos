<?php

require_once 'app/Models/User.php';

$user = new User();

if ($user->testConnection()) {
    echo "<h2>✅ User Model Connected to Database!</h2>";
} else {
    echo "<h2>❌ Connection Failed.</h2>";
}