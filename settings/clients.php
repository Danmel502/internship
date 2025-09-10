<?php
require_once '../config.php';

// Start session for flash messages
session_start();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['client_name'] ?? '');
        $description = trim($_POST['client_description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($name)) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('clients');
                
                // Check if name already exists
                $existing = $collection->findOne(['name' => $name, 'is_active' => true]);
                if ($existing) {
                    $message = 'Client name already exists';
                    $messageType = 'error';
                } else {
                    // Get next ID
                    $lastDoc = $collection->findOne([], ['sort' => ['id' => -1]]);
                    $nextId = ($lastDoc['id'] ?? 0) + 1;
                    
                    $result = $collection->insertOne([
                        'id' => $nextId,
                        'name' => $name,
                        'description' => $description,
                        'is_active' => (bool)$isActive,
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]);
                    
                    $message = 'Client added successfully';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Client name is required';
            $messageType = 'error';
        }
    } elseif ($action === 'update') {
        $id = $_POST['client_id'] ?? '';
        $name = trim($_POST['client_name'] ?? '');
        $description = trim($_POST['client_description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($id) && !empty($name)) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('clients');
                $overallCollection = $db->getCollection('overall');
                
                // Get current client data
                $currentClient = $collection->findOne(['id' => (int)$id]);
                if (!$currentClient) {
                    $message = 'Client not found';
                    $messageType = 'error';
                } else {
                    // Check if this client has dependencies
                    $hasDependencies = $overallCollection->countDocuments(['client_id' => (int)$id]) > 0;
                    
                    // Only check dependencies if trying to disable an active client
                    if ($hasDependencies && $currentClient['is_active'] && !$isActive) {
                        $message = 'Cannot disable client - it has dependencies';
                        $messageType = 'error';
                    } else {
                        $result = $collection->updateOne(
                            ['id' => (int)$id],
                            ['$set' => [
                                'name' => $name,
                                'description' => $description,
                                'is_active' => (bool)$isActive,
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                        
                        if ($result->getMatchedCount() > 0) {
                            $message = 'Client updated successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Client not found';
                            $messageType = 'error';
                        }
                    }
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Client ID and name are required';
            $messageType = 'error';
        }
    }
}

// Get all clients
try {
    $db = Database::getInstance()->getDatabase();
    $collection = $db->getCollection('clients');
    $overallCollection = $db->getCollection('overall');
    
    $cursor = $collection->find([], ['sort' => ['name' => 1]]);
    $clients = [];
    
    foreach ($cursor as $doc) {
        // Check for dependencies
        $hasDependencies = $overallCollection->countDocuments(['client_id' => $doc['id']]) > 0;
        
        $clients[] = [
            'id' => $doc['id'],
            'name' => $doc['name'],
            'description' => $doc['description'] ?? '',
            'is_active' => $doc['is_active'],
            'has_dependencies' => $hasDependencies,
            'created_at' => $doc['created_at'] ?? null,
            'updated_at' => $doc['updated_at'] ?? null
        ];
    }
} catch (Exception $e) {
    $clients = [];
    $message = 'Error loading clients: ' . $e->getMessage();
    $messageType = 'error';
}

// Get base path for navigation
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$current_page = 'settings';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients Management - Media Track</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    
    <style>
        .form-check-input:checked {
            background-color: #198754 !important;
            border-color: #198754 !important;
        }
        .form-check-input:checked::before {
            background-color: white !important;
        }
        .form-switch .form-check-input:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .form-switch .form-check-input {
            background-color: #6c757d;
        }
        .form-switch .form-check-input:checked {
            background-color: #198754;
        }
        .form-switch .form-check-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .description-cell {
            max-width: 250px;
            word-wrap: break-word;
            white-space: normal;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand text-success" href="<?php echo $basePath; ?>/index.php">Media <span class="text-dark">Track</span></a>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../about.php">About</a>
                    </li>
                    <li class="nav-item dropdown" id="settingsDropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" id="settingsMenu" aria-expanded="false">
                            Settings
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" id="settingsDropdownMenu">
                            <li><a class="dropdown-item" href="system-names.php">System Names</a></li>
                            <li><a class="dropdown-item" href="modules.php">Modules</a></li>
                            <li><a class="dropdown-item active" href="clients.php">Clients</a></li>
                            <li><a class="dropdown-item" href="sources.php">Sources</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero py-5 text-center text-white">
        <div class="container">
            <h1 class="fw-bold display-6">Clients Management</h1>
            <p class="lead mt-3">
                Manage and configure clients for your media tracking application.<br>
                Create, update, and organize client configurations with ease.
            </p>
        </div>
    </section>

    <div class="divider-line"></div>

    <!-- Main Content -->
    <section class="py-5 bg-white">
        <div class="container">

            <!-- Flash Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add New Client Section -->
            <div class="content-box p-4 p-md-5 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">Add New Client</h3>
                    <a class="btn btn-outline-secondary" href="../index.php">
                        Back to Home
                    </a>
                </div>

                <form method="POST" id="addClientForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="clientName" class="form-label">Client Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_name" id="clientName" class="form-control mb-3" 
                                   placeholder="Enter client name" required>
                            
                            <label for="clientDescription" class="form-label">Description</label>
                            <textarea name="client_description" id="clientDescription" class="form-control mb-3" 
                                      rows="3" placeholder="Enter client description (optional)"></textarea>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active (visible in main form)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Add Client
                            </button>
                            <button type="reset" class="btn btn-secondary ms-2">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table Section -->
            <div class="content-box p-4 p-md-5">
                <h3 class="mb-4">Clients List</h3>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client Name</th>
                                <th>Status</th>
                                <th>Dependencies</th>
                                <th>Description</th>
                                <th>Created / Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <p class="mb-0">No clients found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?php echo $client['id']; ?></td>
                                        <td><?php echo htmlspecialchars($client['name']); ?></td>
                                        <td>
                                            <?php if ($client['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($client['has_dependencies']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Has Dependencies
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="description-cell">
                                            <?php if (!empty($client['description'])): ?>
                                                <?php echo htmlspecialchars($client['description']); ?>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div><strong>Created:</strong> 
                                                    <?php echo $client['created_at'] ? date('M d, Y H:i', $client['created_at']->toDateTime()->getTimestamp()) : 'N/A'; ?>
                                                </div>
                                                <?php if ($client['updated_at'] && $client['updated_at'] != $client['created_at']): ?>
                                                <div class="text-muted"><strong>Updated:</strong> 
                                                    <?php echo date('M d, Y H:i', $client['updated_at']->toDateTime()->getTimestamp()); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="editClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($client['description'], ENT_QUOTES); ?>', <?php echo $client['is_active'] ? 'true' : 'false'; ?>, <?php echo $client['has_dependencies'] ? 'true' : 'false'; ?>)">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editClientForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="client_id" id="editClientId">
                        
                        <div class="mb-3">
                            <label for="editClientName" class="form-label">Client Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_name" id="editClientName" class="form-control" 
                                   placeholder="Enter client name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editClientDescription" class="form-label">Description</label>
                            <textarea name="client_description" id="editClientDescription" class="form-control" 
                                      rows="3" placeholder="Enter client description (optional)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="editIsActive">
                                <label class="form-check-label" for="editIsActive">
                                    Active (visible in main form)
                                </label>
                            </div>
                            <div id="editDependencyWarning" class="text-warning mt-1 small" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Cannot disable - this client has dependencies
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide flash messages after 8 seconds
        const flashMessage = document.getElementById('flashMessage');
        if (flashMessage) {
            setTimeout(function() {
                // Use Bootstrap's alert method to hide the message
                const alertInstance = bootstrap.Alert.getOrCreateInstance(flashMessage);
                alertInstance.close();
            }, 8000); // 8 seconds
        }

        // Settings dropdown functionality
        const dropdown = document.getElementById('settingsDropdown');
        const dropdownMenu = document.getElementById('settingsDropdownMenu');
        const dropdownToggle = document.getElementById('settingsMenu');
        
        if (dropdown && dropdownMenu && dropdownToggle) {
            let isClicked = false;
            let hoverTimeout;

            // Show dropdown on hover
            dropdown.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                if (!isClicked) {
                    dropdownMenu.classList.add('show');
                    dropdown.classList.add('show');
                }
            });

            // Hide dropdown on mouse leave (only if not clicked)
            dropdown.addEventListener('mouseleave', function() {
                if (!isClicked) {
                    hoverTimeout = setTimeout(function() {
                        dropdownMenu.classList.remove('show');
                        dropdown.classList.remove('show');
                    }, 100);
                }
            });

            // Toggle dropdown on click
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                isClicked = !isClicked;
                
                if (isClicked) {
                    dropdownMenu.classList.add('show');
                    dropdown.classList.add('show');
                } else {
                    dropdownMenu.classList.remove('show');
                    dropdown.classList.remove('show');
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    isClicked = false;
                    dropdownMenu.classList.remove('show');
                    dropdown.classList.remove('show');
                }
            });

            // Handle dropdown item clicks
            dropdownMenu.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-item')) {
                    // Close dropdown after clicking an item
                    isClicked = false;
                    dropdownMenu.classList.remove('show');
                    dropdown.classList.remove('show');
                }
            });
        }

        // Handle edit modal functionality
        const editIsActiveCheckbox = document.getElementById('editIsActive');
        const editDependencyWarning = document.getElementById('editDependencyWarning');
        let currentClientHasDependencies = false;
        let currentClientIsActive = false;

        if (editIsActiveCheckbox && editDependencyWarning) {
            // Prevent disabling when client has dependencies and is currently active
            editIsActiveCheckbox.addEventListener('change', function(e) {
                if (currentClientHasDependencies && currentClientIsActive && !this.checked) {
                    // Prevent unchecking if client has dependencies and is currently active
                    e.preventDefault();
                    this.checked = true;
                    return false;
                }
            });
        }
    });

    // Function to edit client
    function editClient(id, name, description, isActive, hasDependencies) {
        document.getElementById('editClientId').value = id;
        document.getElementById('editClientName').value = name;
        document.getElementById('editClientDescription').value = description;
        document.getElementById('editIsActive').checked = isActive;
        
        const dependencyWarning = document.getElementById('editDependencyWarning');
        const editIsActiveCheckbox = document.getElementById('editIsActive');
        
        // Store dependency and active status globally
        currentClientHasDependencies = hasDependencies;
        currentClientIsActive = isActive;
        
        if (hasDependencies && isActive) {
            dependencyWarning.style.display = 'block';
            // Make the checkbox appear disabled but don't actually disable it (to prevent form issues)
            editIsActiveCheckbox.style.pointerEvents = 'none';
            editIsActiveCheckbox.style.opacity = '0.6';
        } else {
            dependencyWarning.style.display = 'none';
            editIsActiveCheckbox.style.pointerEvents = 'auto';
            editIsActiveCheckbox.style.opacity = '1';
        }
        
        // Show the modal
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
    </script>

</body>
</html>