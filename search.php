<?php
require 'config.php';

header('Content-Type: application/json');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($search === '') {
    echo json_encode([]);
    exit;
}

$cursor = $collection->find([
    '$or' => [
        ['system_name' => ['$regex' => $search, '$options' => 'i']],
        ['module' => ['$regex' => $search, '$options' => 'i']],
        ['description' => ['$regex' => $search, '$options' => 'i']],
        ['client' => ['$regex' => $search, '$options' => 'i']]
    ]
]);

$results = [];
foreach ($cursor as $doc) {
    $doc['_id'] = (string) $doc['_id'];
    $doc['created_at'] = isset($doc['created_at']) ? $doc['created_at']->toDateTime()->format('Y-m-d | H:i') : 'N/A';
    $results[] = $doc;
}

echo json_encode($results);
