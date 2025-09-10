<?php
require_once __DIR__ . '/FeatureController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Process the update through FeatureController
        $result = FeatureController::updateFeature($_POST, $_FILES);
        
        if ($result['success']) {
            // Redirect back with success message
            header('Location: index.php?success=Feature updated successfully');
            exit;
        } else {
            // Handle errors
            if (isset($result['errors']) && is_array($result['errors'])) {
                $errorMessage = implode(', ', $result['errors']);
            } elseif (isset($result['errors']['general'])) {
                $errorMessage = $result['errors']['general'];
            } else {
                $errorMessage = 'Failed to update feature';
            }
            
            header('Location: index.php?error=' . urlencode($errorMessage));
            exit;
        }
    } catch (Exception $e) {
        error_log("Error in process_edit.php: " . $e->getMessage());
        header('Location: index.php?error=An error occurred while updating the feature');
        exit;
    }
} else {
    // If not POST request, redirect to main page
    header('Location: index.php');
    exit;
}
?>