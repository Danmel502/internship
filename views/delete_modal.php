<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Modal - Clean Version</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Demo button to test modal -->
    <div class="container mt-5">
        <button type="button" class="btn btn-danger" onclick="confirmDelete(123, 'User Authentication')">
            <i class="fas fa-trash-alt me-1"></i>Delete Feature
        </button>
    </div>

    <!-- Delete Modal -->
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
                        <h6 class="mb-3">Are you sure you want to delete this feature?</h6>
                        <p class="text-muted mb-3">This action cannot be undone.</p>
                        <div class="alert alert-light border">
                            <strong>Feature:</strong> <span id="deleteFeatureName" class="text-danger"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <form method="POST" action="delete.php" style="display: inline;" id="deleteForm">
                        <input type="hidden" id="deleteFeatureId" name="id" value="">
                        <button type="submit" name="delete_feature" value="true" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript to handle delete modal
        function confirmDelete(id, name) {
            // Set the values in the modal
            document.getElementById('deleteFeatureId').value = id;
            document.getElementById('deleteFeatureName').textContent = name;
            
            // Show the modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Optional: Add form validation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const featureId = document.getElementById('deleteFeatureId').value;
            if (!featureId) {
                e.preventDefault();
                alert('Invalid feature ID');
                return false;
            }
        });
    </script>
</body>
</html>