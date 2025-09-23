<?php
// Show errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// API Headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../controllers/FeatureController.php';

$method = $_SERVER['REQUEST_METHOD'];

// Get collection type from query parameter (default to 'overall' for backward compatibility)
$collection = $_GET['collection'] ?? 'overall';
$validCollections = ['overall', 'system_names', 'modules', 'features', 'clients', 'sources', 'keywords'];

if (!in_array($collection, $validCollections)) {
    sendErrorResponse('Invalid collection. Valid collections: ' . implode(', ', $validCollections), 400);
}

// Get ID from query string - validate MongoDB ObjectId format
$id = null;
if (isset($_GET['id']) && preg_match('/^[a-f\d]{24}$/i', $_GET['id'])) {
    $id = $_GET['id'];
}

// Get numeric ID for reference collections
$numericId = null;
if (isset($_GET['numeric_id']) && is_numeric($_GET['numeric_id'])) {
    $numericId = (int)$_GET['numeric_id'];
}

// Read JSON body for non-form requests
function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents("php://input");
        if (!empty($input)) {
            return json_decode($input, true);
        }
    }
    return null;
}

// Format output - convert MongoDB ObjectId to string
function formatDocument($document) {
    if (!$document) {
        return null;
    }
    
    // Convert to array if it's a MongoDB document
    if (is_object($document) && method_exists($document, 'toArray')) {
        $document = $document->toArray();
    }
    
    // Convert ObjectId to string
    if (isset($document['_id'])) {
        $document['_id'] = (string) $document['_id'];
    }
    
    return $document;
}

// Send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Send error response
function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(['error' => $message], $statusCode);
}

// Get document by ID from any collection
function getDocumentById($collectionName, $id = null, $numericId = null) {
    try {
        $db = Database::getInstance();
        
        if ($collectionName === 'overall') {
            // Use existing FeatureController method for overall collection
            if ($id) {
                return FeatureController::getFeatureById($id);
            }
            return null;
        }
        
        // For reference collections
        $collection = $db->getCollection($collectionName);
        if (!$collection) {
            return null;
        }
        
        $query = [];
        if ($id) {
            // Search by MongoDB ObjectId
            $query['_id'] = new MongoDB\BSON\ObjectId($id);
        } elseif ($numericId !== null) {
            // Search by numeric ID field
            $query['id'] = $numericId;
        } else {
            return null;
        }
        
        $document = $collection->findOne($query);
        return formatDocument($document);
        
    } catch (Exception $e) {
        error_log("Error getting document: " . $e->getMessage());
        return null;
    }
}

