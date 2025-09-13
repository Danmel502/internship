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

// ENHANCED: Function to update all entity relationships after deletion
function updateModuleSystemRelationships() {
    try {
        $db = Database::getInstance()->getDatabase();
        $modulesCollection = $db->getCollection('modules');
        $systemNamesCollection = $db->getCollection('system_names');
        $clientsCollection = $db->getCollection('clients');
        $sourcesCollection = $db->getCollection('sources');
        $overallCollection = $db->getCollection('overall');
        
        // 1. Handle Module relationships
        $modulesCursor = $modulesCollection->find([]);
        
        foreach ($modulesCursor as $module) {
            if (!isset($module['id'])) continue;
            
            $moduleId = $module['id'];
            $moduleName = $module['name'] ?? '';
            $currentSystemName = $module['system_name'] ?? '';
            
            // Check if this module still has any features/dependencies
            $dependencyCount = $overallCollection->countDocuments(['module_id' => $moduleId]);
            
            if ($dependencyCount === 0) {
                // No more dependencies - clear system relationship (make independent)
                if (!empty($currentSystemName) && $currentSystemName !== 'Unknown System') {
                    $modulesCollection->updateOne(
                        ['id' => $moduleId],
                        ['$set' => [
                            'system_name' => 'Independent Module',
                            'system_name_id' => null,
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                    
                    error_log("Made module '{$moduleName}' independent - no more dependencies");
                }
            }
        }
        
        // 2. Handle System Name relationships
        $systemNamesCursor = $systemNamesCollection->find([]);
        
        foreach ($systemNamesCursor as $systemName) {
            if (!isset($systemName['id'])) continue;
            
            $systemNameId = $systemName['id'];
            $systemNameValue = $systemName['name'] ?? '';
            
            // Check if this system name still has dependencies in overall collection
            $systemDependencyCount = $overallCollection->countDocuments([
                'system_name' => $systemNameValue
            ]);
            
            // Also check if it has modules assigned to it
            $moduleDependencyCount = $modulesCollection->countDocuments([
                'system_name_id' => $systemNameId,
                'is_active' => true
            ]);
            
            // If no dependencies in either overall or modules collections
            if ($systemDependencyCount === 0 && $moduleDependencyCount === 0) {
                // Mark system name as having no current dependencies
                $systemNamesCollection->updateOne(
                    ['id' => $systemNameId],
                    ['$set' => [
                        'has_dependencies' => false,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                
                error_log("System name '{$systemNameValue}' marked as having no dependencies");
            } else {
                // Ensure it's marked as having dependencies
                $systemNamesCollection->updateOne(
                    ['id' => $systemNameId],
                    ['$set' => [
                        'has_dependencies' => true,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }
        }
        
        // 3. Handle Client relationships
        $clientsCursor = $clientsCollection->find([]);
        
        foreach ($clientsCursor as $client) {
            if (!isset($client['id'])) continue;
            
            $clientId = $client['id'];
            $clientName = $client['name'] ?? '';
            
            // Check if this client still has dependencies in overall collection
            $clientDependencyCount = $overallCollection->countDocuments([
                'client' => $clientName
            ]);
            
            if ($clientDependencyCount === 0) {
                // Mark client as having no current dependencies
                $clientsCollection->updateOne(
                    ['id' => $clientId],
                    ['$set' => [
                        'has_dependencies' => false,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                
                error_log("Client '{$clientName}' marked as having no dependencies");
            } else {
                // Ensure it's marked as having dependencies
                $clientsCollection->updateOne(
                    ['id' => $clientId],
                    ['$set' => [
                        'has_dependencies' => true,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }
        }
        
        // 4. Handle Source relationships
        $sourcesCursor = $sourcesCollection->find([]);
        
        foreach ($sourcesCursor as $source) {
            if (!isset($source['id'])) continue;
            
            $sourceId = $source['id'];
            $sourceName = $source['name'] ?? '';
            
            // Check if this source still has dependencies in overall collection
            $sourceDependencyCount = $overallCollection->countDocuments([
                'source' => $sourceName
            ]);
            
            if ($sourceDependencyCount === 0) {
                // Mark source as having no current dependencies
                $sourcesCollection->updateOne(
                    ['id' => $sourceId],
                    ['$set' => [
                        'has_dependencies' => false,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                
                error_log("Source '{$sourceName}' marked as having no dependencies");
            } else {
                // Ensure it's marked as having dependencies
                $sourcesCollection->updateOne(
                    ['id' => $sourceId],
                    ['$set' => [
                        'has_dependencies' => true,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }
        }
        
        // Clear any cached data
        $_SESSION['last_data_update'] = time();
        
        error_log("Completed relationship updates for all entities after feature deletion");
        
    } catch (Exception $e) {
        error_log("Error updating entity relationships: " . $e->getMessage());
    }
}

// Redirect back to main page
header("Location: index.php");
exit;
?>