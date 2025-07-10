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

require_once '../config.php'; // MongoDB connection

$method = $_SERVER['REQUEST_METHOD'];

// Get ID from query string (e.g. ?id=...)
$id = isset($_GET['id']) && preg_match('/^[a-f\d]{24}$/i', $_GET['id']) ? $_GET['id'] : null;

// Read JSON body
function getInputData() {
    return json_decode(file_get_contents("php://input"), true);
}

// Format output
function formatFeature($f) {
    $f['_id'] = (string) $f['_id'];
    return $f;
}

switch ($method) {
    case 'GET':
        if ($id) {
            try {
                $feature = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
                if ($feature) {
                    echo json_encode(formatFeature($feature));
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Feature not found']);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid MongoDB ID']);
            }
        } else {
            $features = $collection->find([], ['sort' => ['created_at' => -1]]);
            $output = [];
            foreach ($features as $f) {
                $output[] = formatFeature($f);
            }
            echo json_encode($output);
        }
        break;

    case 'POST':
        $data = getInputData();
        if (!$data || !isset($data['system_name'], $data['module'], $data['description'], $data['client'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }

        $insert = [
            'system_name' => $data['system_name'],
            'module' => $data['module'],
            'description' => $data['description'],
            'client' => $data['client'],
            'sample_file' => $data['sample_file'] ?? '',
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ];

        $result = $collection->insertOne($insert);
        echo json_encode(['message' => 'Feature created', 'id' => (string) $result->getInsertedId()]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            break;
        }

        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid MongoDB ID']);
            break;
        }

        $data = getInputData();
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            break;
        }

        $updateData = [
            'system_name' => $data['system_name'] ?? '',
            'module' => $data['module'] ?? '',
            'description' => $data['description'] ?? '',
            'client' => $data['client'] ?? '',
            'sample_file' => $data['sample_file'] ?? ''
        ];

        try {
            $collection->updateOne(
                ['_id' => $objectId],
                ['$set' => $updateData]
            );
            echo json_encode(['message' => 'Feature updated']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'details' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            break;
        }

        try {
            $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            echo json_encode(['message' => 'Feature deleted']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
