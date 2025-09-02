<?php
require 'vendor/autoload.php';

$client = new MongoDB\Client("mongodb://localhost:27017");
$database = $client->features_db;

$featuresCollection = $database->features;
$overallCollection = $database->overall;

$documents = $featuresCollection->find();

foreach ($documents as $doc) {
    $data = $doc;
    unset($data['_id']); // remove _id to avoid duplication

    $overallCollection->insertOne($data);
}

echo "Migration completed.\n";