// Get all documents from a collection with filtering
function getDocuments($collectionName, $limit = 0, $skip = 0, $filters = []) {
    try {
        $db = Database::getInstance();
        
        if ($collectionName === 'overall') {
            // Use existing FeatureController methods for overall collection
            if (!empty($_GET['search'])) {
                return FeatureController::searchFeatures($_GET['search'], $limit, $skip);
            }
            return FeatureController::getFeatures($limit, $skip, $filters);
        }
        
        // For reference collections
        $collection = $db->getCollection($collectionName);
        if (!$collection) {
            return [];
        }
        
        // Build query for reference collections
        $query = ['is_active' => true]; // Most reference collections have is_active field
        
        // Add name filter if provided
        if (!empty($filters['name'])) {
            $query['name'] = new MongoDB\BSON\Regex($filters['name'], 'i');
        }
        
        // Add system_name filter for modules
        if ($collectionName === 'modules' && !empty($filters['system_name'])) {
            $query['system_name'] = $filters['system_name'];
        }
        
        $options = ['sort' => ['name' => 1]];
        if ($limit > 0) {
            $options['limit'] = (int)$limit;
            $options['skip'] = (int)$skip;
        }
        
        $cursor = $collection->find($query, $options);
        $documents = [];
        
        foreach ($cursor as $doc) {
            $formatted = formatDocument($doc);
            if ($formatted) {
                $documents[] = $formatted;
            }
        }
        
        return $documents;
        
    } catch (Exception $e) {
        error_log("Error getting documents: " . $e->getMessage());
        return [];
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($id || $numericId !== null) {
                // Get single document by ID
                $document = getDocumentById($collection, $id, $numericId);
                
                if ($document) {
                    sendJsonResponse($document);
                } else {
                    sendErrorResponse(ucfirst($collection) . ' not found', 404);
                }
            } else {
                // Get all documents with optional filtering
                $search = $_GET['search'] ?? '';
                $limit = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 0;
                $skip = isset($_GET['skip']) ? max(0, (int)$_GET['skip']) : 0;
                
                // Build filters based on collection type
                $filters = [];
                if ($collection === 'overall') {
                    // Use existing overall collection filters
                    $systemName = $_GET['system_name'] ?? '';
                    $module = $_GET['module'] ?? '';
                    $client = $_GET['client'] ?? '';
                    $source = $_GET['source'] ?? '';
                    
                    $filters = array_filter([
                        'system_name' => $systemName,
                        'module' => $module,
                        'client' => $client,
                        'source' => $source
                    ]);
                } else {
                    // For reference collections, support name and system_name filters
                    $name = $_GET['name'] ?? '';
                    $systemName = $_GET['system_name'] ?? '';
                    
                    $filters = array_filter([
                        'name' => $name,
                        'system_name' => $systemName
                    ]);
                }
                
                $documents = getDocuments($collection, $limit, $skip, $filters);
                sendJsonResponse($documents);
            }
            break;

        case 'POST':
            // Only allow POST for overall collection (features) for now
            if ($collection !== 'overall') {
                sendErrorResponse('POST method not supported for ' . $collection . ' collection', 405);
            }
            
            // Handle both JSON and form data
            $data = getInputData();
            if ($data && is_array($data)) {
                // JSON request
                $postData = $data;
                $fileData = [];
            } else {
                // Form request
                $postData = $_POST;
                $fileData = $_FILES ?? [];
            }
            
            if (empty($postData)) {
                sendErrorResponse('No data provided');
            }
            
            $result = FeatureController::addFeature($postData, $fileData);
            
            if ($result && isset($result['success']) && $result['success']) {
                sendJsonResponse([
                    'message' => 'Feature created successfully',
                    'id' => $result['id'] ?? null,
                    'success' => true
                ], 201);
            } else {
                $errors = $result['errors'] ?? ['Unknown error occurred'];
                sendJsonResponse([
                    'success' => false,
                    'errors' => $errors
                ], 400);
            }
            break;

        case 'PUT':
            // Only allow PUT for overall collection (features) for now
            if ($collection !== 'overall') {
                sendErrorResponse('PUT method not supported for ' . $collection . ' collection', 405);
            }
            
            if (!$id) {
                sendErrorResponse('ID is required for update');
            }

            $data = getInputData();
            if (!$data || !is_array($data)) {
                sendErrorResponse('Invalid JSON data provided');
            }

            // Convert to controller expected format
            $postData = [
                'edit_id' => $id,
                'edit_system_name' => trim($data['system_name'] ?? ''),
                'edit_module' => trim($data['module'] ?? ''),
                'edit_feature' => trim($data['feature'] ?? ''),
                'edit_description' => trim($data['description'] ?? ''),
                'edit_client' => trim($data['client'] ?? ''),
                'edit_source' => trim($data['source'] ?? '')
            ];

            $result = FeatureController::updateFeature($postData, []);
            
            if ($result && isset($result['success']) && $result['success']) {
                sendJsonResponse([
                    'message' => 'Feature updated successfully',
                    'success' => true
                ]);
            } else {
                $errors = $result['errors'] ?? ['Update failed'];
                sendJsonResponse([
                    'success' => false,
                    'errors' => $errors
                ], 400);
            }
            break;

        case 'DELETE':
            // Only allow DELETE for overall collection (features) for now
            if ($collection !== 'overall') {
                sendErrorResponse('DELETE method not supported for ' . $collection . ' collection', 405);
            }
            
            if (!$id) {
                sendErrorResponse('ID is required for deletion');
            }

            $result = FeatureController::deleteFeature($id);
            
            if ($result && isset($result['success']) && $result['success']) {
                sendJsonResponse([
                    'message' => 'Feature deleted successfully',
                    'success' => true
                ]);
            } else {
                $errorMessage = $result['error'] ?? 'Feature not found or could not be deleted';
                sendJsonResponse([
                    'success' => false,
                    'error' => $errorMessage
                ], 404);
            }
            break;

        default:
            sendErrorResponse('Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], 500);
}
?>