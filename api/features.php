<?php
// Show errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// API Headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../controllers/FeatureController.php';

$method = $_SERVER['REQUEST_METHOD'];

// Get ID from query string
$id = isset($_GET['id']) && preg_match('/^[a-f\d]{24}$/i', $_GET['id']) ? $_GET['id'] : null;

// Read JSON body for non-form requests
function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        return json_decode(file_get_contents("php://input"), true);
    }
    return null;
}

// Format output
function formatFeature($f) {
    if (isset($f['_id'])) {
        $f['_id'] = (string) $f['_id'];
    }
    return $f;
}

switch ($method) {
    case 'GET':
        if ($id) {
            // Get single feature
            $feature = FeatureController::getFeatureById($id);
            if ($feature) {
                echo json_encode(formatFeature($feature->toArray()));
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Feature not found']);
            }
        } else {
            // Handle search and filtering
            $search = $_GET['search'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
            $skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
            
            if (!empty($search)) {
                $features = FeatureController::searchFeatures($search, $limit, $skip);
            } else {
                $features = FeatureController::getFeatures($limit, $skip);
            }
            
            $output = [];
            foreach ($features as $f) {
                $output[] = formatFeature($f->toArray());
            }
            echo json_encode($output);
        }
        break;

    case 'POST':
        // Handle both JSON and form data
        $data = getInputData();
        if ($data) {
            // JSON request
            $postData = $data;
            $fileData = [];
        } else {
            // Form request
            $postData = $_POST;
            $fileData = $_FILES;
        }
        
        $result = FeatureController::addFeature($postData, $fileData);
        
        if ($result['success']) {
            http_response_code(201);
            echo json_encode([
                'message' => 'Feature created successfully',
                'id' => $result['id']
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['errors' => $result['errors']]);
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            break;
        }

        $data = getInputData();
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            break;
        }

        // Convert to controller expected format
        $postData = [
            'edit_id' => $id,
            'edit_system_name' => $data['system_name'] ?? '',
            'edit_module' => $data['module'] ?? '',
            'edit_feature' => $data['feature'] ?? '',
            'edit_description' => $data['description'] ?? '',
            'edit_client' => $data['client'] ?? '',
            'edit_source' => $data['source'] ?? ''
        ];

        $result = FeatureController::updateFeature($postData, []);
        
        if ($result['success']) {
            echo json_encode(['message' => 'Feature updated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['errors' => $result['errors']]);
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            break;
        }

        $result = FeatureController::deleteFeature($id);
        
        if ($result['success']) {
            echo json_encode(['message' => 'Feature deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => $result['error']]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>