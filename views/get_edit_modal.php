<?php
// get_edit_modal.php
require_once 'config/database.php';
require_once 'controllers/FeatureController.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Feature ID required';
    exit;
}

$featureId = $_GET['id'];

try {
    $controller = new FeatureController();
    $feature = $controller->getFeatureById($featureId);
    
    if (!$feature) {
        http_response_code(404);
        echo 'Feature not found';
        exit;
    }
    
    // Get option sets for dropdowns
    $optionSets = [];
    $optionSets['system'] = $controller->getDistinctValues('system_name') ?? [];
    $optionSets['module'] = $controller->getDistinctValues('module') ?? [];
    $optionSets['feature'] = $controller->getDistinctValues('feature') ?? [];
    $optionSets['client'] = $controller->getDistinctValues('client') ?? [];
    $optionSets['source'] = $controller->getDistinctValues('source') ?? [];
    
    $controllerLoaded = true;
    
    // Include the edit modal template
    include 'views/modals/edit_modal.php';
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error loading feature: ' . htmlspecialchars($e->getMessage());
}
?>