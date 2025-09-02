<?php
/**
 * Main application entry point
 * Handles feature management CRUD operations and displays the main interface
 */

// Start session and load dependencies
session_start();
require_once 'controllers/FeatureController.php';

$successMessage = $_SESSION['success'] ?? null;
$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];

unset($_SESSION['success'], $_SESSION['errors'], $_SESSION['old']);


/**
 * Flash message handler
 */
class FlashMessage {
    public static function get(): array {
        $message = $_SESSION['success'] ?? $_SESSION['error'] ?? '';
        $type = isset($_SESSION['success']) ? 'success' : (isset($_SESSION['error']) ? 'danger' : '');
        
        // Clear messages after reading
        unset($_SESSION['success'], $_SESSION['error']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    public static function set(string $type, string $message): void {
        $_SESSION[$type] = $message;
    }
}

/**
 * Request handler for POST operations
 */
function handlePostRequest(): void {
    if (!isset($_POST) || empty($_POST)) {
        return;
    }
    
    try {
        if (isset($_POST['add'])) {
            FeatureController::addFeature($_POST, $_FILES);
            FlashMessage::set('success', 'Feature added successfully!');
        } elseif (isset($_POST['update_feature'])) {
            FeatureController::updateFeature($_POST, $_FILES);
            FlashMessage::set('success', 'Feature updated successfully!');
        }
    } catch (Exception $e) {
        $action = isset($_POST['add']) ? 'adding' : 'updating';
        FlashMessage::set('error', "Error {$action} feature: " . $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * Get pagination parameters
 */
function getPaginationParams(): array {
    $limit = 10;
    $page = max((int)($_GET['page'] ?? 1), 1);
    $skip = ($page - 1) * $limit;
    
    return compact('limit', 'page', 'skip');
}

/**
 * Get features based on search or pagination
 */
function getFeatures(array $pagination): array {
    $search = trim($_GET['search'] ?? '');
    
    if (!empty($search)) {
        return [
            'features' => FeatureController::searchFeatures($search, $pagination['limit'], $pagination['skip']),
            'totalPages' => 1, // Search doesn't use pagination
            'search' => $search
        ];
    }
    
    return [
        'features' => FeatureController::getFeatures($pagination['limit'], $pagination['skip']),
        'totalPages' => FeatureController::getTotalPages($pagination['limit']),
        'search' => ''
    ];
}

/**
 * Get application base path
 */
function getBasePath(): string {
    return rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $result = FeatureController::addFeature($_POST, $_FILES);

    if ($result['success']) {
        $_SESSION['success'] = "Feature added successfully!";
    } else {
        $_SESSION['errors'] = $result['errors'];
        $_SESSION['old'] = $_POST;
    }

    if (isset($_POST['update_feature'])) {
        $result = FeatureController::updateFeature($_POST, $_FILES);
        $_SESSION['success'] = $result['success'] ? "Feature updated successfully!" : "Error updating feature";
        if (!$result['success']) {
            $_SESSION['errors'] = $result['errors'];
        }
    } elseif (isset($_POST['delete'])) {
        $result = FeatureController::deleteFeature($_POST['delete_id']);
        $_SESSION['success'] = $result['success'] ? "Feature deleted successfully!" : "Error deleting feature";
    }

    header("Location: index.php");
    exit();
}


// Get flash messages
$flash = FlashMessage::get();

// Get pagination and features
$pagination = getPaginationParams();
$featureData = getFeatures($pagination);

// Extract variables for template
$message = $flash['message'];
$messageType = $flash['type'];
$features = $featureData['features'];
$totalPages = $featureData['totalPages'];
$search = $featureData['search'];
$page = $pagination['page'];
$basePath = getBasePath();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Track</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="bg-light">

    <?php include 'views/navbar.php'; ?>
    <?php include 'views/hero.php'; ?>

    <div class="container py-4">
        <!-- Flash Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <?php include 'views/add_form.php'; ?>
        <?php include 'views/feature_table.php'; ?>
    </div>

    <!-- Toast Notifications -->
    <div class="toast align-items-center text-white border-0 position-fixed bottom-0 end-0 m-4"
         id="toastMessage" role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 9999;">
        <div class="d-flex">
            <div class="toast-body" id="toastContent"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                    data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <!-- Modals (only show when not searching) -->
    <?php if (empty($search)): ?>
        <?php include 'views/modals.php'; ?>
    <?php endif; ?>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Application Configuration -->
    <script>
        const BASE_PATH = '<?= $basePath ?>';
        const CURRENT_PAGE = <?= $page ?>;
        const TOTAL_PAGES = <?= $totalPages ?>;
        const SEARCH_QUERY = '<?= htmlspecialchars($search) ?>';
    </script>
    
    <!-- Custom JavaScript -->
    <script src="js/main.js?v=<?= time() ?>"></script>

    <script> // REMOVE OR COMMENT OUT these conflicting parts from your main HTML JavaScript section:

$(document).ready(function() {
    'use strict';

    // Configuration
    const config = {
        maxFileSize: 5 * 1024 * 1024, // 5MB
        descriptionMaxLength: 1000,
        allowedFileTypes: ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'xlsx', 'xls']
    };

    // Update Select2 validation styling
    function updateSelect2Validation($select) {
        const $container = $select.next('.select2-container');
        const $selection = $container.find('.select2-selection');
        
        $selection.removeClass('is-invalid is-valid');
        
        if ($select.hasClass('is-invalid')) {
            $selection.addClass('is-invalid');
        } else if ($select.hasClass('is-valid')) {
            $selection.addClass('is-valid');
        }
    }
    
    // Character counter for description
    function updateCharCounter() {
        const $description = $('#description');
        const $counter = $('#desc-counter');
        const length = $description.val().length;
        const maxLength = config.descriptionMaxLength;
        
        $counter.text(length + '/' + maxLength);
        
        $counter.removeClass('text-warning text-danger');
        
        if (length > maxLength * 0.9) {
            $counter.addClass('text-danger');
        } else if (length > maxLength * 0.7) {
            $counter.addClass('text-warning');
        }
    }
    
    // File validation
    function validateFile(file) {
        if (!file) return true;
        
        // Check file size
        if (file.size > config.maxFileSize) {
            return 'File size must be less than 5MB';
        }
        
        // Check file type
        const extension = file.name.split('.').pop().toLowerCase();
        if (!config.allowedFileTypes.includes(extension)) {
            return 'File type not allowed';
        }
        
        return true;
    }
    
    // Form validation - MODIFIED to work with cascading dropdown
    function validateForm() {
        let isValid = true;
        const $form = $('#addFeatureForm');
        
        // Validate file upload OR URL (not both required)
        const $fileInput = $('#sample_file');
        const $urlInput = $('#file_url');
        const hasFile = $fileInput.val() && $fileInput.val().trim() !== '';
        const hasUrl = $urlInput.val() && $urlInput.val().trim() !== '';
        
        if (!hasFile && !hasUrl) {
            // Neither file nor URL provided
            if (!$fileInput.hasClass('d-none')) {
                $fileInput.addClass('is-invalid').removeClass('is-valid');
            }
            if (!$urlInput.hasClass('d-none')) {
                $urlInput.addClass('is-invalid').removeClass('is-valid');
            }
            isValid = false;
        } else {
            // At least one is provided
            $fileInput.removeClass('is-invalid');
            $urlInput.removeClass('is-invalid');
        }
        
        // Validate required fields (only enabled ones) - EXCLUDE cascading dropdowns
        $form.find('input[required], textarea[required]').each(function() {
            const $field = $(this);
            
            // Skip disabled fields and file inputs as we handle them separately
            // Also skip cascading dropdown fields as they're handled by cascading-dropdown.js
            if ($field.prop('disabled') || 
                $field.attr('type') === 'file' || 
                $field.attr('type') === 'url' ||
                $field.hasClass('select2-dropdown')) {
                return;
            }
            
            const value = $field.val();
            
            if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
                $field.addClass('is-invalid').removeClass('is-valid');
                isValid = false;
            } else {
                $field.addClass('is-valid').removeClass('is-invalid');
            }
        });
        
        return isValid;
    }
    
    // Initialize components
    updateCharCounter();
    
    // Event handlers
    $('#description').on('input', updateCharCounter);
    
    // Form submission - MODIFIED to use FormValidator from cascading-dropdown.js
    $('#addFeatureForm').on('submit', function(e) {
        e.preventDefault();
        
        // Use the cascading dropdown validation + our file validation
        const cascadingValid = window.FormValidator ? window.FormValidator.validateCascading() : true;
        const formValid = validateForm();
        
        if (!cascadingValid || !formValid) {
            $(this).addClass('was-validated');
            
            // Scroll to first invalid field
            const $firstInvalid = $(this).find('.is-invalid').first();
            if ($firstInvalid.length) {
                $firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $firstInvalid.focus();
            }
            
            return false;
        }
        
        // Show loading overlay
        $('#loadingOverlay').show();
        $(this).find('button[type="submit"]').prop('disabled', true);
        
        // Submit the form
        this.submit();
    });
    
    // Reset form handler
    $('#addFeatureForm').on('reset', function() {
        $(this).removeClass('was-validated');
        
        $('.select2-dropdown').val(null).trigger('change');
        $('.form-control, .form-select').removeClass('is-invalid is-valid');
        
        $('.select2-dropdown').each(function() {
            updateSelect2Validation($(this));
        });
        
        updateCharCounter();
    });
    
    // File input validation
    $('#sample_file').on('change', function() {
        const file = this.files[0];
        const validation = validateFile(file);
        
        if (validation !== true) {
            alert(validation);
            $(this).val('');
            $(this).addClass('is-invalid').removeClass('is-valid');
        } else if (file) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        }
    });
    
    // Real-time validation for text inputs (EXCLUDING cascading dropdowns)
    $('input[required], textarea[required]').not('.select2-dropdown').on('blur input', function() {
        const $this = $(this);
        
        // Skip file inputs
        if ($this.attr('type') === 'file' || $this.attr('type') === 'url') {
            return;
        }
        
        const value = $this.val();
        
        if (value === null || value.trim() === '') {
            $this.addClass('is-invalid').removeClass('is-valid');
        } else {
            $this.removeClass('is-invalid').addClass('is-valid');
        }
    });
    
    // Validate URL input
    $('#file_url').on('blur input', function () {
        const value = $(this).val().trim();
        const isValidUrl = /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test(value);

        if (value === '') {
            $(this).removeClass('is-valid').addClass('is-invalid');
        } else if (isValidUrl) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });
});
</script>
</body>
</html>