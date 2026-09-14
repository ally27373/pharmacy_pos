<?php

require_once 'app/Models/User.php';

$user = new User();

echo "<h2>User Exists Test</h2>";

$username = "admin";

if ($user->usernameExists($username)) {

    echo "Username Found";

} else {

    echo "Username Not Found";
}