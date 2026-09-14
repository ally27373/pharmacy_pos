<?php

require_once 'app/Controllers/AuthController.php';

$controller = new AuthController();

$result = $controller->register([
    'full_name' => 'Maria Santos',
    'username'  => 'maria01',
    'email'     => 'maria01@gmail.com',
    'password'  => 'password123'
]);

echo "<pre>";
print_r($result);
echo "</pre>";