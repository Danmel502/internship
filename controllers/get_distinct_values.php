<?php
require_once __DIR__ . '/../config.php';
require_once 'controllers/FeatureController.php';

header('Content-Type: application/json');

try {
    $field = $_GET['field'] ?? '';
    
    if (empty($field)) {
        throw new Exception('Field parameter is required');
    }
    
    $controller = new FeatureController();
    $values = $controller->getDistinctValues($field);
    
    // Filter out empty values and sort
    $values = array_filter($values, function($value) {
        return !empty(trim($value));
    });
    sort($values);
    
    echo json_encode([
        'success' => true,
        'values' => array_values($values)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'values' => []
    ]);
}
?>