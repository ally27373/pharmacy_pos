<?php

require_once 'app/Models/User.php';

$user = new User();

$result = $user->register(

    "Juan Dela Cruz",

    "juan",

    "juan@gmail.com",

    "123456"

);

if ($result) {

    echo "<h2>✅ User Registered Successfully!</h2>";

} else {

    echo "<h2>❌ Registration Failed.</h2>";

}