<?php
// edit_modal.php - Updated with enhanced edit functionality
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controllers/FeatureController.php';

// Get the current feature data from the database
$currentFeatureData = null;
if (isset($_GET['edit_id']) || isset($feature['_id'])) {
    try {
        $db = Database::getInstance()->getDatabase();
        $collection = $db->getCollection('overall');
        
        $featureId = $_GET['edit_id'] ?? (string)$feature['_id'];
        $currentFeatureData = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($featureId)]);
        
    } catch (Exception $e) {
        error_log("Error loading feature data: " . $e->getMessage());
    }
}

// Use current feature data if available, otherwise fall back to the loop variable
$displayFeature = $currentFeatureData ?? $feature;

// Handle form submissions for editing features
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feature'])) {
    $editId = $_POST['edit_id'] ?? '';
    $systemName = trim($_POST['edit_system_name'] ?? '');
    $module = trim($_POST['edit_module'] ?? '');
    $featureName = trim($_POST['edit_feature'] ?? '');
    $client = trim($_POST['edit_client'] ?? '');
    $description = trim($_POST['edit_description'] ?? '');
    $source = trim($_POST['edit_source'] ?? '');
    
    // Handle file upload or URL
    $sampleFile = '';
    
    // Check if URL is provided and valid
    $fileUrl = trim($_POST['edit_file_url'] ?? '');
    if (!empty($fileUrl) && filter_var($fileUrl, FILTER_VALIDATE_URL)) {
        $sampleFile = $fileUrl;
    } 
    // Handle file upload
    else if (isset($_FILES['edit_sample_file']) && $_FILES['edit_sample_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['edit_sample_file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['edit_sample_file']['tmp_name'], $targetPath)) {
            $sampleFile = $targetPath;
        }
    }
    // Keep existing file if no new file/URL provided
    else {
        // We'll get the existing file path from the database in the update process
        $sampleFile = null; // This will indicate to keep the existing file
    }
    
    if (!empty($editId) && !empty($systemName) && !empty($module) && !empty($client) && !empty($description)) {
        try {
            $db = Database::getInstance()->getDatabase();
            $collection = $db->getCollection('overall');
            
            // Get the current feature to preserve file if not changed
            $currentFeature = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($editId)]);
            $currentFile = $currentFeature['sample_file'] ?? '';
            
            // Prepare update data
            $updateData = [
                'system_name' => $systemName,
                'module' => $module,
                'feature' => $featureName,
                'client' => $client,
                'description' => $description,
                'source' => $source,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Only update file if a new one was provided
            if ($sampleFile !== null) {
                $updateData['sample_file'] = !empty($sampleFile) ? $sampleFile : '';
                
                // Delete old file if it was a local upload and we're replacing it
                if (!empty($currentFile) && $sampleFile !== $currentFile && 
                    !filter_var($currentFile, FILTER_VALIDATE_URL) && 
                    file_exists($currentFile)) {
                    unlink($currentFile);
                }
            }
            
            // Update the feature
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($editId)],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                $message = 'Feature updated successfully';
                $messageType = 'success';
                
                // Clear cache for cascading dropdowns
                $_SESSION['last_data_update'] = time();
