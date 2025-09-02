<?php
// get_system_names.php - Enhanced for searchable dropdown
require_once __DIR__ . '/../vendor/autoload.php'; // ✅ Composer autoload (important!)
require_once __DIR__ . '/../config.php';

use MongoDB\BSON\Regex; // ✅ Import the Regex class

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

try {
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    if (strlen($search) > 100) {
        throw new Exception('Search query too long');
    }

    $db = Database::getInstance();
    $collection = $db->getCollection();

   $matchStage = [
    '$match' => [
        'name' => [
            '$regex' => $escapedSearch,
            '$options' => 'i'
        ]
    ]
];
    $pipeline = [];

    if (!empty($matchStage)) {
        $pipeline[] = $matchStage;
    }

   $pipeline[] = [
    '$group' => [
        '_id' => '$name',
        'count' => ['$sum' => 1]
    ]
];


    $pipeline[] = ['$sort' => ['_id' => 1]];
    $pipeline[] = ['$skip' => $offset];
    $pipeline[] = ['$limit' => $limit];

    $cursor = $collection->aggregate($pipeline);
    $results = $cursor->toArray();

    $formattedResults = [];
    foreach ($results as $item) {
        if (!empty($item['_id'])) {
            $formattedResults[] = [
                'id' => $item['_id'],
                'text' => $item['_id'],
                'count' => $item['count'] ?? 0
            ];
        }
    }

    // Count total unique matches
    $countPipeline = [];
    if (!empty($matchStage)) {
        $countPipeline[] = $matchStage;
    }
   $countPipeline[] = [
    '$group' => [
        '_id' => '$name'
    ]
];
    $countPipeline[] = [
        '$group' => [
            '_id' => null,
            'total' => ['$sum' => 1]
        ]
    ];

    $countCursor = $collection->aggregate($countPipeline);
    $countResult = $countCursor->toArray();
    $totalCount = $countResult[0]['total'] ?? 0;

    $hasMore = ($page * $limit) < $totalCount;

    // Allow new tag creation if no matches
    if (empty($formattedResults) && !empty($search)) {
        $existingCheck = $collection->findOne([
    'name' => new Regex('^' . preg_quote($search, '/') . '$', 'i')
]);

        if (!$existingCheck) {
            $formattedResults[] = [
                'id' => $search,
                'text' => $search,
                'newTag' => true
            ];
        }
    }

    echo json_encode([
        'results' => $formattedResults,
        'pagination' => ['more' => $hasMore],
        'total_count' => $totalCount,
        'current_page' => $page,
        'per_page' => $limit
    ], JSON_UNESCAPED_UNICODE);

} catch (MongoDB\Driver\Exception\Exception $mongoError) {
    error_log("MongoDB Error: " . $mongoError->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database query failed',
        'results' => [],
        'pagination' => ['more' => false],
        'total_count' => 0
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An error occurred: ' . $e->getMessage(),
        'results' => [],
        'pagination' => ['more' => false],
        'total_count' => 0
    ]);
}
