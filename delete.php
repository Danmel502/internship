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
            
            // ADDED: Check and update module system relationships after bulk delete
            if ($result['success']) {
                updateModuleSystemRelationships();
            }
            
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
        
        // ADDED: Check and update module system relationships after single delete
        updateModuleSystemRelationships();
        
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

// ADDED: Function to update module system relationships
function updateModuleSystemRelationships() {
    try {
        $db = Database::getInstance()->getDatabase();
        $modulesCollection = $db->getCollection('modules');
        $overallCollection = $db->getCollection('overall');
        
        // Get all modules
        $modulesCursor = $modulesCollection->find([]);
        
        foreach ($modulesCursor as $module) {
            if (!isset($module['id'])) continue;
            
            $moduleId = $module['id'];
            $moduleName = $module['name'] ?? '';
            $currentSystemName = $module['system_name'] ?? '';
            
            // Check if this module still has any features/dependencies
            $dependencyCount = $overallCollection->countDocuments(['module_id' => $moduleId]);
            
            if ($dependencyCount === 0) {
                // No more dependencies - check if we need to clear system relationship
                // Only clear if the module was previously linked to a system
                if (!empty($currentSystemName) && $currentSystemName !== 'Unknown System') {
                    
                    // Option 1: Clear system relationship completely (makes module independent)
                    $modulesCollection->updateOne(
                        ['id' => $moduleId],
                        ['$set' => [
                            'system_name' => '',
                            'system_name_id' => null,
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                    
                    error_log("Cleared system relationship for module '{$moduleName}' (ID: {$moduleId}) - no more dependencies");
                    
                    // Option 2: Alternative approach - mark as "No Current System" instead of clearing
                    // Uncomment this if you prefer to show "No Current System" instead of empty
                    /*
                    $modulesCollection->updateOne(
                        ['id' => $moduleId],
                        ['$set' => [
                            'system_name' => 'No Current System',
                            'system_name_id' => 0,
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                    */
                }
            } else {
                // Still has dependencies - ensure system relationship is maintained
                // Get the current system from existing features
                $currentFeature = $overallCollection->findOne(['module_id' => $moduleId]);
                
                if ($currentFeature && isset($currentFeature['system_name'])) {
                    $actualSystemName = $currentFeature['system_name'];
                    
                    // Update module if system name doesn't match
                    if ($currentSystemName !== $actualSystemName) {
                        // Find system_name_id from system_names collection
                        $systemNamesCollection = $db->getCollection('system_names');
                        $systemDoc = $systemNamesCollection->findOne(['name' => $actualSystemName]);
                        $systemNameId = $systemDoc ? $systemDoc['id'] : 0;
                        
                        $modulesCollection->updateOne(
                            ['id' => $moduleId],
                            ['$set' => [
                                'system_name' => $actualSystemName,
                                'system_name_id' => $systemNameId,
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                        
                        error_log("Updated system relationship for module '{$moduleName}' (ID: {$moduleId}) to '{$actualSystemName}'");
                    }
                }
            }
        }
        
        // Clear any cached data
        $_SESSION['last_data_update'] = time();
        
    } catch (Exception $e) {
        error_log("Error updating module system relationships: " . $e->getMessage());
    }
}

// Redirect back to main page
header("Location: index.php");
exit;
?>