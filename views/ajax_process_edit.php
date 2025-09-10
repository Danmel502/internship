<?php
header('Content-Type: application/json');
require_once __DIR__ . '/FeatureController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = FeatureController::updateFeature($_POST, $_FILES);
        echo json_encode($result);
    } catch (Exception $e) {
        error_log("Error in ajax_process_edit.php: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'An error occurred while updating the feature'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>