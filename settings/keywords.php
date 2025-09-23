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
        $keyword = trim($_POST['keyword'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($keyword)) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('keywords');
                
                // Check if keyword already exists
                $existing = $collection->findOne(['keyword' => $keyword, 'is_active' => true]);
                if ($existing) {
                    $message = 'Keyword already exists';
                    $messageType = 'error';
                } else {
                    // Get next ID
                    $lastDoc = $collection->findOne([], ['sort' => ['id' => -1]]);
                    $nextId = ($lastDoc['id'] ?? 0) + 1;
                    
                    $result = $collection->insertOne([
                        'id' => $nextId,
                        'keyword' => $keyword,
                        'category' => $category,
                        'description' => $description,
                        'synonyms' => [], // Initialize empty synonyms array
                        'is_active' => (bool)$isActive,
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]);
                    
                    $message = 'Keyword added successfully';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Keyword is required';
            $messageType = 'error';
        }
    } elseif ($action === 'update') {
        $id = $_POST['keyword_id'] ?? '';
        $keyword = trim($_POST['keyword'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($id) && !empty($keyword)) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('keywords');
                $overallCollection = $db->getCollection('overall');
                
                // Get current keyword data
                $currentKeyword = $collection->findOne(['id' => (int)$id]);
                if (!$currentKeyword) {
                    $message = 'Keyword not found';
                    $messageType = 'error';
                } else {
                    $result = $collection->updateOne(
                        ['id' => (int)$id],
                        ['$set' => [
                            'name' => $keyword,
                            'category' => $category,
                            'description' => $description,
                            'is_active' => (bool)$isActive,
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                    
                    if ($result->getMatchedCount() > 0) {
                        $message = 'Keyword updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Keyword not found';
                        $messageType = 'error';
                    }
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Keyword ID and name are required';
            $messageType = 'error';
        }
    } elseif ($action === 'add_synonym') {
        $keywordId = $_POST['keyword_id'] ?? '';
        $synonym = trim($_POST['synonym'] ?? '');
        
        if (!empty($keywordId) && !empty($synonym)) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('keywords');
                
                // Add synonym to the array
                $result = $collection->updateOne(
                    ['id' => (int)$keywordId],
                    [
                        '$addToSet' => ['synonyms' => $synonym], // $addToSet prevents duplicates
                        '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                    ]
                );
                
                if ($result->getMatchedCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Synonym added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Keyword not found']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Keyword ID and synonym are required']);
            exit;
        }
    } elseif ($action === 'remove_synonym') {
        $keywordId = $_POST['keyword_id'] ?? '';
        $synonym = $_POST['synonym'] ?? '';
        
        if (!empty($keywordId) && !empty($synonym)) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('keywords');
                
                // Remove synonym from the array
                $result = $collection->updateOne(
                    ['id' => (int)$keywordId],
                    [
                        '$pull' => ['synonyms' => $synonym],
                        '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                    ]
                );
                
                if ($result->getMatchedCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Synonym removed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Keyword not found']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Keyword ID and synonym are required']);
            exit;
        }
    }
}

// Get all keywords
try {
    $db = Database::getInstance()->getDatabase();
    $collection = $db->getCollection('keywords');
    
    $cursor = $collection->find([], ['sort' => ['keyword' => 1]]);
    $keywords = [];

    // Add this debug code right after line ~175 where you do the find query
foreach ($cursor as $doc) {
    error_log('Debug - Document fields: ' . print_r((array)$doc, true)); // Add this line
    break; // Only log the first document
}
    
    foreach ($cursor as $doc) {
        $keywords[] = [
            'id' => $doc['id'] ?? 0,
             'keyword' => $doc['keyword'] ?? $doc['name'] ?? '',
            'category' => $doc['category'] ?? '',
            'description' => $doc['description'] ?? '',
            'synonyms' => $doc['synonyms'] ?? [],
            'is_active' => $doc['is_active'],
            'created_at' => $doc['created_at'] ?? null,
            'updated_at' => $doc['updated_at'] ?? null
        ];
    }
} catch (Exception $e) {
    $keywords = [];
    $message = 'Error loading keywords: ' . $e->getMessage();
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
    <title>Keywords Management - Media Track</title>
    
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
        .description-cell {
            max-width: 250px;
            word-wrap: break-word;
            white-space: normal;
        }
        .synonym-badge {
            margin: 2px;
            position: relative;
        }
        .synonym-remove {
            margin-left: 5px;
            cursor: pointer;
            color: #dc3545;
        }
        .synonym-remove:hover {
            color: #c82333;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand text-success" href="../index.php">Media <span class="text-dark">Track</span></a>

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
                            <li><a class="dropdown-item" href="clients.php">Clients</a></li>
                            <li><a class="dropdown-item" href="sources.php">Sources</a></li>
                            <li><a class="dropdown-item active" href="keywords.php">Keywords</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero py-5 text-center text-white">
        <div class="container">
            <h1 class="fw-bold display-6">Keywords Management</h1>
            <p class="lead mt-3">
                Manage search keywords and their synonyms for enhanced search functionality.<br>
                Create keywords with multiple synonyms to improve search results.
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

            <!-- Add New Keyword Section -->
            <div class="content-box p-4 p-md-5 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">Add New Keyword</h3>
                    <a class="btn btn-outline-secondary" href="../index.php">
                        Back to Home
                    </a>
                </div>

                <form method="POST" id="addKeywordForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="keyword" class="form-label">Keyword <span class="text-danger">*</span></label>
                            <input type="text" name="keyword" id="keyword" class="form-control mb-3" 
                                   placeholder="Enter keyword" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" name="category" id="category" class="form-control mb-3" 
                                   placeholder="Enter category (optional)">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control mb-3" 
                                      rows="3" placeholder="Enter keyword description (optional)"></textarea>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active (available for search expansion)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Add Keyword
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
                <h3 class="mb-4">Keywords List</h3>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Keyword</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Synonyms</th>
                                <th>Description</th>
                                <th>Created / Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($keywords)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <p class="mb-0">No keywords found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($keywords as $keyword): ?>
                                    <tr>
                                        <td><?php echo $keyword['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($keyword['keyword']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($keyword['category'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($keyword['category']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">No category</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($keyword['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($keyword['synonyms']) && count($keyword['synonyms']) > 0): ?>
                                                <button type="button" class="badge bg-success text-white border-0" 
                                                        style="cursor: pointer;" 
                                                        onclick="showSynonyms('<?php echo htmlspecialchars($keyword['keyword'], ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($keyword['synonyms']), ENT_QUOTES); ?>, <?php echo $keyword['id']; ?>)">
                                                    View Synonyms (<?php echo count($keyword['synonyms']); ?>)
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="badge bg-secondary text-white border-0" 
                                                        style="cursor: pointer;" 
                                                        onclick="showSynonyms('<?php echo htmlspecialchars($keyword['keyword'], ENT_QUOTES); ?>', [], '<?php echo $keyword['id']; ?>')">
                                                    No Synonyms
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="description-cell">
                                            <?php if (!empty($keyword['description'])): ?>
                                                <?php echo htmlspecialchars($keyword['description']); ?>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div><strong>Created:</strong> 
                                                    <?php echo $keyword['created_at'] ? date('M d, Y H:i', $keyword['created_at']->toDateTime()->getTimestamp()) : 'N/A'; ?>
                                                </div>
                                                <?php if ($keyword['updated_at'] && $keyword['updated_at'] != $keyword['created_at']): ?>
                                                <div class="text-muted"><strong>Updated:</strong> 
                                                    <?php echo date('M d, Y H:i', $keyword['updated_at']->toDateTime()->getTimestamp()); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
    <button type="button" class="btn btn-sm btn-outline-primary" 
            onclick="showSynonyms('<?php echo htmlspecialchars($keyword['keyword'], ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($keyword['synonyms']), ENT_QUOTES); ?>, '<?php echo $keyword['id']; ?>')">
        <i class="fas fa-tags me-1"></i>Populate
    </button>
    <button type="button" class="btn btn-sm btn-outline-success" 
            onclick="editKeyword(<?php echo $keyword['id']; ?>, '<?php echo htmlspecialchars($keyword['keyword'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($keyword['category'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($keyword['description'], ENT_QUOTES); ?>', <?php echo $keyword['is_active'] ? 'true' : 'false'; ?>)">
        <i class="fas fa-edit me-1"></i>Edit
    </button>
</div>
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

    <!-- Edit Keyword Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Keyword</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editKeywordForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="keyword_id" id="editKeywordId">
                        
                        <div class="mb-3">
                            <label for="editKeyword" class="form-label">Keyword <span class="text-danger">*</span></label>
                            <input type="text" name="keyword" id="editKeyword" class="form-control" 
                                   placeholder="Enter keyword" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <input type="text" name="category" id="editCategory" class="form-control" 
                                   placeholder="Enter category (optional)">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea name="description" id="editDescription" class="form-control" 
                                      rows="3" placeholder="Enter keyword description (optional)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="editIsActive">
                                <label class="form-check-label" for="editIsActive">
                                    Active (available for search expansion)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Keyword
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Synonyms Modal -->
    <div class="modal fade" id="synonymsModal" tabindex="-1" aria-labelledby="synonymsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="synonymsModalLabel">Manage Synonyms</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <h6>Keyword: <span id="currentKeyword" class="text-primary"></span></h6>
                    </div>
                    
                    <!-- Add Synonym Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Add New Synonym</h6>
                        </div>
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" id="newSynonym" class="form-control" placeholder="Enter synonym">
                                <button type="button" class="btn btn-success" onclick="addSynonym()">
                                    <i class="fas fa-plus me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Synonyms -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Current Synonyms</h6>
                        </div>
                        <div class="card-body">
                            <div id="synonymsList">
                                <!-- Synonyms will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    let currentKeywordId = null;
    let currentSynonyms = [];

    document.addEventListener('DOMContentLoaded', function() {
        // Settings dropdown functionality (same as system-names.php)
        const dropdown = document.getElementById('settingsDropdown');
        const dropdownMenu = document.getElementById('settingsDropdownMenu');
        const dropdownToggle = document.getElementById('settingsMenu');

        const synonymsModal = document.getElementById('synonymsModal');
if (synonymsModal) {
    synonymsModal.addEventListener('hidden.bs.modal', function() {
        // Refresh the page when synonyms modal is closed
        window.location.reload();
    });
}
        
        if (dropdown && dropdownMenu && dropdownToggle) {
            let isClicked = false;
            let hoverTimeout;

            dropdown.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                if (!isClicked) {
                    dropdownMenu.classList.add('show');
                    dropdown.classList.add('show');
                }
            });

            dropdown.addEventListener('mouseleave', function() {
                if (!isClicked) {
                    hoverTimeout = setTimeout(function() {
                        dropdownMenu.classList.remove('show');
                        dropdown.classList.remove('show');
                    }, 100);
                }
            });

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

            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    isClicked = false;
                    dropdownMenu.classList.remove('show');
                    dropdown.classList.remove('show');
                }
            });

            dropdownMenu.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-item')) {
                    isClicked = false;
                    dropdownMenu.classList.remove('show');
                    dropdown.classList.remove('show');
                }
            });
        }

        // Allow Enter key to add synonym
        document.getElementById('newSynonym').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addSynonym();
            }
        });
    });

    // Function to edit keyword
    function editKeyword(id, keyword, category, description, isActive) {
        document.getElementById('editKeywordId').value = id;
        document.getElementById('editKeyword').value = keyword;
        document.getElementById('editCategory').value = category;
        document.getElementById('editDescription').value = description;
        document.getElementById('editIsActive').checked = isActive;
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }

    // Function to show synonyms
    function showSynonyms(keyword, synonyms, keywordId) {
        currentKeywordId = keywordId;
        currentSynonyms = synonyms || [];
        
        document.getElementById('currentKeyword').textContent = keyword;
        updateSynonymsList();
        
        const synonymsModal = new bootstrap.Modal(document.getElementById('synonymsModal'));
        synonymsModal.show();
    }

    // Function to update synonyms list display
    function updateSynonymsList() {
        const synonymsList = document.getElementById('synonymsList');
        
        if (currentSynonyms.length === 0) {
            synonymsList.innerHTML = '<p class="text-muted fst-italic">No synonyms added yet.</p>';
            return;
        }
        
        let html = '';
        currentSynonyms.forEach(function(synonym, index) {
            html += `<span class="badge bg-light text-dark border synonym-badge">
                        ${synonym}
                        <i class="fas fa-times synonym-remove" onclick="removeSynonym('${synonym}')"></i>
                     </span>`;
        });
        
        synonymsList.innerHTML = html;
    }

    // Function to add synonym
    function addSynonym() {
        const newSynonymInput = document.getElementById('newSynonym');
        const synonym = newSynonymInput.value.trim();
        
        if (!synonym) {
            alert('Please enter a synonym');
            return;
        }
        
        if (currentSynonyms.includes(synonym)) {
            alert('This synonym already exists');
            return;
        }
        
        // Send AJAX request to add synonym
        fetch('keywords.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_synonym&keyword_id=${currentKeywordId}&synonym=${encodeURIComponent(synonym)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentSynonyms.push(synonym);
                updateSynonymsList();
                newSynonymInput.value = '';
                
                // Show success message (simple alert for now)
                // You can implement a toast notification system if desired
                console.log('Synonym added successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding the synonym');
        });
    }

    // Function to remove synonym
    function removeSynonym(synonym) {
        if (!confirm(`Are you sure you want to remove "${synonym}"?`)) {
            return;
        }
        
        // Send AJAX request to remove synonym
        fetch('keywords.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=remove_synonym&keyword_id=${currentKeywordId}&synonym=${encodeURIComponent(synonym)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentSynonyms = currentSynonyms.filter(s => s !== synonym);
                updateSynonymsList();
                console.log('Synonym removed successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while removing the synonym');
        });
    }
    </script>

</body>
</html>