$_SESSION['feature_updated'] = true;
            } else {
                $message = 'No changes were made to the feature';
                $messageType = 'info';
            }
            
        } catch (Exception $e) {
            $message = 'Error updating feature: ' . $e->getMessage();
            $messageType = 'error';
            error_log("Feature update error: " . $e->getMessage());
        }
    } else {
        $message = 'Please fill all required fields';
        $messageType = 'error';
    }

    // ADD THIS SECTION AFTER YOUR EXISTING UPDATE LOGIC
    $oldModule = $currentFeature['module'] ?? '';
    $newModule = trim($_POST['edit_module'] ?? '');
    $systemName = trim($_POST['edit_system_name'] ?? '');
    
    // Check if module name was changed and we have system context
    if (!empty($oldModule) && !empty($newModule) && $oldModule !== $newModule && !empty($systemName)) {
        try {
            $modulesCollection = $db->getCollection('modules');
            $overallCollection = $db->getCollection('overall');
            
            // Check if the new module already exists for this system
            $existingModule = $modulesCollection->findOne([
                'name' => $newModule,
                'system_name' => $systemName,
                'is_active' => true
            ]);
            
            if (!$existingModule) {
                // Create the new module if it doesn't exist
                $lastModuleDoc = $modulesCollection->findOne([], ['sort' => ['id' => -1]]);
                $nextModuleId = ($lastModuleDoc['id'] ?? 0) + 1;
                
                $modulesCollection->insertOne([
                    'id' => $nextModuleId,
                    'name' => $newModule,
                    'description' => 'Created from feature edit',
                    'system_name' => $systemName,
                    'system_name_id' => 0, // You might need to look this up
                    'is_active' => true,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]);
                
                // Also update the overall collection for AJAX compatibility
                $overallCollection->updateMany(
                    [
                        'system_name' => $systemName,
                        'module' => $newModule
                    ],
                    ['$set' => [
                        'module_id' => $nextModuleId,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]],
                    ['upsert' => true]
                );
                
                $message .= ' New module created.';
            }
            
            // Update all features with the old module to use the new module
            $overallCollection->updateMany(
                [
                    'system_name' => $systemName,
                    'module' => $oldModule
                ],
                ['$set' => [
                    'module' => $newModule,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
        } catch (Exception $e) {
            error_log("Module update error: " . $e->getMessage());
            // Don't show this error to users as it doesn't affect the main feature update
        }
    }
    
    // Store message in session for display after redirect
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $messageType;
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Display flash message if exists
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_message_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
}
?>

<!-- Flash Messages -->
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'info' ? 'info' : 'danger'); ?> alert-dismissible fade show" id="flashMessage">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    
    <script>
    // Auto-hide flash message after 5 seconds
    setTimeout(function() {
        const alert = document.getElementById('flashMessage');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
    </script>
<?php endif; ?>

<div class="modal fade" id="editModal<?= htmlspecialchars((string)$feature['_id']) ?>" tabindex="-1"
     aria-labelledby="editModalLabel<?= htmlspecialchars((string)$feature['_id']) ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" enctype="multipart/form-data"
              onsubmit="return handleSaveEdit('<?= htmlspecialchars((string)$feature['_id']) ?>')">
              
            <input type="hidden" name="edit_id" value="<?= htmlspecialchars((string)$feature['_id']) ?>">

            <div class="modal-content border-0 shadow-lg">
                <!-- Header - Cleaner design -->
                <div class="modal-header bg-success text-white border-0 rounded-top-3 p-3">
                    <h5 class="modal-title fs-6 fw-semibold" id="editModalLabel<?= htmlspecialchars((string)$feature['_id']) ?>">
                        <i class="fas fa-edit me-2"></i>
                        Edit Feature – <?= htmlspecialchars($feature['system_name'] ?? 'N/A') ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white p-2" data-bs-dismiss="modal"
                            aria-label="Close"
                            onclick="handleCancelEdit('<?= htmlspecialchars((string)$feature['_id']) ?>')"></button>
                </div>

                <!-- Body - Improved spacing and cleaner design -->
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>AJAX Sync:</strong> Changes will automatically update in all cascading dropdowns.
                    </div>
                    
                    <div class="row g-3">
                        <!-- System Name - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1 fw-medium">System Name <span class="text-danger">*</span></label>
                            <select name="edit_system_name" 
                                    class="form-select select2-edit border-0 shadow-sm" 
                                    id="edit_system_name_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                    required>
                                <option value="">Select or type a system name...</option>
                                <?php
                                $currentSystemName = $feature['system_name'] ?? '';
                                foreach ($optionSets['system'] as $option):
                                    $selected = ($currentSystemName === $option) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <!-- Add current value if it's not in the list -->
                                <?php if ($currentSystemName && !in_array($currentSystemName, $optionSets['system'])): ?>
                                    <option value="<?= htmlspecialchars($currentSystemName, ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($currentSystemName, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Module - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1 fw-medium">Module <span class="text-danger">*</span></label>
                            <select name="edit_module" 
                                    class="form-select select2-edit border-0 shadow-sm" 
                                    id="edit_module_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                    required>
                                <option value="">Select or type a module...</option>
                                <?php
                                $currentModule = $feature['module'] ?? '';
                                $moduleOptions = [];
                                if ($controllerLoaded && class_exists('FeatureController')) {
                                    try {
                                        $moduleOptions = FeatureController::getDistinctValues('module') ?? [];
                                        $moduleOptions = array_filter($moduleOptions, function($value) {
                                            return !empty(trim($value));
                                        });
                                        sort($moduleOptions);
                                    } catch (Exception $e) {
                                        $moduleOptions = [];
                                    }
                                }
                                
                                foreach ($moduleOptions as $option):
                                    $selected = ($currentModule === $option) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <!-- Add current value if it's not in the list -->
                                <?php if ($currentModule && !in_array($currentModule, $moduleOptions)): ?>
                                    <option value="<?= htmlspecialchars($currentModule, ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($currentModule, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Feature - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1 fw-medium">Feature</label>
                            <select name="edit_feature" 
                                    class="form-select select2-edit border-0 shadow-sm" 
                                    id="edit_feature_<?= htmlspecialchars((string)$feature['_id']) ?>">
                                <option value="">Select or type a feature...</option>
                                <?php
                                $currentFeature = $feature['feature'] ?? '';
                                $featureOptions = [];
                                if ($controllerLoaded && class_exists('FeatureController')) {
                                    try {
                                        $featureOptions = FeatureController::getDistinctValues('feature') ?? [];
                                        $featureOptions = array_filter($featureOptions, function($value) {
                                            return !empty(trim($value));
                                        });
                                        sort($featureOptions);
                                    } catch (Exception $e) {
                                        $featureOptions = [];
                                    }
                                }
                                
                                foreach ($featureOptions as $option):
                                    $selected = ($currentFeature === $option) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <!-- Add current value if it's not in the list -->
                                <?php if ($currentFeature && !in_array($currentFeature, $featureOptions)): ?>
                                    <option value="<?= htmlspecialchars($currentFeature, ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($currentFeature, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Client - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1 fw-medium">Client <span class="text-danger">*</span></label>
                            <select name="edit_client" 
                                    class="form-select select2-edit border-0 shadow-sm" 
                                    id="edit_client_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                    required>
                                <option value="">Select or type a client...</option>
                                <?php
                                $currentClient = $feature['client'] ?? '';
                                $clientOptions = [];
                                if ($controllerLoaded && class_exists('FeatureController')) {
                                    try {
                                        $clientOptions = FeatureController::getDistinctValues('client') ?? [];
                                        $clientOptions = array_filter($clientOptions, function($value) {
                                            return !empty(trim($value));
                                        });
                                        sort($clientOptions);
                                    } catch (Exception $e) {
                                        $clientOptions = [];
                                    }
                                }
                                
                                foreach ($clientOptions as $option):
                                    $selected = ($currentClient === $option) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <!-- Add current value if it's not in the list -->
                                <?php if ($currentClient && !in_array($currentClient, $clientOptions)): ?>
                                    <option value="<?= htmlspecialchars($currentClient, ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($currentClient, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1 fw-medium">Description <span class="text-danger">*</span></label>
                            <textarea name="edit_description" class="form-control border-0 shadow-sm" rows="3" required><?= htmlspecialchars($feature['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Source - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1 fw-medium">Source</label>
                            <select name="edit_source" 
                                    class="form-select select2-edit border-0 shadow-sm" 
                                    id="edit_source_<?= htmlspecialchars((string)$feature['_id']) ?>">
                                <option value="">Select or type a source...</option>
                                <?php
                                $currentSource = $feature['source'] ?? '';
                                $sourceOptions = [];
                                if ($controllerLoaded && class_exists('FeatureController')) {
                                    try {
                                        $sourceOptions = FeatureController::getDistinctValues('source') ?? [];
                                        $sourceOptions = array_filter($sourceOptions, function($value) {
                                            return !empty(trim($value));
                                        });
                                        sort($sourceOptions);
                                    } catch (Exception $e) {
                                        $sourceOptions = [];
                                    }
                                }
                                
                                foreach ($sourceOptions as $option):
                                    $selected = ($currentSource === $option) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <!-- Add current value if it's not in the list -->
                                <?php if ($currentSource && !in_array($currentSource, $sourceOptions)): ?>
                                    <option value="<?= htmlspecialchars($currentSource, ENT_QUOTES, 'UTF-8') ?>" selected>
                                        <?= htmlspecialchars($currentSource, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Sample File or URL -->
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1 fw-medium">
                                Sample File or URL
                                <small class="text-muted">(Choose one)</small>
                            </label>

                            <!-- Toggle Button Group - Improved styling -->
                            <div class="btn-group w-100 mb-3 shadow-sm" role="group">
                                <div class="btn-group" role="group" aria-label="Upload or URL Toggle">
    <button type="button" 
        id="uploadToggle_<?= htmlspecialchars((string)$feature['_id']) ?>" 
        class="btn btn-success text-white active">
        <i class="fas fa-upload me-1"></i> Upload File
    </button>

    <button type="button" 
        id="urlToggle_<?= htmlspecialchars((string)$feature['_id']) ?>" 
        class="btn btn-outline-success">
        <i class="fas fa-link me-1"></i> Add URL
    </button>
</div>


                            </div>

                            <!-- Current file display -->
                            <?php $fileUrl = $feature['sample_file'] ?? ''; ?>
                            <div id="current-file-wrapper-<?= htmlspecialchars((string)$feature['_id']) ?>"
                                 class="<?= empty($fileUrl) ? 'd-none' : '' ?> mb-2 p-2 bg-white rounded shadow-sm">
                                <?php if (!empty($fileUrl)): ?>
                                    <small class="text-muted d-block mb-1">Current file:</small>
                                    <?php if (filter_var($fileUrl, FILTER_VALIDATE_URL)): ?>
                                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="text-decoration-none text-success d-flex align-items-center">
                                            <i class="fas fa-external-link-alt me-1"></i>
                                            <span class="text-truncate"><?= htmlspecialchars($fileUrl) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="text-decoration-none text-success d-flex align-items-center">
                                            <i class="fas fa-file me-1"></i>
                                            <span class="text-truncate"><?= htmlspecialchars(basename($fileUrl)) ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- File Upload Input -->
                            <input type="file"
                                   id="edit_sample_file_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                   name="edit_sample_file"
                                   class="form-control mb-2 border-0 shadow-sm"
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.xlsx,.xls"
                                   aria-describedby="sample_file_help_<?= htmlspecialchars((string)$feature['_id']) ?>">

                            <!-- URL Input (Hidden by Default) -->
                            <input type="url"
                                   id="edit_file_url_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                   name="edit_file_url"
                                   class="form-control d-none border-0 shadow-sm"
                                   placeholder="Paste a file URL here (e.g., https://...)"
                                   value="<?= filter_var($fileUrl, FILTER_VALIDATE_URL) ? htmlspecialchars($fileUrl) : '' ?>"
                                   aria-describedby="file_url_help_<?= htmlspecialchars((string)$feature['_id']) ?>">

                            <!-- Error Feedback -->
                            <div id="file-error-<?= htmlspecialchars((string)$feature['_id']) ?>" 
                                 class="invalid-feedback d-none"></div>

                            <div id="sample_file_help_<?= htmlspecialchars((string)$feature['_id']) ?>" class="form-text small mt-1">
                                <i class="fas fa-info-circle text-success me-1"></i>
                                Supported: JPG, PNG, GIF, PDF, DOC, DOCX, TXT, XLSX, XLS (Max: 5MB) or paste a valid public file URL.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer - Cleaner design -->
                <div class="modal-footer px-4 py-3 bg-light border-0 rounded-bottom-3">
                    <button type="submit" name="update_feature" class="btn btn-success px-4 py-2 shadow-sm">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 shadow-sm" data-bs-dismiss="modal"
                            onclick="handleCancelEdit('<?= htmlspecialchars((string)$feature['_id']) ?>')">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    /* Fix toggle button styling */
.btn-group .btn-success.text-white {
    background-color: #198754 !important;
    border-color: #198754 !important;
    color: white !important;
}

.btn-group .btn-outline-success {
    background-color: transparent !important;
    border-color: #198754 !important;
    color: #198754 !important;
}

.btn-group .btn-outline-success:hover {
    background-color: #198754 !important;
    color: white !important;
}


/* Fix Select2 dropdown hover colors - same as edit modal */
.select2-container--bootstrap-5 .select2-results__option--selected {
    background-color: #198754 !important;
    color: white !important;
}

.select2-container--bootstrap-5 .select2-results__option--highlighted {
    background-color: #6c757d !important; /* Gray instead of green */
    color: white !important;
}

.select2-container--bootstrap-5 .select2-results__option--selected.select2-results__option--highlighted {
    background-color: #198754 !important; /* Keep green even when hovered */
    color: white !important;
}

/* Hover effects for dropdown items */
.select2-container--bootstrap-5 .select2-results__option:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
}
    /* Fix Add URL active state */
#urlToggle_[id].active,
#urlToggle_[id]:active {
    background-color: #198754 !important; /* Bootstrap success green */
    color: #fff !important; /* White text */
    border-color: #198754 !important;
}

/* Additional custom styles for a cleaner look */
.modal-content {
    border: none;
    border-radius: 12px;
}

.form-control, .form-select {
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 0.9rem;
}

.form-label {
    font-size: 0.85rem;
    letter-spacing: 0.3px;
}

.select2-container .select2-selection--single {
    height: 42px;
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
    border-radius: 8px;
}

.select2-container .select2-selection--single .select2-selection__rendered {
    line-height: 42px;
    padding-left: 15px;
}

.select2-container--bootstrap-5 .select2-dropdown {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-radius: 8px;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}

.btn-group .btn:last-child {
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
}

.text-truncate {
    max-width: 200px;
}
</style>

<script>
    document.querySelectorAll('[id^="uploadToggle_"]').forEach(btn => {
    btn.addEventListener('click', function() {
        this.classList.add('btn-success', 'text-white');
        this.classList.remove('btn-outline-success');

        let urlBtn = document.getElementById(this.id.replace('uploadToggle_', 'urlToggle_'));
        urlBtn.classList.add('btn-outline-success');
        urlBtn.classList.remove('btn-success', 'text-white');
    });
});

document.querySelectorAll('[id^="urlToggle_"]').forEach(btn => {
    btn.addEventListener('click', function() {
        this.classList.add('btn-success', 'text-white');
        this.classList.remove('btn-outline-success');

        let uploadBtn = document.getElementById(this.id.replace('urlToggle_', 'uploadToggle_'));
        uploadBtn.classList.add('btn-outline-success');
        uploadBtn.classList.remove('btn-success', 'text-white');
    });
});

// Initialize Select2 for edit modals when they are shown
$(document).on('shown.bs.modal', '[id^="editModal"]', function () {
    const modalId = $(this).attr('id');
    const featureId = modalId.replace('editModal', '');
    
    // Initialize Select2 for all dropdowns in this modal
    $(this).find('.select2-edit').each(function() {
        const $select = $(this);
        
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        
        $select.select2({
            dropdownParent: $(this).closest('.modal'),
            theme: 'bootstrap-5',
            width: '100%',
            tags: true,
            tokenSeparators: [','],
            createTag: function (params) {
                const term = $.trim(params.term);
                if (term === '') {
                    return null;
                }
                return {
                    id: term,
                    text: term,
                    newTag: true
                };
            },
            templateResult: function (data) {
                const $result = $('<span></span>');
                $result.text(data.text);
                if (data.newTag) {
                    $result.append(' <small class="text-muted">(new)</small>');
                }
                return $result;
            }
        });
    });

    // Initialize file/URL toggle for this modal
    const uploadToggle = $(`#uploadToggle_${featureId}`);
    const urlToggle = $(`#urlToggle_${featureId}`);
    const fileInput = $(`#edit_sample_file_${featureId}`);
    const urlInput = $(`#edit_file_url_${featureId}`);

    // Set initial state based on current file
    const currentUrl = urlInput.val().trim();
    if (currentUrl) {
        // URL mode - show URL input, hide file input
        uploadToggle.removeClass('active btn-success').addClass('btn-outline-success');
        urlToggle.removeClass('btn-outline-success').addClass('active btn-success');
        fileInput.addClass('d-none').prop('disabled', true);
        urlInput.removeClass('d-none').prop('disabled', false);
    } else {
        // File mode (default) - show file input, hide URL input
        uploadToggle.removeClass('btn-outline-success').addClass('active btn-success');
        urlToggle.removeClass('active btn-success').addClass('btn-outline-success');
        fileInput.removeClass('d-none').prop('disabled', false);
        urlInput.addClass('d-none').prop('disabled', true);
    }

    // Upload toggle handler
    uploadToggle.off('click').on('click', function() {
        $(this).removeClass('btn-outline-success').addClass('active btn-success');
        urlToggle.removeClass('active btn-success').addClass('btn-outline-success');
        
        fileInput.removeClass('d-none').prop('disabled', false);
        urlInput.addClass('d-none').prop('disabled', true).val('').removeClass('is-valid is-invalid');
    });

    // URL toggle handler
    urlToggle.off('click').on('click', function() {
        $(this).removeClass('btn-outline-success').addClass('active btn-success');
        uploadToggle.removeClass('active btn-success').addClass('btn-outline-success');
        
        urlInput.removeClass('d-none').prop('disabled', false);
        fileInput.addClass('d-none').prop('disabled', true).val('').removeClass('is-valid is-invalid');
    });

    // URL validation for edit modal
    urlInput.off('blur input').on('blur input', function() {
        const value = $(this).val().trim();
        if ($(this).hasClass('d-none')) return; // Skip if hidden
        
        const isValidUrl = /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test(value);
        
        if (value === '') {
            $(this).removeClass('is-valid is-invalid');
        } else if (isValidUrl) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });
});

// Clean up Select2 when modal is hidden
$(document).on('hidden.bs.modal', '[id^="editModal"]', function () {
    $(this).find('.select2-edit').each(function() {
        if ($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
        }
    });
});

// Form validation for edit modals
function handleSaveEdit(featureId) {
    const fileInput = $(`#edit_sample_file_${featureId}`);
    const urlInput = $(`#edit_file_url_${featureId}`);
    const currentFileWrapper = $(`#current-file-wrapper-${featureId}`);
    
    const hasFile = fileInput.val() && !fileInput.prop('disabled');
    const hasUrl = urlInput.val().trim() && !urlInput.prop('disabled');
    const hasCurrentFile = !currentFileWrapper.hasClass('d-none');
    
    // Validate URL if provided
    if (hasUrl) {
        const isValidUrl = /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test(urlInput.val().trim());
        if (!isValidUrl) {
            alert('Please provide a valid URL.');
            urlInput.focus();
            return false;
        }
    }

    const systemName = $('#edit_system_name_' + featureId).val();
    const oldModule = '<?= addslashes($feature["module"] ?? "") ?>';
    const newModule = $('#edit_module_' + featureId).val();
    
    if (systemName && oldModule && newModule && oldModule !== newModule) {
        sessionStorage.setItem('lastChangedData', JSON.stringify({
            system_name: systemName,
            old_module: oldModule,
            new_module: newModule
        }));
    }
    
    return true;
}

function handleCancelEdit(featureId) {
    // Reset form and validation states
    $(`#editModal${featureId} form`)[0].reset();
    $(`#editModal${featureId} .is-valid, #editModal${featureId} .is-invalid`).removeClass('is-valid is-invalid');
}
</script>

<script>
// Set flag when feature is updated successfully
<?php if ($messageType === 'success'): ?>
sessionStorage.setItem('dataUpdated', 'true');
<?php endif; ?>
</script>

<script>// Add this to your edit modal JavaScript
function validateSystemModulePair(systemName, moduleName, featureId) {
    if (!systemName || !moduleName) return true;
    
    // Make AJAX call to validate the pair
    fetch('controllers/validate_module_system.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            system_name: systemName,
            module_name: moduleName
        })
    })
    .then(response => response.json())
    .then(data => {
        const moduleSelect = $(`#edit_module_${featureId}`);
        const systemSelect = $(`#edit_system_name_${featureId}`);
        
        if (data.isValid) {
            moduleSelect.removeClass('is-invalid').addClass('is-valid');
            systemSelect.removeClass('is-invalid').addClass('is-valid');
        } else {
            moduleSelect.removeClass('is-valid').addClass('is-invalid');
            systemSelect.removeClass('is-valid').addClass('is-invalid');
            
            // Show warning
            alert('Warning: This module is not normally associated with the selected system. ' +
                  'This may indicate the module was moved or the relationship needs updating.');
        }
    })
    .catch(error => {
        console.error('Validation error:', error);
    });
}</script>