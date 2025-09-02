<?php
require_once __DIR__ . '/../config.php';

use MongoDB\BSON\Regex;

// Set header for JSON response
header('Content-Type: application/json');

$type   = $_GET['type']   ?? null; // e.g., 'module', 'feature_name', etc.
$parent = $_GET['parent'] ?? null; // e.g., 'system_name', 'module', etc.
$value  = $_GET['value']  ?? null;

if (!$type || !$parent || !$value) {
    echo json_encode([]);
    exit;
}

$collection = (new Database)->getCollection();
$filter = [$parent => $value];

$options = $collection->distinct($type, $filter);
$response = [];

foreach ($options as $opt) {
    $response[] = ['id' => $opt, 'text' => $opt];
}

echo json_encode($response);
exit;
