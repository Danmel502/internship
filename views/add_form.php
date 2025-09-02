<?php
/**
 * Add Feature Form View
 * Displays form for adding new features with validation and Select2 integration
 */

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Controller loading with proper error handling
 */
function loadFeatureController(): bool {
    $possiblePaths = [
        __DIR__ . '/../controllers/FeatureController.php',
        __DIR__ . '/../FeatureController.php',
        __DIR__ . '/../../controllers/FeatureController.php',
        dirname(__DIR__) . '/controllers/FeatureController.php',
        dirname(__DIR__) . '/FeatureController.php'
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    error_log("FeatureController not found at any expected location.");
    return false;
}

/**
 * Form validation helper functions
 */
function hasValidationError(string $field, array $errors): bool {
    return isset($errors[$field]);
}

function getValidationError(string $field, array $errors): string {
    return $errors[$field] ?? '';
}

function getFieldClass(string $field, array $errors): string {
    return hasValidationError($field, $errors) ? 'is-invalid' : '';
}

function getOldValue(string $field, array $old): string {
    return htmlspecialchars($old[$field] ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Render select options with proper escaping - ONLY FOR SYSTEM NAME
 */
function renderSelectOptions(array $options, string $fieldName, array $oldValues = []): string {
    $html = '<option value="">Select or type a ' . htmlspecialchars($fieldName) . '...</option>';
    $oldValue = $oldValues[$fieldName] ?? null;

    foreach ($options as $option) {
        $selected = ($oldValue === $option) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>';
        $html .= htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
        $html .= '</option>';
    }

    // This part is crucial for allowing new, user-typed values to be selected
    if ($oldValue && !in_array($oldValue, $options)) {
        $html .= '<option value="' . htmlspecialchars($oldValue, ENT_QUOTES, 'UTF-8') . '" selected>';
        $html .= htmlspecialchars($oldValue, ENT_QUOTES, 'UTF-8');
        $html .= '</option>';
    }

    return $html;
}

// Initialize variables
$controllerLoaded = loadFeatureController();
$old = $_SESSION['old'] ?? [];
$validationErrors = $_SESSION['errors'] ?? [];
$successMessage = $_SESSION['success'] ?? null;

// Initialize option sets - ONLY FETCH SYSTEM NAMES
$optionSets = [
    'system' => []
];

// Fetch options from database or use fallbacks
if ($controllerLoaded && class_exists('FeatureController')) {
    try {
        $optionSets['system'] = FeatureController::getDistinctValues('system_name') ?? [];
    } catch (Exception $e) {
        error_log("Error fetching options: " . $e->getMessage());
        $optionSets['system'] = [];
    }
}

// Sort system options alphabetically and filter empty values
$optionSets['system'] = array_filter($optionSets['system'], function($value) {
    return !empty(trim($value));
});
sort($optionSets['system']);

// Clean up session data at the end
$cleanupSession = function() {
    unset($_SESSION['old'], $_SESSION['errors'], $_SESSION['success']);
};

// Register shutdown function to clean up session
register_shutdown_function($cleanupSession);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Feature</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- Select2 Bootstrap theme -->
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <style>
        .select2-container {
            width: 100% !important;
        }

        .select2-container--bootstrap-5 .select2-selection.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .select2-container--bootstrap-5 .select2-selection.is-valid {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            z-index: 9999;
        }

        .select2-container + .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }

        .char-counter {
            font-size: 0.875em;
            color: #6c757d;
            float: right;
        }

        .char-counter.text-warning {
            color: #ffc107 !important;
        }

        .char-counter.text-danger {
            color: #dc3545 !important;
        }

        .form-text {
            font-size: 0.875em;
            color: #6c757d;
        }

        .required-field {
            color: #dc3545;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 9999;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
        }

        /* Base toggle button style */
        .toggle-btn {
          background-color: white;
          color: #198754;
          border: 1px solid #198754;
        }

        /* Active button */
        .toggle-btn.active-toggle {
          background-color: #198754;
          color: white;
          border: 1px solid #198754;
        }

        /* Remove focus outline and blue box shadow */
        .toggle-btn:focus,
        .toggle-btn:active {
          outline: none !important;
          box-shadow: none !important;
        }

        /* Fix hover text color for outline buttons */
        .btn-outline-success:hover {
          color: white !important;
        }

        /* Custom styles for collapsible button */
        .add-feature-trigger {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 12px;
            padding: 20px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .add-feature-trigger:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            transform: translateY(-2px);
            color: white;
        }

        .add-feature-trigger:active {
            transform: translateY(0);
        }

        .add-feature-trigger::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .add-feature-trigger:hover::before {
            left: 100%;
        }

        .collapse-icon {
            transition: transform 0.3s ease;
        }

        .add-feature-trigger[aria-expanded="true"] .collapse-icon {
            transform: rotate(180deg);
        }

        /* Form container styling */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 0;
        }

        .form-content {
            padding: 30px;
            background: white;
            border-radius: 12px;
        }

        /* Animation for smooth collapse */
        .collapsing {
            transition: height 0.35s ease;
        }

        .collapse.show {
            display: block;
        }

        /* Status indicator */
        .form-status {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            opacity: 0.8;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <!-- Collapsible Trigger Button (Always Visible) -->
    <div class="card shadow-sm mb-3">
        <button class="btn add-feature-trigger w-100" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#addFeatureFormCollapse" 
                aria-expanded="false" 
                aria-controls="addFeatureFormCollapse">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-plus-circle me-3"></i>
                    <span>Add New Feature</span>
                </div>
                <div class="d-flex align-items-center">
                    <span class="form-status me-3">Click to expand form</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </div>
            </div>
        </button>
    </div>

    <!-- Collapsible Form Container (Separate Card) -->
    <div class="collapse mb-5" id="addFeatureFormCollapse">
        <div class="card shadow-sm form-container">
            <div class="form-content">
                <div class="d-flex align-items-center mb-4">
                    <h4 class="mb-0">Add New Feature</h4>
                    <span class="badge bg-primary ms-2">Required Fields</span>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($validationErrors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($validationErrors as $field => $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="addFeatureForm" method="POST" action="index.php" enctype="multipart/form-data" novalidate>
                    <div class="row g-3">

                        <!-- System Name Field - ONLY ONE WITH OPTIONS -->
                        <div class="col-md-6">
                            <label for="system_name" class="form-label">
                                System Name <span class="required-field">*</span>
                            </label>
                            <select id="system_name" name="system_name"
                                    class="form-select <?= getFieldClass('system_name', $validationErrors) ?>"
                                    required aria-describedby="system_name_help">
                                <option value="">Select or type a system name...</option>
                                <!-- ADD THIS: Load existing system names from database -->
                                <?php foreach ($optionSets['system'] as $systemName): ?>
                                    <option value="<?= htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8') ?>" 
                                            <?= (isset($old['system_name']) && $old['system_name'] === $systemName) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <!-- Add old value if it doesn't exist in database options -->
                                <?php if (!empty($old['system_name']) && !in_array($old['system_name'], $optionSets['system'])): ?>
                                    <option value="<?= htmlspecialchars($old['system_name'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($old['system_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if (hasValidationError('system_name', $validationErrors)): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars(getValidationError('system_name', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div id="system_name_help" class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Select or type a system name. You can create new entries.
                            </div>
                        </div>
                        
                        <!-- Module Field - EMPTY FOR CASCADING -->
                        <div class="col-md-6">
                            <label for="module" class="form-label">
                                Module <span class="required-field">*</span>
                            </label>
                            <select id="module" name="module" class="form-select <?= getFieldClass('module', $validationErrors) ?>" required aria-describedby="module_help">
                                <option value="">Select or type a module...</option>
                                <?php if (!empty($old['module'])): ?>
                                    <option value="<?= htmlspecialchars($old['module'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($old['module'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if (hasValidationError('module', $validationErrors)): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars(getValidationError('module', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div id="module_help" class="form-text">
                                <i class="fas fa-lock text-muted"></i> 
                                <span class="text-muted">Please select System Name first to enable this field</span>
                            </div>
                        </div>

                        <!-- Feature Field - EMPTY FOR CASCADING -->
                        <div class="col-md-6">
                            <label for="feature" class="form-label">
                                Feature <span class="required-field">*</span>
                            </label>
                            <select id="feature" name="feature" class="form-select <?= getFieldClass('feature', $validationErrors) ?>" required aria-describedby="feature_help">
                                <option value="">Select or type a feature...</option>
                                <?php if (!empty($old['feature'])): ?>
                                    <option value="<?= htmlspecialchars($old['feature'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($old['feature'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if (hasValidationError('feature', $validationErrors)): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars(getValidationError('feature', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div id="feature_help" class="form-text">
                                <i class="fas fa-lock text-muted"></i> 
                                <span class="text-muted">Please select Module first to enable this field</span>
                            </div>
                        </div>

                        <!-- Client Field - EMPTY FOR CASCADING -->
                        <div class="col-md-6">
                            <label for="client" class="form-label">
                                Client <span class="required-field">*</span>
                            </label>
                            <select id="client" name="client" class="form-select <?= getFieldClass('client', $validationErrors) ?>" required aria-describedby="client_help">
                                <option value="">Select or type a client...</option>
                                <?php if (!empty($old['client'])): ?>
                                    <option value="<?= htmlspecialchars($old['client'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($old['client'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if (hasValidationError('client', $validationErrors)): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars(getValidationError('client', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div id="client_help" class="form-text">
                                <i class="fas fa-lock text-muted"></i> 
                                <span class="text-muted">Please select Feature first to enable this field</span>
                            </div>
                        </div>

                        <!-- Description Field -->
                        <div class="col-12">
                            <label for="description" class="form-label">
                                Description <span class="required-field">*</span>
                            </label>
                            <textarea id="description" 
                                      name="description"
                                      class="form-control <?= getFieldClass('description', $validationErrors) ?>"
                                      rows="4"
                                      placeholder="Describe the feature in detail..."
                                      required
                                      maxlength="1000"
                                      aria-describedby="description_help desc-counter"><?= getOldValue('description', $old) ?></textarea>
                            <?php if (hasValidationError('description', $validationErrors)): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars(getValidationError('description', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div id="description_help" class="form-text d-flex justify-content-between">
                                <span>Provide a clear and detailed description of the feature functionality.</span>
                                <span id="desc-counter" class="char-counter">0/1000</span>
                            </div>
                        </div>

                        <!-- Source Field - EMPTY FOR CASCADING -->
                        <div class="col-md-6">
                            <label for="source" class="form-label">
                                Source <span class="required-field">*</span>
                            </label>
                            <select id="source" name="source" class="form-select <?= getFieldClass('source', $validationErrors) ?>" required aria-describedby="source_help">
                                <option value="">Select or type a source...</option>
                                <?php if (!empty($old['source'])): ?>
                                    <option value="<?= htmlspecialchars($old['source'], ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($old['source'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if (hasValidationError('source', $validationErrors)): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars(getValidationError('source', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div id="source_help" class="form-text">
                                <i class="fas fa-lock text-muted"></i> 
                                <span class="text-muted">Please select Client first to enable this field</span>
                            </div>
                        </div>

                        <!-- File Upload or URL Field -->
                        <div class="col-md-6">
                            <label class="form-label">
                                File Upload or URL <span class="required-field">*</span>
                                <small class="text-muted">(Choose one)</small>
                            </label>

                            <!-- Toggle Button Group -->
                            <div class="mb-3">
                                <button type="button" id="uploadToggle" class="btn btn-success text-white me-2 active">Upload File</button>
                                <button type="button" id="urlToggle" class="btn btn-outline-success text-success">Add URL</button>
                            </div>

                            <!-- File Upload Input -->
                            <input type="file"
                                   id="sample_file"
                                   name="sample_file"
                                   class="form-control mb-2 <?= getFieldClass('sample_file', $validationErrors) ?>"
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.xlsx,.xls"
                                   aria-describedby="sample_file_help">

                            <!-- URL Input (Hidden by Default) -->
                            <input type="url"
                                   id="file_url"
                                   name="file_url"
                                   class="form-control d-none <?= getFieldClass('file_url', $validationErrors) ?>"
                                   placeholder="Paste a file URL here (e.g., https://...)"
                                   aria-describedby="file_url_help">

                            <!-- Error Feedback -->
                            <?php if (hasValidationError('sample_file', $validationErrors) || hasValidationError('file_url', $validationErrors)): ?>
                                <div class="invalid-feedback d-block">
                                    <?= htmlspecialchars(getValidationError('sample_file', $validationErrors) ?: getValidationError('file_url', $validationErrors), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <div id="sample_file_help" class="form-text">
                                <i class="fas fa-upload"></i>
                                Supported: JPG, PNG, GIF, PDF, DOC, DOCX, TXT, XLSX, XLS (Max: 5MB) or paste a valid public file URL.
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-12">
                            <hr class="my-4">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-undo"></i> Reset Form
                                </button>
                                <button type="submit" name="add" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Feature
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-3">Processing your request...</div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Pass PHP old values to JavaScript for cascading dropdown restoration
window.phpOldValues = <?= json_encode($old, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<script src="js/cascading-dropdown.js"></script>

<script>
$(document).ready(function() {
    'use strict';

    // Character counter for description only
    function updateCharCounter() {
        const $description = $('#description');
        const $counter = $('#desc-counter');
        const length = $description.val().length;
        const maxLength = 1000;
        
        $counter.text(length + '/' + maxLength);
        
        $counter.removeClass('text-warning text-danger');
        
        if (length > maxLength * 0.9) {
            $counter.addClass('text-danger');
        } else if (length > maxLength * 0.7) {
            $counter.addClass('text-warning');
        }
    }

    // File upload toggle functionality
    $('#uploadToggle').on('click', function() {
        $(this).addClass('active-toggle').removeClass('btn-outline-success').addClass('btn-success');
        $('#urlToggle').removeClass('active-toggle').removeClass('btn-success').addClass('btn-outline-success');
        $('#sample_file').removeClass('d-none');
        $('#file_url').addClass('d-none').val('');
    });

    $('#urlToggle').on('click', function() {
        $(this).addClass('active-toggle').removeClass('btn-outline-success').addClass('btn-success');
        $('#uploadToggle').removeClass('active-toggle').removeClass('btn-success').addClass('btn-outline-success');
        $('#file_url').removeClass('d-none');
        $('#sample_file').addClass('d-none').val('');
    });

    // Update button text when form is expanded/collapsed
    const $collapseBtn = $('.add-feature-trigger');
    const $formStatus = $('.form-status');
    
    $('#addFeatureFormCollapse').on('show.bs.collapse', function () {
        $formStatus.text('Form expanded - Fill out the fields below');
    });

    $('#addFeatureFormCollapse').on('hide.bs.collapse', function () {
        $formStatus.text('Click to expand form');
    });

    // Initialize
    updateCharCounter();
    $('#description').on('input', updateCharCounter);
});
</script>
</body>
</html>