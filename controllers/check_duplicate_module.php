<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['module_name']) || !isset($input['system_name_id'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $db = Database::getInstance()->getDatabase();
    $collection = $db->getCollection('modules');
    
    $query = [
        'name' => trim($input['module_name']),
        'system_name_id' => (int)$input['system_name_id'],
        'is_active' => true
    ];
    
    // Exclude current module if editing
    if (isset($input['exclude_id']) && $input['exclude_id'] > 0) {
        $query['id'] = ['$ne' => (int)$input['exclude_id']];
    }
    
    $existing = $collection->findOne($query);
    
    echo json_encode([
        'isDuplicate' => $existing !== null,
        'success' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'isDuplicate' => false
    ]);
}
?>