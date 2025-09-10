<?php
session_start();

require_once '../config.php';
require_once __DIR__ . '/../controllers/FeatureController.php';

// Handle form submissions
$message = '';
$messageType = '';

// Initialize $action variable
$action = $_POST['action'] ?? '';

// Make sure $action is always defined (even for GET requests)
$action = $action ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Make sure $action is always defined
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['module_name'] ?? '');
        $description = trim($_POST['module_description'] ?? '');
        $systemNameId = isset($_POST['system_name_id']) && !empty($_POST['system_name_id']) ? (int)$_POST['system_name_id'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($name) && $systemNameId > 0) {
            try {
                $db = Database::getInstance()->getDatabase();
                $collection = $db->getCollection('modules');
                $systemNamesCollection = $db->getCollection('system_names');
                $overallCollection = $db->getCollection('overall'); // For AJAX compatibility
                
                // Verify system name exists and is active
                $systemName = $systemNamesCollection->findOne(['id' => $systemNameId, 'is_active' => true]);
                if (!$systemName) {
                    $message = 'Selected system name not found or inactive';
                    $messageType = 'error';
                } else {
                    // Check if module already exists for this system
                    $existing = $collection->findOne([
                        'name' => $name, 
                        'system_name_id' => $systemNameId,
                        'is_active' => true
                    ]);
                    
                    if ($existing) {
                        $message = 'Module already exists for this system';
                        $messageType = 'error';
                    } else {
                        // Get next ID
                        $lastDoc = $collection->findOne([], ['sort' => ['id' => -1]]);
                        $nextId = ($lastDoc['id'] ?? 0) + 1;
                        
                        // Insert into modules collection
                        $result = $collection->insertOne([
                            'id' => $nextId,
                            'name' => $name,
                            'module_description' => $description, // Use separate field for module descriptions
'description' => '', // Keep this empty - it's for feature descriptions
                            'system_name_id' => $systemNameId,
                            'system_name' => $systemName['name'], // Store for easier queries
                            'is_active' => (bool)$isActive,
                            'created_at' => new MongoDB\BSON\UTCDateTime(),
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]);
                        
                        // IMPORTANT: Also insert into overall collection for AJAX compatibility
// This ensures the module appears in cascading dropdowns
$overallCollection->insertOne([
    'system_name' => $systemName['name'],
    'module' => $name,
    'module_id' => $nextId, // ADD THIS LINE - Critical for proper linking
    'feature' => '', // Empty for now
    'client' => '', // Empty for now
    'source' => '', // Empty for now
    'module_description' => $description, // Use separate field for module descriptions
'description' => '', // Keep this empty - it's for feature descriptions
    'created_from' => 'modules_management',
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'is_active' => (bool)$isActive
]);
                        
                        $message = 'Module added successfully and will appear in dropdowns';
                        $messageType = 'success';
                    }
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
                error_log("Module add error: " . $e->getMessage());
            }
        } else {
            $message = 'Module name and system name are required';
            $messageType = 'error';
        }
    } elseif ($action === 'update') {
    $id = $_POST['module_id'] ?? '';
    $name = trim($_POST['module_name'] ?? '');
    $description = trim($_POST['module_description'] ?? '');
    
    // FIXED: Handle system_name_id more flexibly - allow empty/null
    $systemNameId = null;
    if (isset($_POST['system_name_id']) && !empty($_POST['system_name_id'])) {
        $rawSystemNameId = $_POST['system_name_id'];
        if (is_numeric($rawSystemNameId)) {
            $systemNameId = (int)$rawSystemNameId;
        }
    }
    
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // FIXED: Only require ID and name - system name is optional
    if (empty($id)) {
        $message = 'Module ID is required';
        $messageType = 'error';
    } elseif (empty($name)) {
        $message = 'Module name is required';
        $messageType = 'error';
    } else {
        try {
            $db = Database::getInstance()->getDatabase();
            $collection = $db->getCollection('modules');
            $systemNamesCollection = $db->getCollection('system_names');
            $overallCollection = $db->getCollection('overall');
            
            // Get current module data
            $currentModule = $collection->findOne(['id' => (int)$id]);
if (!$currentModule) {
    $message = 'Module not found';
    $messageType = 'error';
} else {
    // FIXED: Allow keeping existing system_name_id if none provided, or setting to null
    $finalSystemNameId = $systemNameId ?? $currentModule['system_name_id'] ?? null;
    $finalSystemName = $currentModule['system_name'] ?? '';
    
    // Only validate system name if one was explicitly provided
    if ($systemNameId !== null && $systemNameId > 0) {
        $systemName = $systemNamesCollection->findOne(['id' => $systemNameId, 'is_active' => true]);
        if (!$systemName) {
            $message = 'Selected system name not found or inactive';
            $messageType = 'error';
        } else {
            $finalSystemName = $systemName['name'];
        }
    } elseif (isset($_POST['system_name_id']) && empty($_POST['system_name_id'])) {
        // User explicitly wants to remove system association
        $finalSystemNameId = null;
        $finalSystemName = '';
                }
                
                // Only proceed if no system validation errors
                if ($messageType !== 'error') {
                    // Check dependencies and system change restrictions
                    $hasDependencies = $overallCollection->countDocuments(['module_id' => (int)$id]) > 0;
                    $isChangingSystem = ($currentModule['system_name_id'] != $finalSystemNameId);
                    
                    if ($hasDependencies && $isChangingSystem && $finalSystemNameId !== null) {
                        $dependencyCount = $overallCollection->countDocuments(['module_id' => (int)$id]);
                        $message = 'Cannot change system name - this module has ' . $dependencyCount . 
                                   ' dependency(ies) in the current system. ' .
                                   'Please remove or update the dependencies first.';
                        $messageType = 'error';
                    }
                    // Only check dependencies if trying to disable an active module
                    elseif ($hasDependencies && $currentModule['is_active'] && !$isActive) {
                        $dependencyCount = $overallCollection->countDocuments(['module_id' => (int)$id]);
                        $message = 'Cannot disable module - it has ' . $dependencyCount . 
                                   ' dependency(ies). ' .
                                   'Please remove or update the dependencies first.';
                        $messageType = 'error';
                    } else {
                        // ENHANCED: Check for duplicate name only if system is assigned
                        if ($finalSystemNameId !== null && $finalSystemNameId > 0) {
                            $duplicateCheck = $collection->findOne([
                                'name' => $name,
                                'system_name_id' => $finalSystemNameId,
                                'is_active' => true,
                                'id' => ['$ne' => (int)$id]
                            ]);

                            if ($duplicateCheck) {
                                $message = "A module named '{$name}' already exists for the system '{$finalSystemName}'. Please choose a different name.";
                                $messageType = 'error';
                            }
                        }
                        
                        // Proceed with update if no errors
                        if ($messageType !== 'error') {
                            // Prepare update data
                            $updateData = [
                                'name' => $name,
                                'description' => $description,
                                'is_active' => (bool)$isActive,
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ];
                            
                            // Only update system fields if they're provided/valid
                            if ($finalSystemNameId !== null) {
                                $updateData['system_name_id'] = $finalSystemNameId;
                                $updateData['system_name'] = $finalSystemName;
                            }
                            
                            // Update modules collection
                            $result = $collection->updateOne(
                                ['id' => (int)$id],
                                ['$set' => $updateData]
                            );
                            
                            if ($result->getMatchedCount() > 0) {
                                // Update related records in overall collection
                                $updatedFeaturesCount = 0;
                                
                                // Update by module_id (most reliable)
                                if ($finalSystemNameId !== null) {
                                    $updateResult = $overallCollection->updateMany(
                                        ['module_id' => (int)$id],
                                        ['$set' => [
                                            'system_name' => $finalSystemName,
                                            'module' => $name,
                                            'module_description' => $description,
                                            'is_active' => (bool)$isActive,
                                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                                        ]]
                                    );
                                    $updatedFeaturesCount = $updateResult->getModifiedCount();
                                }
                                
                                $message = 'Module updated successfully. ';
                                if ($updatedFeaturesCount > 0) {
                                    $message .= $updatedFeaturesCount . ' feature(s) were automatically updated.';
                                } else {
                                    $message .= 'No features needed updating.';
                                }
                                $messageType = 'success';

                                // Clear cache and redirect
                                $_SESSION['last_data_update'] = time();
                                $_SESSION['module_updated'] = true;
                                
                                header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=success&updated_count=' . $updatedFeaturesCount);
                                exit();
                            } else {
                                $message = 'Module not found or no changes were made';
                                $messageType = 'error';
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
            error_log("Update error: " . $e->getMessage());
        }
    }
}
} elseif ($action === 'add_orphaned') {
    $systemName = trim($_POST['system_name'] ?? '');
    $moduleName = trim($_POST['module_name'] ?? '');
    
    if (!empty($systemName) && !empty($moduleName)) {
        try {
            $db = Database::getInstance()->getDatabase();
            $collection = $db->getCollection('modules');
            $systemNamesCollection = $db->getCollection('system_names');
            
            // Find system name ID
            $systemNameDoc = $systemNamesCollection->findOne(['name' => $systemName]);
            $systemNameId = $systemNameDoc['id'] ?? 0;
            
            // Get next ID
            $lastDoc = $collection->findOne([], ['sort' => ['id' => -1]]);
            $nextId = ($lastDoc['id'] ?? 0) + 1;
            
            // Insert into modules collection
            $result = $collection->insertOne([
                'id' => $nextId,
                'name' => $moduleName,
                'description' => 'Added from orphaned module detection',
                'system_name_id' => $systemNameId,
                'system_name' => $systemName,
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            $message = 'Orphaned module added successfully';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error adding orphaned module: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'System name and module name are required';
        $messageType = 'error';
    }
}

// ONE-TIME DATA MIGRATION TO FIX MISSING SYSTEM RELATIONSHIPS
// Add this before the module fetching logic
if (isset($_GET['migrate']) && $_GET['migrate'] === 'fix_system_relationships') {
    try {
        $db = Database::getInstance()->getDatabase();
        $collection = $db->getCollection('modules');
        $systemNamesCollection = $db->getCollection('system_names');
        $overallCollection = $db->getCollection('overall');
        
        // Build system name lookups
        $systemNamesById = [];
        $systemNamesByName = [];
        $systemNamesCursor = $systemNamesCollection->find([]);
        
        foreach ($systemNamesCursor as $sysDoc) {
            $systemNamesById[$sysDoc['id']] = $sysDoc['name'];
            $systemNamesByName[$sysDoc['name']] = $sysDoc['id'];
        }
        
        // Build overall collection lookup
        $overallLookup = [];
        $overallCursor = $overallCollection->find(['module' => ['$ne' => '']]);
        foreach ($overallCursor as $overallDoc) {
            $moduleName = $overallDoc['module'] ?? '';
            $systemName = $overallDoc['system_name'] ?? '';
            
            if (!empty($moduleName) && !empty($systemName)) {
                $overallLookup[$moduleName] = $systemName;
            }
        }
        
        $fixedCount = 0;
        $modulesCursor = $collection->find([]);
        
        foreach ($modulesCursor as $moduleDoc) {
            $moduleName = $moduleDoc['name'] ?? '';
            $systemNameId = $moduleDoc['system_name_id'] ?? null;
            $systemName = $moduleDoc['system_name'] ?? '';
            
            $needsUpdate = false;
            $updateData = [];
            
            // Fix missing system_name_id
            if ($systemNameId === null && !empty($systemName) && isset($systemNamesByName[$systemName])) {
                $updateData['system_name_id'] = $systemNamesByName[$systemName];
                $needsUpdate = true;
            }
            
            // Fix missing system_name
            if (empty($systemName) && $systemNameId !== null && isset($systemNamesById[$systemNameId])) {
                $updateData['system_name'] = $systemNamesById[$systemNameId];
                $needsUpdate = true;
            }
            
            // Fix both from overall collection
            if ((empty($systemName) || $systemNameId === null) && !empty($moduleName) && isset($overallLookup[$moduleName])) {
                $foundSystemName = $overallLookup[$moduleName];
                if (isset($systemNamesByName[$foundSystemName])) {
                    if (empty($systemName)) {
                        $updateData['system_name'] = $foundSystemName;
                    }
                    if ($systemNameId === null) {
                        $updateData['system_name_id'] = $systemNamesByName[$foundSystemName];
                    }
                    $needsUpdate = true;
                }
            }
            
            if ($needsUpdate) {
                $collection->updateOne(
                    ['_id' => $moduleDoc['_id']],
                    ['$set' => $updateData]
                );
                $fixedCount++;
            }
        }
        
        echo "<div class='alert alert-success'>Migration completed! Fixed {$fixedCount} modules.</div>";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Migration failed: " . $e->getMessage() . "</div>";
    }
}

// Get all modules with system name info - COMPREHENSIVE FIX FOR MISSING SYSTEM RELATIONSHIPS
try {
    $db = Database::getInstance()->getDatabase();
    $collection = $db->getCollection('modules');
    $systemNamesCollection = $db->getCollection('system_names');
    $overallCollection = $db->getCollection('overall');
    
    $cursor = $collection->find([]);
    $modules = [];
    
    // Create comprehensive system names lookup (by both ID and name)
    $systemNamesById = [];
    $systemNamesByName = [];
    $systemNamesCursor = $systemNamesCollection->find([]);
    
    foreach ($systemNamesCursor as $sysDoc) {
        $systemNamesById[$sysDoc['id']] = [
            'id' => $sysDoc['id'],
            'name' => $sysDoc['name']
        ];
        $systemNamesByName[$sysDoc['name']] = [
            'id' => $sysDoc['id'],
            'name' => $sysDoc['name']
        ];
    }
    
    // ALSO create lookup from overall collection for modules that might only exist there
    $overallLookup = [];
    $overallCursor = $overallCollection->find(['module' => ['$ne' => '']]);
    foreach ($overallCursor as $overallDoc) {
        $moduleName = $overallDoc['module'] ?? '';
        $systemName = $overallDoc['system_name'] ?? '';
        
        if (!empty($moduleName) && !empty($systemName)) {
            $overallLookup[$moduleName] = $systemName;
        }
    }
    
    foreach ($cursor as $doc) {
    $moduleName = $doc['name'] ?? '';
    $systemNameId = $doc['system_name_id'] ?? null;
    $systemName = $doc['system_name'] ?? '';
    
    $needsUpdate = false;
    $updateData = [];
    
    // STEP 1: Try to resolve missing system_name_id from existing system_name
    if ($systemNameId === null && !empty($systemName)) {
        if (isset($systemNamesByName[$systemName])) {
            $systemNameId = $systemNamesByName[$systemName]['id'];
            $updateData['system_name_id'] = $systemNameId;
            $needsUpdate = true;
            error_log("Resolved system_name_id for module '{$moduleName}': {$systemNameId}");
        }
    }
    
    // STEP 2: Try to resolve missing system_name from existing system_name_id
    if (empty($systemName) && $systemNameId !== null) {
        if (isset($systemNamesById[$systemNameId])) {
            $systemName = $systemNamesById[$systemNameId]['name'];
            $updateData['system_name'] = $systemName;
            $needsUpdate = true;
            error_log("Resolved system_name for module '{$moduleName}': {$systemName}");
        }
    }
    
    // STEP 3: If both are still missing, try to find from overall collection
    if ((empty($systemName) || $systemNameId === null) && !empty($moduleName)) {
        if (isset($overallLookup[$moduleName])) {
            $foundSystemName = $overallLookup[$moduleName];
            
            if (isset($systemNamesByName[$foundSystemName])) {
                if (empty($systemName)) {
                    $systemName = $foundSystemName;
                    $updateData['system_name'] = $systemName;
                    $needsUpdate = true;
                }
                
                if ($systemNameId === null) {
                    $systemNameId = $systemNamesByName[$foundSystemName]['id'];
                    $updateData['system_name_id'] = $systemNameId;
                    $needsUpdate = true;
                }
                
                error_log("Resolved system relationship from overall collection for module '{$moduleName}': {$foundSystemName}");
            }
        }
    }
    
    // STEP 4: Update the module document if we found missing data
    if ($needsUpdate && !empty($updateData)) {
        try {
            $collection->updateOne(
                ['_id' => $doc['_id']],
                ['$set' => $updateData]
            );
            error_log("Updated module '{$moduleName}' with: " . json_encode($updateData));
        } catch (Exception $e) {
            error_log("Failed to update module '{$moduleName}': " . $e->getMessage());
        }
    }
        
        // If we still don't have system_name but have system_name_id, resolve system_name
        if (empty($systemName) && $systemNameId !== null) {
            $systemDoc = $systemNamesCollection->findOne(['id' => $systemNameId]);
            if ($systemDoc) {
                $systemName = $systemDoc['name'];
                
                // Update the document with the missing system_name
                try {
                    $collection->updateOne(
                        ['_id' => $doc['_id']],
                        ['$set' => ['system_name' => $systemName]]
                    );
                } catch (Exception $e) {
                    error_log("Failed to update system_name for module: " . $e->getMessage());
                }
            }
        }
        
        // Check for dependencies using multiple methods
        $hasDependencies = false;
        
        // Method 1: Check by module_id (if it exists)
        if (isset($doc['id'])) {
            $hasDependencies = $overallCollection->countDocuments(['module_id' => $doc['id']]) > 0;
        }
        
        // Method 2: Check by module name and system name combination
        if (!$hasDependencies && isset($doc['name']) && !empty($systemName)) {
            $hasDependencies = $overallCollection->countDocuments([
                'module' => $doc['name'],
                'system_name' => $systemName,
                'feature' => ['$exists' => true, '$ne' => '']
            ]) > 0;
        }
        
         $allSystemNames = [];
    $multipleSystemsQuery = $overallCollection->distinct('system_name', [
        'module' => $doc['name'],
        'system_name' => ['$ne' => '']
    ]);
    
    foreach ($multipleSystemsQuery as $sysName) {
        if (!empty($sysName)) {
            $allSystemNames[] = $sysName;
        }
    }
    
    // Remove duplicates and sort
    $allSystemNames = array_unique($allSystemNames);
    sort($allSystemNames);
    
    $hasMultipleSystemNames = count($allSystemNames) > 1;
    
    $modules[] = [
        'id' => $doc['id'],
        'name' => $doc['name'],
        'description' => $doc['description'] ?? '',
        'system_name_id' => $systemNameId ?? 0,
        'system_name' => $systemName ?: 'Independent Module',
        'is_active' => $doc['is_active'],
        'has_dependencies' => $hasDependencies,
        'has_multiple_systems' => $hasMultipleSystemNames,
        'all_system_names' => $allSystemNames,
        'created_at' => $doc['created_at'] ?? null,
        'updated_at' => $doc['updated_at'] ?? null
    ];
}
} catch (Exception $e) {
    $modules = [];
    $message = 'Error loading modules: ' . $e->getMessage();
    $messageType = 'error';
    error_log("Modules loading error: " . $e->getMessage());
}

// Get active system names for dropdown
$systemNames = [];
try {
    $db = Database::getInstance()->getDatabase();
    $systemNamesCollection = $db->getCollection('system_names');
    
    // Get all active system names directly from the collection
    $cursor = $systemNamesCollection->find(['is_active' => true], ['sort' => ['name' => 1]]);
    
    foreach ($cursor as $systemDoc) {
        $systemNames[] = [
            'id' => $systemDoc['id'],
            'name' => $systemDoc['name']
        ];
    }
} catch (Exception $e) {
    $systemNames = [];
    // Optionally add error message
    if (empty($message)) {
        $message = 'Error loading system names: ' . $e->getMessage();
        $messageType = 'error';
    }
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
    <title>Modules Management - Media Track</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    
    <style>
        .btn-info {
    background-color: #0dcaf0;
    border-color: #0dcaf0;
    color: #000;
}

.btn-info:hover {
    background-color: #31d2f2;
    border-color: #25cff2;
    color: #000;
}

.multiple-systems-indicator {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
    font-size: 0.75rem;
}
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
            max-width: 200px;
            word-wrap: break-word;
            white-space: normal;
        }
        .system-name-badge {
            background-color: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }
        .ajax-sync-badge {
            background-color: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
            font-size: 0.75rem;
        }
        .unknown-system {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
                            <li><a class="dropdown-item active" href="modules.php">Modules</a></li>
                            <li><a class="dropdown-item" href="clients.php">Clients</a></li>
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
            <h1 class="fw-bold display-6">Modules Management</h1>
            <p class="lead mt-3">
                Manage and configure modules for your media tracking application.<br>
                Create, update, and organize modules under their respective system names.
            </p>
        </div>
    </section>

    <div class="divider-line"></div>

    <!-- Main Content -->
    <section class="py-5 bg-white">
        <div class="container">

            <!-- Flash Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" id="flashMessage">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php
// Check for recent module moves
if (isset($_SESSION['module_move_detected'])) {
    $moveData = $_SESSION['module_move_detected'];
    
    // Show notification for 30 seconds after move
    if (time() - $moveData['timestamp'] < 30) {
        ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Module Moved:</strong> 
            "<?= htmlspecialchars($moveData['module_name']) ?>" was moved from 
            "<?= htmlspecialchars($moveData['old_system']) ?>" to 
            "<?= htmlspecialchars($moveData['new_system']) ?>".
            <br>
            <small class="text-muted">
                <?= $moveData['affected_features'] ?> feature(s) were automatically updated.
            </small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
    }
    
    // Clear the notification
    unset($_SESSION['module_move_detected']);
}

// Also check URL parameter
if (isset($_GET['updated_count']) && is_numeric($_GET['updated_count'])) {
    $updatedCount = (int)$_GET['updated_count'];
    if ($updatedCount > 0) {
        ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> <?= $updatedCount ?> feature(s) were updated to reflect module changes.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php
    }
}
            ?>

            <!-- Add New Module Section -->
            <div class="content-box p-4 p-md-5 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">Add New Module</h3>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary" href="../index.php">
                            Back to Home
                        </a>
                    </div>
                </div>

                <form method="POST" id="addModuleForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="moduleName" class="form-label">Module Name <span class="text-danger">*</span></label>
                            <input type="text" name="module_name" id="moduleName" class="form-control" 
                                   placeholder="Enter module name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="systemNameId" class="form-label">System Name <span class="text-danger">*</span></label>
                            <select name="system_name_id" id="systemNameId" class="form-select" required>
                                <option value="">Select a system name...</option>
                                <?php foreach ($systemNames as $systemName): ?>
                                    <option value="<?php echo $systemName['id']; ?>">
                                        <?php echo htmlspecialchars($systemName['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="moduleDescription" class="form-label">Description</label>
                            <textarea name="module_description" id="moduleDescription" class="form-control" 
                                      rows="3" placeholder="Enter module description (optional)"></textarea>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active (visible in main form and AJAX dropdowns)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Add Module
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
                <h3 class="mb-4">Modules List</h3>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Module Name</th>
                                <th>System Name</th>
                                <th>Status</th>
                                <th>Dependencies</th>
                                <th>Description</th>
                                <th>Created / Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($modules)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <p class="mb-0">No modules found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><?php echo $module['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($module['name']); ?>
                                        </td>
                                        <td>
    <?php if ($module['has_multiple_systems']): ?>
        <button type="button" 
    class="badge bg-success text-white border-0"
    style="cursor: pointer;"
    onclick="showSystemNames('<?php echo htmlspecialchars($module['name'], ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($module['all_system_names']), ENT_QUOTES); ?>)">
    <i class="fas fa-list me-1"></i>
    Check System Names (<?php echo count($module['all_system_names']); ?>)
</button>
    <?php else: ?>
        <span class="badge <?php echo $module['system_name'] === 'Unknown System' ? 'unknown-system' : 'system-name-badge'; ?>">
            <?php echo htmlspecialchars($module['system_name']); ?>
            <?php if ($module['system_name'] === 'Unknown System'): ?>
                <i class="fas fa-exclamation-triangle ms-1"></i>
            <?php endif; ?>
        </span>
    <?php endif; ?>
</td>
                                        <td>
                                            <?php if ($module['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($module['has_dependencies']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Has Dependencies
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="description-cell">
                                            <?php if (!empty($module['description'])): ?>
                                                <?php echo htmlspecialchars($module['description']); ?>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div><strong>Created:</strong> 
                                                    <?php echo $module['created_at'] ? date('M d, Y H:i', $module['created_at']->toDateTime()->getTimestamp()) : 'N/A'; ?>
                                                </div>
                                                <?php if ($module['updated_at'] && $module['updated_at'] != $module['created_at']): ?>
                                                <div class="text-muted"><strong>Updated:</strong> 
                                                    <?php echo date('M d, Y H:i', $module['updated_at']->toDateTime()->getTimestamp()); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
    <button type="button" class="btn btn-sm <?php echo $module['system_name_id'] > 0 ? 'btn-outline-success' : 'btn-outline-warning'; ?>" 
            onclick="editModule(<?php echo $module['id']; ?>, '<?php echo htmlspecialchars($module['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($module['description'], ENT_QUOTES); ?>', <?php echo $module['system_name_id'] ?: 0; ?>, <?php echo $module['is_active'] ? 'true' : 'false'; ?>, <?php echo $module['has_dependencies'] ? 'true' : 'false'; ?>)">
        <i class="fas fa-edit me-1"></i>
        <?php if ($module['system_name_id'] > 0): ?>
            Edit
        <?php else: ?>
            Populate & Edit
        <?php endif; ?>
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
                    <h5 class="modal-title" id="editModalLabel">Edit Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editModuleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="module_id" id="editModuleId">
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>AJAX Sync:</strong> Changes will automatically update in all cascading dropdowns.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editModuleName" class="form-label">Module Name <span class="text-danger">*</span></label>
                                <input type="text" name="module_name" id="editModuleName" class="form-control" 
                                       placeholder="Enter module name" required>
                            </div>
                            <div class="col-md-6">
    <label for="editSystemNameId" class="form-label">
        System Name 
        <small class="text-muted">(Optional - leave blank for independent modules)</small>
    </label>
    <select name="system_name_id" id="editSystemNameId" class="form-select">
        <option value="">-- Independent Module --</option>
        <?php foreach ($systemNames as $systemName): ?>
            <option value="<?php echo $systemName['id']; ?>">
                <?php echo htmlspecialchars($systemName['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="form-text text-muted">
        <i class="fas fa-info-circle me-1"></i>
        Independent modules can be assigned to a system later, or left unassigned for flexibility.
    </div>
</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editModuleDescription" class="form-label">Description</label>
                            <textarea name="module_description" id="editModuleDescription" class="form-control" 
                                      rows="3" placeholder="Enter module description (optional)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="editIsActive">
                                <label class="form-check-label" for="editIsActive">
                                    Active (visible in main form and AJAX dropdowns)
                                </label>
                            </div>
                            <div id="editDependencyWarning" class="text-warning mt-1 small" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Cannot disable - this module has dependencies
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Module
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- System Names Modal -->
<div class="modal fade" id="systemNamesModal" tabindex="-1" aria-labelledby="systemNamesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="systemNamesModalLabel">System Names for Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This module appears in multiple systems. This may indicate data inconsistency.
                </div>
                
                <h6 class="mb-3">Module: <strong id="modalModuleName"></strong></h6>
                
                <div class="list-group" id="systemNamesList">
                    <!-- System names will be populated here -->
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-lightbulb me-1"></i>
                        <strong>Tip:</strong> Consider consolidating this module under a single system for better data consistency.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <?php
    // Detect orphaned modules
    $orphanedModules = [];
    try {
        $db = Database::getInstance()->getDatabase();
        $overallCollection = $db->getCollection('overall');
        $modulesCollection = $db->getCollection('modules');
        
        // Get modules that exist in overall but not in modules collection
        $pipeline = [
            ['$match' => ['module' => ['$ne' => ''], 'system_name' => ['$ne' => '']]],
            ['$group' => ['_id' => ['system_name' => '$system_name', 'module' => '$module']]],
            ['$lookup' => [
                'from' => 'modules',
                'let' => ['sys' => '$_id.system_name', 'mod' => '$_id.module'],
                'pipeline' => [
                    ['$match' => [
                        '$expr' => [
                            '$and' => [
                                ['$eq' => ['$system_name', '$$sys']],
                                ['$eq' => ['$name', '$$mod']]
                            ]
                        ]
                    ]]
                ],
                'as' => 'module_match'
            ]],
            ['$match' => ['module_match' => []]],
            ['$project' => ['system_name' => '$_id.system_name', 'module' => '$_id.module', '_id' => 0]]
        ];
        
        $orphanedCursor = $overallCollection->aggregate($pipeline);
        $orphanedModules = iterator_to_array($orphanedCursor);
        
    } catch (Exception $e) {
        error_log("Error detecting orphaned modules: " . $e->getMessage());
    }
    ?>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Function to show system names modal
function showSystemNames(moduleName, systemNames) {
    document.getElementById('modalModuleName').textContent = moduleName;
    
    const systemNamesList = document.getElementById('systemNamesList');
    systemNamesList.innerHTML = '';
    
    systemNames.forEach((systemName, index) => {
        const listItem = document.createElement('div');
        listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
        listItem.innerHTML = `
            <div>
                <i class="fas fa-cog text-primary me-2"></i>
                <strong>${systemName}</strong>
            </div>
            <span class="badge bg-primary rounded-pill">${index + 1}</span>
        `;
        systemNamesList.appendChild(listItem);
    });
    
    const systemNamesModal = new bootstrap.Modal(document.getElementById('systemNamesModal'));
    systemNamesModal.show();
}

// Global variables for edit modal
let currentModuleHasDependencies = false;
let currentModuleIsActive = false;

// Function to edit module
function editModule(id, name, description, systemNameId, isActive, hasDependencies) {
    // Validate essential parameters
    if (!id || !name) {
        alert('Invalid module data. Please refresh the page and try again.');
        return;
    }
    
    // Handle missing system link case
    const isMissingSystemLink = !systemNameId || systemNameId === 0;

    // Set form values
    document.getElementById('editModuleId').value = id;
    document.getElementById('editModuleName').value = name;
    document.getElementById('editModuleDescription').value = description || '';
    document.getElementById('editSystemNameId').value = systemNameId || '';
    document.getElementById('editIsActive').checked = isActive;

    const dependencyWarning = document.getElementById('editDependencyWarning');
    const editIsActiveCheckbox = document.getElementById('editIsActive');
    
    // Store dependency and active status globally
    currentModuleHasDependencies = hasDependencies;
    currentModuleIsActive = isActive;

    // Handle dependency constraints for status changes
    if (hasDependencies && isActive) {
        dependencyWarning.style.display = 'block';
        editIsActiveCheckbox.style.pointerEvents = 'none';
        editIsActiveCheckbox.style.opacity = '0.6';
        editIsActiveCheckbox.title = 'Cannot disable module with dependencies';
    } else {
        dependencyWarning.style.display = 'none';
        editIsActiveCheckbox.style.pointerEvents = 'auto';
        editIsActiveCheckbox.style.opacity = '1';
        editIsActiveCheckbox.title = '';
    }

    // Update modal title
    const modalTitle = document.getElementById('editModalLabel');
    if (isMissingSystemLink) {
        modalTitle.textContent = 'Edit Module (Optional: Assign System)';
        modalTitle.className = 'modal-title text-info';
    } else {
        modalTitle.textContent = 'Edit Module';
        modalTitle.className = 'modal-title';
    }

    // Handle system name field based on dependencies
    const systemNameSelect = document.getElementById('editSystemNameId');
    if (hasDependencies) {
        systemNameSelect.disabled = true;
        systemNameSelect.title = 'System name cannot be changed - module has dependencies';
        
        // Add help text if it doesn't exist
        if (!document.getElementById('systemNameHelpText')) {
            const helpText = document.createElement('div');
            helpText.id = 'systemNameHelpText';
            helpText.className = 'form-text text-warning';
            helpText.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>System name cannot be changed - this module has dependencies in the current system.';
            systemNameSelect.parentNode.appendChild(helpText);
        }
    } else {
        systemNameSelect.disabled = false;
        systemNameSelect.title = '';
        
        // Remove help text if it exists
        const helpText = document.getElementById('systemNameHelpText');
        if (helpText) {
            helpText.remove();
        }
        
        // Add info for independent modules
        if (isMissingSystemLink && !document.getElementById('independentModuleInfo')) {
            const infoText = document.createElement('div');
            infoText.id = 'independentModuleInfo';
            infoText.className = 'form-text text-info';
            infoText.innerHTML = '<i class="fas fa-info-circle me-1"></i>This module is currently independent. You can optionally assign it to a system or leave it as-is.';
            systemNameSelect.parentNode.appendChild(infoText);
        }
    }

    // Show the modal
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// SINGLE DOMContentLoaded event listener - consolidating all functionality
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['last_data_update'])): ?>
    window.phpLastUpdate = <?php echo $_SESSION['last_data_update']; ?>;
    <?php endif; ?>

    // Auto-hide flash messages after 8 seconds
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(function() {
            const alertInstance = bootstrap.Alert.getOrCreateInstance(flashMessage);
            alertInstance.close();
        }, 8000);
    }

    // Settings dropdown functionality
    const dropdown = document.getElementById('settingsDropdown');
    const dropdownMenu = document.getElementById('settingsDropdownMenu');
    const dropdownToggle = document.getElementById('settingsMenu');
    
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

    // Handle edit modal functionality
    const editIsActiveCheckbox = document.getElementById('editIsActive');
    const editDependencyWarning = document.getElementById('editDependencyWarning');

    if (editIsActiveCheckbox && editDependencyWarning) {
        editIsActiveCheckbox.addEventListener('change', function(e) {
            if (currentModuleHasDependencies && currentModuleIsActive && !this.checked) {
                e.preventDefault();
                this.checked = true;
                return false;
            }
        });
    }

    // Form validation for add module form (requires system name)
    const addModuleForm = document.getElementById('addModuleForm');
    if (addModuleForm) {
        addModuleForm.addEventListener('submit', function(e) {
            const moduleName = document.getElementById('moduleName').value.trim();
            const systemNameId = document.getElementById('systemNameId').value;
            
            if (!moduleName) {
                e.preventDefault();
                alert('Module name is required');
                document.getElementById('moduleName').focus();
                return false;
            }
            
            if (!systemNameId) {
                e.preventDefault();
                alert('Please select a system name');
                document.getElementById('systemNameId').focus();
                return false;
            }
        });
    }

    // Form validation for edit module form (system name is optional)
    const editModuleForm = document.getElementById('editModuleForm');
    if (editModuleForm) {
        editModuleForm.addEventListener('submit', function(e) {
            const moduleName = document.getElementById('editModuleName').value.trim();
            
            if (!moduleName) {
                e.preventDefault();
                alert('Module name is required');
                document.getElementById('editModuleName').focus();
                return false;
            }
            
            // System name is optional for edit - no validation needed
        });
    }

    // Real-time duplicate checking for edit form
    const editModuleName = document.getElementById('editModuleName');
    const editSystemNameId = document.getElementById('editSystemNameId');
    
    if (editModuleName && editSystemNameId) {
        function createDuplicateWarning() {
            const warningDiv = document.createElement('div');
            warningDiv.id = 'editDuplicateWarning';
            warningDiv.className = 'alert alert-danger mt-1';
            warningDiv.style.display = 'none';
            warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>A module with this name already exists in the selected system.';
            editModuleName.parentNode.appendChild(warningDiv);
            return warningDiv;
        }

        const duplicateWarning = document.getElementById('editDuplicateWarning') || createDuplicateWarning();
        
        function checkDuplicateName() {
            const currentModuleId = document.getElementById('editModuleId').value;
            const moduleName = editModuleName.value.trim();
            const systemNameId = editSystemNameId.value;
            
            if (!moduleName || !systemNameId) {
                duplicateWarning.style.display = 'none';
                return;
            }
            
            // Make AJAX call to check for duplicates
            fetch('controllers/check_duplicate_module.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    module_name: moduleName,
                    system_name_id: parseInt(systemNameId),
                    exclude_id: parseInt(currentModuleId)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.isDuplicate) {
                    duplicateWarning.style.display = 'block';
                    editModuleName.classList.add('is-invalid');
                    editModuleName.classList.remove('is-valid');
                } else {
                    duplicateWarning.style.display = 'none';
                    editModuleName.classList.remove('is-invalid');
                    if (moduleName !== '') {
                        editModuleName.classList.add('is-valid');
                    }
                }
            })
            .catch(error => {
                console.error('Error checking duplicate:', error);
                duplicateWarning.style.display = 'none';
            });
        }
        
        // Add event listeners for duplicate checking
        editModuleName.addEventListener('input', debounce(checkDuplicateName, 500));
        editSystemNameId.addEventListener('change', checkDuplicateName);
    }
});
    </script>
</body>
</html>