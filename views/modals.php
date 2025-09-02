<!-- Edit Modal for Feature ID: <?= (string)$feature['_id'] ?> -->
<div class="modal fade" id="editModal<?= (string)$feature['_id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= (string)$feature['_id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" onsubmit="return handleSaveEdit('<?= (string)$feature['_id'] ?>')">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="editModalLabel<?= (string)$feature['_id'] ?>">
                        <i class="fas fa-edit me-2"></i>Edit Feature
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="handleCancelEdit('<?= (string)$feature['_id'] ?>')"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_id" value="<?= (string)$feature['_id'] ?>">
                    <input type="hidden" name="delete_file" id="delete_file_<?= (string)$feature['_id'] ?>" value="0">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">System Name <span class="text-danger">*</span></label>
                                <input type="text" name="edit_system_name" class="form-control" required value="<?= htmlspecialchars($feature['system_name']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Module <span class="text-danger">*</span></label>
                                <input type="text" name="edit_module" class="form-control" required value="<?= htmlspecialchars($feature['module']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Feature</label>
                                <input type="text" name="edit_feature" class="form-control" value="<?= htmlspecialchars($feature['feature'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Client <span class="text-danger">*</span></label>
                                <input type="text" name="edit_client" class="form-control" required value="<?= htmlspecialchars($feature['client']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                        <textarea name="edit_description" class="form-control" rows="3" required placeholder="Enter a detailed description of the feature..."><?= htmlspecialchars($feature['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <input type="text" name="edit_source" class="form-control" value="<?= htmlspecialchars($feature['source'] ?? '') ?>" placeholder="Enter the source or reference">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Sample File</label>
                        <div id="file-wrapper-<?= (string)$feature['_id'] ?>" data-original-url="<?= $feature['sample_file'] ?? '' ?>">
                            <?php if (!empty($feature['sample_file'])): ?>
                                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border" id="file-preview-<?= (string)$feature['_id'] ?>">
                                    <i class="fas fa-file-alt text-primary"></i>
                                    <a href="<?= $feature['sample_file'] ?>" target="_blank" class="text-decoration-none flex-grow-1">
                                        <i class="fas fa-external-link-alt me-1"></i>View current file
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile('<?= (string)$feature['_id'] ?>')">
                                        <i class="fas fa-trash me-1"></i>Remove
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-muted fst-italic p-2 bg-light rounded border">
                                    <i class="fas fa-file-slash me-1"></i>No file uploaded
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Alert shown when file is marked for removal -->
                        <div class="alert alert-warning mt-2 d-none" id="file-alert-<?= (string)$feature['_id'] ?>">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Sample file marked for removal. Please upload a new file before saving or the file will be permanently deleted.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Upload New Sample File</label>
                        <input type="file" name="edit_sample_file" class="form-control" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Supported formats: PDF, DOC, DOCX, TXT, JPG, JPEG, PNG, GIF
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="handleCancelEdit('<?= (string)$feature['_id'] ?>')">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success" name="update_feature">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// JavaScript functions for handling file operations
function removeFile(featureId) {
    // Mark file for deletion
    document.getElementById('delete_file_' + featureId).value = '1';
    
    // Hide current file preview
    const filePreview = document.getElementById('file-preview-' + featureId);
    if (filePreview) {
        filePreview.classList.add('d-none');
    }
    
    // Show warning alert
    const fileAlert = document.getElementById('file-alert-' + featureId);
    if (fileAlert) {
        fileAlert.classList.remove('d-none');
    }
    
    // Show "No file" message
    const fileWrapper = document.getElementById('file-wrapper-' + featureId);
    if (fileWrapper) {
        fileWrapper.innerHTML = `
            <div class="text-muted fst-italic p-2 bg-light rounded border">
                <i class="fas fa-file-slash me-1"></i>File marked for removal
            </div>
        `;
    }
}

function handleCancelEdit(featureId) {
    // Reset delete file flag
    document.getElementById('delete_file_' + featureId).value = '0';
    
    // Hide warning alert
    const fileAlert = document.getElementById('file-alert-' + featureId);
    if (fileAlert) {
        fileAlert.classList.add('d-none');
    }
    
    // Restore original file preview
    const fileWrapper = document.getElementById('file-wrapper-' + featureId);
    const originalUrl = fileWrapper.dataset.originalUrl;
    
    if (originalUrl) {
        fileWrapper.innerHTML = `
            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border" id="file-preview-${featureId}">
                <i class="fas fa-file-alt text-primary"></i>
                <a href="${originalUrl}" target="_blank" class="text-decoration-none flex-grow-1">
                    <i class="fas fa-external-link-alt me-1"></i>View current file
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile('${featureId}')">
                    <i class="fas fa-trash me-1"></i>Remove
                </button>
            </div>
        `;
    } else {
        fileWrapper.innerHTML = `
            <div class="text-muted fst-italic p-2 bg-light rounded border">
                <i class="fas fa-file-slash me-1"></i>No file uploaded
            </div>
        `;
    }
}

function handleSaveEdit(featureId) {
    // Add any validation logic here if needed
    return true; // Allow form submission
}
</script>