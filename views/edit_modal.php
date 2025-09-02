<div class="modal fade" id="editModal<?= htmlspecialchars((string)$feature['_id']) ?>" tabindex="-1"
     aria-labelledby="editModalLabel<?= htmlspecialchars((string)$feature['_id']) ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="index.php" enctype="multipart/form-data"
              onsubmit="return handleSaveEdit('<?= htmlspecialchars((string)$feature['_id']) ?>')">
              
            <input type="hidden" name="edit_id" value="<?= htmlspecialchars((string)$feature['_id']) ?>">

            <div class="modal-content">
                <!-- Header -->
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="editModalLabel<?= htmlspecialchars((string)$feature['_id']) ?>">
                        Edit Feature – <?= htmlspecialchars($feature['system_name'] ?? 'N/A') ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"
                            onclick="handleCancelEdit('<?= htmlspecialchars((string)$feature['_id']) ?>')"></button>
                </div>

                <!-- Body -->
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- System Name - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label">System Name</label>
                            <select name="edit_system_name" 
                                    class="form-select select2-edit" 
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
                            <label class="form-label">Module</label>
                            <select name="edit_module" 
                                    class="form-select select2-edit" 
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
                            <label class="form-label">Feature</label>
                            <select name="edit_feature" 
                                    class="form-select select2-edit" 
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
                            <label class="form-label">Client</label>
                            <select name="edit_client" 
                                    class="form-select select2-edit" 
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
                            <label class="form-label">Description</label>
                            <textarea name="edit_description" class="form-control" rows="3" required><?= htmlspecialchars($feature['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Source - Select2 Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label">Source</label>
                            <select name="edit_source" 
                                    class="form-select select2-edit" 
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
                            <label class="form-label">
                                Sample File or URL <span class="required-field">*</span>
                                <small class="text-muted">(Choose one)</small>
                            </label>

                            <!-- Toggle Button Group -->
                            <div class="mb-3">
                                <button type="button" id="uploadToggle_<?= htmlspecialchars((string)$feature['_id']) ?>" 
                                        class="btn btn-success text-white me-2 active">Upload File</button>
                                <button type="button" id="urlToggle_<?= htmlspecialchars((string)$feature['_id']) ?>" 
                                        class="btn btn-outline-success text-success">Add URL</button>
                            </div>

                            <!-- Current file display -->
                            <?php $fileUrl = $feature['sample_file'] ?? ''; ?>
                            <div id="current-file-wrapper-<?= htmlspecialchars((string)$feature['_id']) ?>"
                                 class="<?= empty($fileUrl) ? 'd-none' : '' ?> mb-2">
                                <?php if (!empty($fileUrl)): ?>
                                    <small class="text-muted d-block mb-2">Current:</small>
                                    <?php if (filter_var($fileUrl, FILTER_VALIDATE_URL)): ?>
                                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="text-decoration-underline">
                                            <?= htmlspecialchars($fileUrl) ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="text-decoration-underline">
                                            <?= htmlspecialchars(basename($fileUrl)) ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- File Upload Input -->
                            <input type="file"
                                   id="edit_sample_file_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                   name="edit_sample_file"
                                   class="form-control mb-2"
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.xlsx,.xls"
                                   aria-describedby="sample_file_help_<?= htmlspecialchars((string)$feature['_id']) ?>">

                            <!-- URL Input (Hidden by Default) -->
                            <input type="url"
                                   id="edit_file_url_<?= htmlspecialchars((string)$feature['_id']) ?>"
                                   name="edit_file_url"
                                   class="form-control d-none"
                                   placeholder="Paste a file URL here (e.g., https://...)"
                                   value="<?= filter_var($fileUrl, FILTER_VALIDATE_URL) ? htmlspecialchars($fileUrl) : '' ?>"
                                   aria-describedby="file_url_help_<?= htmlspecialchars((string)$feature['_id']) ?>">

                            <!-- Error Feedback -->
                            <div id="file-error-<?= htmlspecialchars((string)$feature['_id']) ?>" 
                                 class="invalid-feedback d-none"></div>

                            <div id="sample_file_help_<?= htmlspecialchars((string)$feature['_id']) ?>" class="form-text">
                                <i class="fas fa-upload"></i>
                                Supported: JPG, PNG, GIF, PDF, DOC, DOCX, TXT, XLSX, XLS (Max: 5MB) or paste a valid public file URL.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer px-4 py-3">
                    <button type="submit" name="update_feature" class="btn btn-success">💾 Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            onclick="handleCancelEdit('<?= htmlspecialchars((string)$feature['_id']) ?>')">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
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
        uploadToggle.removeClass('active btn-success text-white').addClass('btn-outline-success text-success');
        urlToggle.removeClass('btn-outline-success text-success').addClass('active btn-success text-white');
        fileInput.addClass('d-none').prop('disabled', true);
        urlInput.removeClass('d-none').prop('disabled', false);
    } else {
        // File mode (default) - show file input, hide URL input
        uploadToggle.removeClass('btn-outline-success text-success').addClass('active btn-success text-white');
        urlToggle.removeClass('active btn-success text-white').addClass('btn-outline-success text-success');
        fileInput.removeClass('d-none').prop('disabled', false);
        urlInput.addClass('d-none').prop('disabled', true);
    }

    // Upload toggle handler
    uploadToggle.off('click').on('click', function() {
        $(this).removeClass('btn-outline-success text-success').addClass('active btn-success text-white');
        urlToggle.removeClass('active btn-success text-white').addClass('btn-outline-success text-success');
        
        fileInput.removeClass('d-none').prop('disabled', false);
        urlInput.addClass('d-none').prop('disabled', true).val('').removeClass('is-valid is-invalid');
    });

    // URL toggle handler
    urlToggle.off('click').on('click', function() {
        $(this).removeClass('btn-outline-success text-success').addClass('active btn-success text-white');
        uploadToggle.removeClass('active btn-success text-white').addClass('btn-outline-success text-success');
        
        urlInput.removeClass('d-none').prop('disabled', false);
        fileInput.addClass('d-none').prop('disabled', true).val('').removeClass('is-valid is-invalid');
    });

    // URL validation for edit modal
    urlInput.off('blur input').on('blur input', function() {
        const value = $(this).val().trim();
        if ($(this).hasClass('d-none')) return; // Skip if hidden
        
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
    
    // Must have either new file/URL or existing file
    if (!hasFile && !hasUrl && !hasCurrentFile) {
        alert('Please provide either a file upload or a valid URL.');
        return false;
    }
    
    // Validate URL if provided
    if (hasUrl) {
        const isValidUrl = /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test(urlInput.val().trim());
        if (!isValidUrl) {
            alert('Please provide a valid URL.');
            urlInput.focus();
            return false;
        }
    }
    
    return true;
}

function handleCancelEdit(featureId) {
    // Reset form and validation states
    $(`#editModal${featureId} form`)[0].reset();
    $(`#editModal${featureId} .is-valid, #editModal${featureId} .is-invalid`).removeClass('is-valid is-invalid');
}
</script>