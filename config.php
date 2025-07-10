<?php
require 'vendor/autoload.php'; // Composer autoloader
$client = new MongoDB\Client("mongodb://localhost:27017");
$collection = $client->features_db->features;
?>