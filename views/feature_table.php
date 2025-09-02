feature table
<?php
// Helper function to safely format date
function formatDate($dateField) {
    try {
        if (isset($dateField) && $dateField instanceof MongoDB\BSON\UTCDateTime) {
            return $dateField->toDateTime()->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
        }
        return 'N/A';
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Helper function to get file icon
function getFileIcon($extension) {
    $icons = [
        'pdf' => '📄',
        'doc' => '📝', 
        'docx' => '📝',
        'txt' => '📃',
        'zip' => '🗜️',
        'rar' => '🗜️',
        'xls' => '📊',
        'xlsx' => '📊'
    ];
    return $icons[$extension] ?? '📁';
}

// Helper function to truncate filename
function truncateFilename($filename, $maxLength = 20) {
    return strlen($filename) > $maxLength ? substr($filename, 0, $maxLength - 3) . '...' : $filename;
}

/**
 * Safely decode and re-encode HTML entities for display
 */
function safeDisplayText($text) {
    if (empty($text)) return '';
    
    // First decode any existing HTML entities
    $decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Then safely encode for display
    return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
}

/**
 * Highlight search terms in text
 */
function highlightSearchTerm($text, $searchTerm) {
    if (empty($searchTerm) || empty($text)) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    $highlighted = preg_replace(
        '/(' . preg_quote($searchTerm, '/') . ')/i',
        '<mark class="bg-warning">$1</mark>',
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
    
    return $highlighted;
}
?>

<!-- Search Section -->
<div class="container mb-3">
    <div class="row justify-content-end">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">🔍</span>
                <input type="text" 
                       id="searchInput" 
                       class="form-control"
                       placeholder="Search features..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES) ?>"
                       autocomplete="off">
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Section -->
<?php if (!empty($features)): ?>
<div class="container mb-3">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-3">
                <label class="form-check-label">
                    <input type="checkbox" id="selectAll" class="form-check-input"> Select All
                </label>
                <button class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>
                    <i class="fas fa-trash-alt me-1"></i>Delete Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($features)): ?>
    <div class="alert alert-warning text-center">
        <i class="fas fa-search me-2"></i>
        No features found.
    </div>
<?php else: ?>
    <!-- Features Table -->
    <div class="table-responsive">
        <table class="table custom-table align-middle shadow-sm">
            <thead class="table-header-green">
                <tr>
                    <th class="large-font" width="50">
                        <input type="checkbox" id="selectAllHeader"> 
                    </th>
                    <th class="large-font">Created At</th>
                    <th class="large-font">System Name</th>
                    <th class="large-font">Module</th>
                    <th class="large-font">Feature</th>
                    <th class="large-font">Description</th>
                    <th class="large-font">Client</th>
                    <th class="large-font">Source</th>
                    <th class="large-font">File or URLs</th>
                    <th class="large-font">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($features as $feature): ?>
                    <tr>
                        <!-- Checkbox Column -->
                        <td>
                            <input type="checkbox" class="feature-checkbox form-check-input" 
                                   value="<?= (string)$feature['_id'] ?>"
                                   data-name="<?= safeDisplayText($feature['feature'] ?? 'Unknown') ?>">
                        </td>
                        
                        <!-- Date Column -->
                        <td><?= formatDate($feature['created_at'] ?? null) ?></td>
                        
                        <!-- System Name -->
                        <td><?= highlightSearchTerm($feature['system_name'] ?? '', $search) ?></td>
                        
                        <!-- Module -->
                        <td><?= highlightSearchTerm($feature['module'] ?? '', $search) ?></td>
                        
                        <!-- Feature -->
                        <td><?= highlightSearchTerm($feature['feature'] ?? '', $search) ?></td>
                        
                        <!-- Description -->
                        <td>
                            <div class="text-truncate" style="max-width: 200px;" title="<?= safeDisplayText($feature['description'] ?? '') ?>">
                                <?= safeDisplayText($feature['description'] ?? '') ?>
                            </div>
                        </td>
                        
                        <!-- Client -->
                        <td><?= highlightSearchTerm($feature['client'] ?? '', $search) ?></td>
                        
                        <!-- Source -->
                        <td><?= highlightSearchTerm($feature['source'] ?? '', $search) ?></td>
                        
                        <!-- Sample File -->
                        <td>
                            <?php
                                $file = $feature['sample_file'] ?? '';
                                if (!empty($file)):
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                                    $filename = basename($file);

                                    // Check if it's a valid external URL
                                    if (filter_var($file, FILTER_VALIDATE_URL)):
                                        $parsedUrl = parse_url($file);
                                        $domain = $parsedUrl['host'] ?? '';
                                        $favicon = "https://www.google.com/s2/favicons?sz=32&domain={$domain}";
                            ?>
                                        <span class="d-inline-flex align-items-center">
                                            <img src="<?= htmlspecialchars($favicon, ENT_QUOTES) ?>"
                                                 alt="favicon"
                                                 width="16" height="16"
                                                 class="me-2">
                                            <a href="<?= htmlspecialchars($file, ENT_QUOTES) ?>"
                                               target="_blank"
                                               class="text-decoration-none">
                                                <?= htmlspecialchars($domain, ENT_QUOTES) ?>
                                            </a>
                                        </span>
                            <?php
                                    // Not a URL, handle as image or file
                                    elseif (in_array($ext, $imageTypes)):
                            ?>
                                        <a href="<?= htmlspecialchars($file, ENT_QUOTES) ?>"
                                           target="_blank"
                                           class="text-decoration-none">
                                            <img src="<?= htmlspecialchars($file, ENT_QUOTES) ?>"
                                                 alt="Preview"
                                                 class="preview-thumb rounded"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        </a>
                            <?php else: ?>
                                        <span class="d-inline-flex align-items-center">
                                            <?= getFileIcon($ext) ?>
                                            <a href="<?= htmlspecialchars($file, ENT_QUOTES) ?>"
                                               target="_blank"
                                               class="ms-2 text-decoration-none"
                                               title="<?= safeDisplayText($filename) ?>">
                                                <?= safeDisplayText(truncateFilename($filename)) ?>
                                            </a>
                                        </span>
                            <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">N/A</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Actions -->
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-success"
                                        data-id="<?= (string)$feature['_id'] ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= (string)$feature['_id'] ?>"
                                        title="Edit feature">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                
                                <button class="btn btn-sm btn-danger"
                                        onclick="confirmDelete('<?= (string)$feature['_id'] ?>', '<?= safeDisplayText($feature['feature'] ?? $feature['system_name'] ?? 'Unknown') ?>')"
                                        title="Delete feature">
                                    <i class="fas fa-trash-alt me-1"></i>Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    
                    <?php 
                    // Include edit modal for each feature
                    if (file_exists(__DIR__ . '/edit_modal.php')) {
                        include __DIR__ . '/edit_modal.php';
                    }
                    ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (isset($totalPages) && $totalPages > 1): ?>
        <nav aria-label="Features pagination">
            <ul class="pagination justify-content-center mt-4">
                <!-- Previous Page -->
                <li class="page-item <?= ($page ?? 1) <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" 
                       href="?page=<?= max(1, ($page ?? 1) - 1) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"
                       aria-label="Previous">
                        <i class="fas fa-chevron-left me-1"></i>Prev
                    </a>
                </li>
                
                <!-- Page Numbers -->
                <?php
                $currentPage = $page ?? 1;
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                // Show first page if we're not starting from 1
                if ($startPage > 1):
                ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Current page range -->
                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <li class="page-item <?= $p == $currentPage ? 'active' : '' ?>">
                        <a class="page-link" 
                           href="?page=<?= $p ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"
                           <?= $p == $currentPage ? 'aria-current="page"' : '' ?>>
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <!-- Show last page if we're not ending at the last page -->
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $totalPages ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Next Page -->
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" 
                       href="?page=<?= min($totalPages, $currentPage + 1) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"
                       aria-label="Next">
                        Next<i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- Single Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-trash-alt me-2"></i>Delete Feature
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                    <h6>Are you sure you want to delete this feature?</h6>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                    <p class="fw-bold mt-2 text-danger" id="deleteFeatureName"></p>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <form method="POST" action="delete.php" style="display: inline;">
                    <input type="hidden" id="deleteFeatureId" name="id" value="">
                    <button type="submit" name="delete_feature" value="true" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="bulkDeleteModalLabel">
                    <i class="fas fa-trash-alt me-2"></i>Bulk Delete Features
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                    <h6>Are you sure you want to delete <span id="bulkDeleteCount">0</span> feature(s)?</h6>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                    <div id="bulkDeleteList" class="mt-3 text-start small"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" id="confirmBulkDelete" class="btn btn-danger">
                    <i class="fas fa-trash-alt me-1"></i>Delete All
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced search functionality with loading state
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality with loading state
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.querySelector('tbody');
    const tableContainer = document.querySelector('.table-responsive');
    let searchTimeout;
    
    // Create loading overlay
    function createLoadingOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'searchLoadingOverlay';
        overlay.className = 'position-relative d-flex justify-content-center align-items-center';
        overlay.style.cssText = `
            min-height: 200px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        `;
        
        overlay.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="text-muted mb-2">
                    <i class="fas fa-search me-2"></i>Searching features...
                </h5>
                <p class="text-muted small mb-0">Please wait while we find matching results</p>
            </div>
        `;
        
        return overlay;
    }
    
    // Show loading state
    function showLoadingState() {
        if (tableContainer) {
            const existingTable = tableContainer.querySelector('table');
            const existingOverlay = document.getElementById('searchLoadingOverlay');
            
            // Remove existing overlay if any
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            // Hide existing table and show loading
            if (existingTable) {
                existingTable.style.display = 'none';
            }
            
            // Add loading overlay
            const loadingOverlay = createLoadingOverlay();
            tableContainer.appendChild(loadingOverlay);
        }
        
        // Also hide pagination and bulk actions during search
        const pagination = document.querySelector('.pagination');
        const bulkActions = document.querySelector('.container.mb-3:has(#selectAll)');
        
        if (pagination) pagination.style.display = 'none';
        if (bulkActions) bulkActions.style.display = 'none';
    }
    
    // Hide loading state and restore table
    function hideLoadingState() {
        const loadingOverlay = document.getElementById('searchLoadingOverlay');
        const existingTable = tableContainer?.querySelector('table');
        
        if (loadingOverlay) {
            loadingOverlay.remove();
        }
        
        if (existingTable) {
            existingTable.style.display = 'table';
        }
        
        // Restore pagination and bulk actions
        const pagination = document.querySelector('.pagination');
        const bulkActions = document.querySelector('.container.mb-3:has(#selectAll)');
        
        if (pagination) pagination.style.display = 'flex';
        if (bulkActions) bulkActions.style.display = 'block';
    }
    
    if (searchInput) {
        // Add search input styling for better UX
        searchInput.addEventListener('focus', function() {
            this.parentElement.classList.add('shadow-sm');
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.classList.remove('shadow-sm');
        });
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const currentValue = this.value.trim();
            
            // Add visual feedback for typing
            if (currentValue) {
                this.classList.add('searching');
            } else {
                this.classList.remove('searching');
            }
            
            searchTimeout = setTimeout(function() {
    // Let main.js SearchManager handle the AJAX search
    // Don't redirect here - main.js will handle it
}, 500);
        });
        
        // Handle Enter key with loading state
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                
                const searchValue = searchInput.value.trim();
                
                if (searchValue) {
                    showLoadingState();
                    
                    setTimeout(function() {
                        const url = new URL(window.location);
                        url.searchParams.set('search', searchValue);
                        url.searchParams.set('page', '1');
                        window.location.href = url.toString();
                    }, 200);
                } else {
                    const url = new URL(window.location);
                    url.searchParams.delete('search');
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                }
            }
        });
    }

    // Rest of your existing bulk delete functionality...
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    const featureCheckboxes = document.querySelectorAll('.feature-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectedCount = document.getElementById('selectedCount');

    // Sync both select all checkboxes
    function syncSelectAll() {
        if (selectAllCheckbox && selectAllHeader) {
            selectAllCheckbox.checked = selectAllHeader.checked;
            selectAllHeader.checked = selectAllCheckbox.checked;
        }
    }

    // Update button state and count
    function updateBulkActions() {
        const selected = document.querySelectorAll('.feature-checkbox:checked');
        const count = selected.length;
        
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = count === 0;
        if (selectedCount) selectedCount.textContent = count;
        
        const allChecked = featureCheckboxes.length > 0 && 
                          Array.from(featureCheckboxes).every(cb => cb.checked);
        const noneChecked = Array.from(featureCheckboxes).every(cb => !cb.checked);
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
        }
        if (selectAllHeader) {
            selectAllHeader.checked = allChecked;
            selectAllHeader.indeterminate = !allChecked && !noneChecked;
        }
    }

    // Select all functionality
    [selectAllCheckbox, selectAllHeader].forEach(checkbox => {
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                featureCheckboxes.forEach(cb => cb.checked = this.checked);
                syncSelectAll();
                updateBulkActions();
            });
        }
    });

    // Individual checkbox changes
    featureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    // Bulk delete button click
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.feature-checkbox:checked'));
            
            if (selected.length === 0) return;
            
            document.getElementById('bulkDeleteCount').textContent = selected.length;
            
            const listDiv = document.getElementById('bulkDeleteList');
            listDiv.innerHTML = '<strong>Features to delete:</strong><ul class="mt-2">' +
                selected.map(cb => `<li>${cb.dataset.name || 'Unknown'}</li>`).join('') +
                '</ul>';
            
            const modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
            modal.show();
        });
    }

    // Confirm bulk delete
    const confirmBtn = document.getElementById('confirmBulkDelete');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.feature-checkbox:checked'))
                .map(cb => cb.value);
            
            if (selected.length === 0) return;
            
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';
            
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'bulk_delete',
                    ids: selected
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Features deleted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>Delete All';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting features');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>Delete All';
            });
        });
    }

    if (featureCheckboxes.length > 0) {
        updateBulkActions();
    }
});

// Delete confirmation handler for single delete
function confirmDelete(id, name) {
    if (!id) {
        console.error('Feature ID is required');
        return;
    }
    
    document.getElementById('deleteFeatureId').value = id;
    document.getElementById('deleteFeatureName').textContent = name || 'Unknown Feature';
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>

<style>
.preview-thumb {
    cursor: pointer;
    transition: transform 0.2s;
}

.preview-thumb:hover {
    transform: scale(1.1);
}

.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pagination .page-link {
    border-radius: 0.375rem;
    margin: 0 2px;
    border: 1px solid #dee2e6;
}

.pagination .page-item.active .page-link {
    background-color: #198754;
    border-color: #198754;
}

.pagination .page-item:not(.active) .page-link:hover {
    background-color: #e9ecef;
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #ced4da;
}

.btn-success {
    background-color: #198754;
    border-color: #198754;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}

.modal-header.bg-danger {
    background-color: #dc3545 !important;
}

/* Enhanced search and loading styles */
.input-group:has(#searchInput) {
    transition: all 0.2s ease;
}

.input-group:has(#searchInput).shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    transform: translateY(-1px);
}

#searchInput:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Loading overlay styles */
#searchLoadingOverlay {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Loading text animation */
#searchLoadingOverlay h5 {
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Loading state for search input */
#searchInput.searching {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 16px;
}

/* Improved transitions */
.pagination .page-item:not(.active) .page-link:hover {
    transform: translateY(-1px);
}

.btn-success:hover {
    transform: translateY(-1px);
}

.btn-danger:hover {
    transform: translateY(-1px);
}
</style>

