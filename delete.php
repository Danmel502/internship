<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/controllers/FeatureController.php';

// Handle JSON requests (bulk delete)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input && isset($input['action']) && $input['action'] === 'bulk_delete') {
        $ids = $input['ids'] ?? [];
        
        if (empty($ids) || !is_array($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No valid IDs provided']);
            exit;
        }
        
        try {
            $result = FeatureController::bulkDeleteFeatures($ids);
            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Bulk delete error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error occurred']);
        }
        exit;
    }
}

// Ensure only POST requests are processed for regular form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "❌ Invalid request method.";
    header("Location: index.php");
    exit;
}

// Check if required parameters are present (support both frontend formats)
if ((!isset($_POST['delete_feature']) || !isset($_POST['id'])) && 
    (!isset($_POST['delete']) || !isset($_POST['delete_id']))) {
    $_SESSION['error'] = "❌ Missing required parameters.";
    header("Location: index.php");
    exit;
}

// Get ID from either parameter format
$id = '';
if (isset($_POST['id'])) {
    $id = trim($_POST['id']);
} elseif (isset($_POST['delete_id'])) {
    $id = trim($_POST['delete_id']);
}

if (empty($id)) {
    $_SESSION['error'] = "❌ Invalid feature ID provided.";
    header("Location: index.php");
    exit;
}

try {
    // Use FeatureController for proper deletion with file cleanup
    $result = FeatureController::deleteFeature($id);
    
    if ($result['success']) {
        $_SESSION['success'] = "✅ Feature deleted successfully!";
    } else {
        // Handle specific error messages from FeatureController
        $errorMessage = isset($result['error']) ? $result['error'] : 'Unknown error occurred';
        $_SESSION['error'] = "❌ Deletion failed: " . $errorMessage;
    }
    
} catch (Exception $e) {
    // Catch any unexpected errors
    error_log("Unexpected error in delete.php: " . $e->getMessage());
    $_SESSION['error'] = "❌ An unexpected error occurred while deleting the feature.";
}

// Redirect back to main page
header("Location: index.php");
exit;
?>