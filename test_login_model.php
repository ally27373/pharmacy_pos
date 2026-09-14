<?php

require_once 'app/Models/User.php';

$user = new User();

$result = $user->findByUsernameOrEmail("juan@gmail.com");

echo "<pre>";
print_r($result);
echo "</pre>";