<?php

header('Content-Type: text/plain');

echo "PHP version: " . PHP_VERSION . PHP_EOL;
echo "PDO drivers:" . PHP_EOL;

print_r(PDO::getAvailableDrivers());
