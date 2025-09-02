<?php
/**
 * API endpoint for cascading dropdown data
 * Returns filtered data based on parent selections
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

try {
    require_once __DIR__ . '/../controllers/FeatureController.php';
    
    $type = $_GET['type'] ?? '';
    $q = $_GET['q'] ?? '';
    
    // Build filters based on JavaScript requests
    $filters = [];
    
    // Map JavaScript complex query types to filters
    switch ($type) {
        case 'systems':
        case 'systems_by_source':
        case 'systems_by_client': 
        case 'systems_by_feature':
        case 'systems_by_module':
            $targetField = 'system_name';
            if (isset($_GET['source'])) $filters['source'] = $_GET['source'];
            if (isset($_GET['client'])) $filters['client'] = $_GET['client'];
            if (isset($_GET['feature'])) $filters['feature'] = $_GET['feature'];
            if (isset($_GET['module'])) $filters['module'] = $_GET['module'];
            break;
            
        case 'modules':
        case 'modules_by_system':
        case 'modules_by_source':
        case 'modules_by_client':
        case 'modules_by_feature':
            $targetField = 'module';
            if (isset($_GET['system_name'])) $filters['system_name'] = $_GET['system_name'];
            if (isset($_GET['source'])) $filters['source'] = $_GET['source'];
            if (isset($_GET['client'])) $filters['client'] = $_GET['client'];
            if (isset($_GET['feature'])) $filters['feature'] = $_GET['feature'];
            break;
            
        case 'features':
        case 'features_by_system':
        case 'features_by_module': 
        case 'features_by_system_module':
        case 'features_by_client':
        case 'features_by_source':
            $targetField = 'feature';
            if (isset($_GET['system_name'])) $filters['system_name'] = $_GET['system_name'];
            if (isset($_GET['module'])) $filters['module'] = $_GET['module'];
            if (isset($_GET['client'])) $filters['client'] = $_GET['client'];
            if (isset($_GET['source'])) $filters['source'] = $_GET['source'];
            break;
            
        case 'clients':
        case 'clients_by_system':
        case 'clients_by_module':
        case 'clients_by_feature':
        case 'clients_by_system_module_feature':
        case 'clients_by_source':
            $targetField = 'client';
            if (isset($_GET['system_name'])) $filters['system_name'] = $_GET['system_name'];
            if (isset($_GET['module'])) $filters['module'] = $_GET['module'];
            if (isset($_GET['feature'])) $filters['feature'] = $_GET['feature'];
            if (isset($_GET['source'])) $filters['source'] = $_GET['source'];
            break;
            
        case 'sources':
        case 'sources_by_system':
        case 'sources_by_module':
        case 'sources_by_feature':
        case 'sources_by_client':
        case 'sources_by_complete_hierarchy':
            $targetField = 'source';
            if (isset($_GET['system_name'])) $filters['system_name'] = $_GET['system_name'];
            if (isset($_GET['module'])) $filters['module'] = $_GET['module'];
            if (isset($_GET['feature'])) $filters['feature'] = $_GET['feature'];
            if (isset($_GET['client'])) $filters['client'] = $_GET['client'];
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Invalid type parameter'];
            echo json_encode($response);
            exit;
    }
    
    // Use FeatureController's method
    $result = FeatureController::getCascadingData($targetField, $filters);
    
    // Filter results by search term if provided
    if (!empty($q) && $result['success']) {
        $filteredData = array_filter($result['data'], function($item) use ($q) {
            return stripos($item, $q) !== false;
        });
        $result['data'] = array_values($filteredData);
    }
    
    $response = $result;
    
} catch (Exception $e) {
    error_log("Cascading data error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Database error occurred'];
}

echo json_encode($response);
?>