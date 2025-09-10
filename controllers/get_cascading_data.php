<?php
/**
 * Enhanced API endpoint for cascading dropdown data
 * Returns filtered data based on parent selections with proper module support
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

try {
    $type = $_GET['type'] ?? '';
    $q = $_GET['q'] ?? '';
    
    // Debug logging
    error_log("Cascading API called with type: $type, query: $q");
    error_log("Full GET params: " . json_encode($_GET));
    
    // Initialize database connection
    $db = Database::getInstance()->getDatabase();
    $response = ['success' => false, 'data' => [], 'debug' => []];
    
    switch ($type) {
        case 'systems':
        case 'system_names':
            // Get from system_names collection
            $systemNamesCollection = $db->getCollection('system_names');
            $filter = ['is_active' => true];
            
            if (!empty($q)) {
                $filter['name'] = ['$regex' => $q, '$options' => 'i'];
            }
            
            $cursor = $systemNamesCollection->find($filter, ['sort' => ['name' => 1]]);
            $data = [];
            
            foreach ($cursor as $doc) {
                $data[] = $doc['name'];
            }
            
            $response = ['success' => true, 'data' => array_values(array_unique($data))];
            break;
            
        case 'modules':
            // Get all modules from modules collection
            $modulesCollection = $db->getCollection('modules');
            $filter = ['is_active' => true];
            
            if (!empty($q)) {
                $filter['name'] = ['$regex' => $q, '$options' => 'i'];
            }
            
            $cursor = $modulesCollection->find($filter, ['sort' => ['name' => 1]]);
            $data = [];
            
            foreach ($cursor as $doc) {
                $data[] = $doc['name'];
            }
            
            $response = ['success' => true, 'data' => array_values(array_unique($data))];
            break;
            
        case 'modules_by_system':
    // Get modules filtered by system name - FIXED to prioritize modules collection
    $systemName = $_GET['system_name'] ?? '';
    
    if (empty($systemName)) {
        $response = ['success' => false, 'error' => 'System name required for modules_by_system'];
        break;
    }
    
    $modulesCollection = $db->getCollection('modules');
    $overallCollection = $db->getCollection('overall');
    
    $data = [];
    $moduleIds = []; // Track module IDs to avoid duplicates
    
    // 1. PRIMARY SOURCE: Get active modules from modules collection
    $modulesFilter = [
        'system_name' => $systemName,
        'is_active' => true
    ];
    
    if (!empty($q)) {
        $modulesFilter['name'] = ['$regex' => $q, '$options' => 'i'];
    }
    
    $modulesCursor = $modulesCollection->find($modulesFilter, ['sort' => ['name' => 1]]);
    
    foreach ($modulesCursor as $doc) {
        if (!empty($doc['name'])) {
            $data[] = $doc['name'];
            if (isset($doc['id'])) {
                $moduleIds[] = $doc['id'];
            }
        }
    }
    
    // 2. SECONDARY SOURCE: Get from overall collection ONLY for modules not found in primary
    $overallFilter = [
        'system_name' => $systemName,
        'module' => ['$exists' => true, '$ne' => '']
    ];
    
    // Exclude modules we already found by ID
    if (!empty($moduleIds)) {
        $overallFilter['module_id'] = ['$nin' => $moduleIds];
    }
    
    if (!empty($q)) {
        $overallFilter['module'] = ['$regex' => $q, '$options' => 'i'];
    }
    
    $overallCursor = $overallCollection->find($overallFilter);
    
    foreach ($overallCursor as $doc) {
        if (!empty($doc['module']) && !in_array($doc['module'], $data)) {
            // Only add if this module doesn't exist in modules collection
            $moduleExists = $modulesCollection->countDocuments([
                'name' => $doc['module'],
                'system_name' => $systemName
            ]) > 0;
            
            if (!$moduleExists) {
                $data[] = $doc['module'];
            }
        }
    }
    
    // Sort and return unique values
    sort($data);
    $response = ['success' => true, 'data' => array_values(array_unique($data))];
    error_log("FIXED: Modules for system '$systemName': " . json_encode($response['data']));
    break;
            
        case 'features':
            // Get all features from overall collection
            $overallCollection = $db->getCollection('overall');
            $filter = ['feature' => ['$exists' => true, '$ne' => '']];
            
            if (!empty($q)) {
                $filter['feature'] = ['$regex' => $q, '$options' => 'i'];
            }
            
            $cursor = $overallCollection->find($filter);
            $data = [];
            
            foreach ($cursor as $doc) {
                if (!empty($doc['feature'])) {
                    $data[] = $doc['feature'];
                }
            }
            
            $response = ['success' => true, 'data' => array_values(array_unique($data))];
            break;
            
        case 'features_by_system_module':
            // Get features filtered by system and module
            $systemName = $_GET['system_name'] ?? '';
            $module = $_GET['module'] ?? '';
            
            if (empty($systemName) || empty($module)) {
                $response = ['success' => false, 'error' => 'System name and module required for features_by_system_module'];
                break;
            }
            
            $overallCollection = $db->getCollection('overall');
            $filter = [
                'system_name' => $systemName,
                'module' => $module,
                'feature' => ['$exists' => true, '$ne' => '']
            ];
            
            if (!empty($q)) {
                $filter['feature'] = ['$regex' => $q, '$options' => 'i'];
            }
            
            $cursor = $overallCollection->find($filter);
            $data = [];
            
            foreach ($cursor as $doc) {
                if (!empty($doc['feature'])) {
                    $data[] = $doc['feature'];
                }
            }
            
            $response = ['success' => true, 'data' => array_values(array_unique($data))];
            break;
            
        case 'clients':
            // Get all clients from overall collection and clients collection if exists
            $overallCollection = $db->getCollection('overall');
            $filter = ['client' => ['$exists' => true, '$ne' => '']];
            
            if (!empty($q)) {
                $filter['client'] = ['$regex' => $q, '$options' => 'i'];
            }
            
            $cursor = $overallCollection->find($filter);
            $data = [];
            
            foreach ($cursor as $doc) {
                if (!empty($doc['client'])) {
                    $data[] = $doc['client'];
                }
            }
            
            // Also check clients collection if it exists
            try {
                $clientsCollection = $db->getCollection('clients');
                $clientFilter = ['is_active' => true];
                
                if (!empty($q)) {
                    $clientFilter['name'] = ['$regex' => $q, '$options' => 'i'];
                }
                
                $clientCursor = $clientsCollection->find($clientFilter);
                
                foreach ($clientCursor as $doc) {
                    if (!empty($doc['name'])) {
                        $data[] = $doc['name'];
                    }
                }
            } catch (Exception $e) {
                // Clients collection might not exist, continue without it
                error_log("Clients collection not found or error: " . $e->getMessage());
            }
            
            $response = ['success' => true, 'data' => array_values(array_unique($data))];
            break;
            
        case 'sources':
            // Get all sources from overall collection and sources collection if exists
            $overallCollection = $db->getCollection('overall');
            $filter = ['source' => ['$exists' => true, '$ne' => '']];
            
            if (!empty($q)) {
                $filter['source'] = ['$regex' => $q, '$options' => 'i'];
            }
            
            $cursor = $overallCollection->find($filter);
            $data = [];
            
            foreach ($cursor as $doc) {
                if (!empty($doc['source'])) {
                    $data[] = $doc['source'];
                }
            }
            
            // Also check sources collection if it exists
            try {
                $sourcesCollection = $db->getCollection('sources');
                $sourceFilter = ['is_active' => true];
                
                if (!empty($q)) {
                    $sourceFilter['name'] = ['$regex' => $q, '$options' => 'i'];
                }
                
                $sourceCursor = $sourcesCollection->find($sourceFilter);
                
                foreach ($sourceCursor as $doc) {
                    if (!empty($doc['name'])) {
                        $data[] = $doc['name'];
                    }
                }
            } catch (Exception $e) {
                // Sources collection might not exist, continue without it
                error_log("Sources collection not found or error: " . $e->getMessage());
            }
            
            $response = ['success' => true, 'data' => array_values(array_unique($data))];
            break;
            
        default:
            $response = ['success' => false, 'error' => "Unknown type: $type"];
            break;
    }
    
    // Add debug information in development
    $response['debug'] = [
        'type' => $type,
        'query' => $q,
        'params' => $_GET,
        'count' => count($response['data'] ?? [])
    ];
    
} catch (Exception $e) {
    error_log("Cascading data error: " . $e->getMessage());
    $response = [
        'success' => false, 
        'error' => 'Database error occurred',
        'debug' => [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ];
}

echo json_encode($response);
?>