<?php
session_start();
include 'config.php'; // Includes the database connection configuration

// --- Pagination Setup ---
$limit = 10; // Number of features to display per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1); // Ensures the page number is at least 1 (prevents negative pages)
$skip = ($page - 1) * $limit; // Calculates how many documents to skip for the current page

// Count total documents for pagination
$totalFeatures = $collection->countDocuments();
$totalPages = ceil($totalFeatures / $limit); // Calculate total number of pages
// --- End Pagination Setup ---


// --- Handle Feature Update ---
if (isset($_POST['update_feature'])) {
    $id = $_POST['edit_id'];
    $existing = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]); // Fetch existing document

    // Determine the current file path
    $uploadPath = $existing['sample_file'] ?? '';

    $newFileUploaded = !empty($_FILES['edit_sample_file']['name']);
    $fileRemoved = isset($_POST['delete_file']) && $_POST['delete_file'] == '1';

    // Validation: If the user explicitly removed the file but didn't upload a new one
    if ($fileRemoved && !$newFileUploaded) {
        $_SESSION['error'] = "⚠️ Please upload a new file before saving.";
        header("Location: index.php");
        exit;
    }

    // Handle new file upload
    if ($newFileUploaded) {
        // If an old file exists, you might want to delete it from the server here
        // if (!empty($existing['sample_file']) && file_exists($existing['sample_file'])) {
        //     unlink($existing['sample_file']);
        // }
        $uploadPath = 'uploads/' . basename($_FILES['edit_sample_file']['name']);
        move_uploaded_file($_FILES['edit_sample_file']['tmp_name'], $uploadPath);
    } elseif ($fileRemoved) {
        // If file was explicitly removed and no new file uploaded, clear the path
        // You might also want to delete the actual file from the server here
        // if (!empty($existing['sample_file']) && file_exists($existing['sample_file'])) {
        //     unlink($existing['sample_file']);
        // }
        $uploadPath = '';
    }

    // Update the document in MongoDB
    $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        ['$set' => [
            'system_name'  => $_POST['edit_system_name'],
            'module'       => $_POST['edit_module'],
            'description'  => $_POST['edit_description'],
            'client'       => $_POST['edit_client'],
            'sample_file'  => $uploadPath, // Save the new or updated file path
            'updated_at'   => new MongoDB\BSON\UTCDateTime() // Record update timestamp
        ]]
    );

    $_SESSION['success'] = "✅ Feature updated successfully!";
    header("Location: index.php");
    exit;
}
// --- End Handle Feature Update ---

// --- Handle New Feature Addition ---
if (isset($_POST['add'])) {
    $_SESSION['old_input'] = $_POST; // Store old input for re-population in case of error

    // Basic validation for required fields
    if (empty($_POST['client']) || empty($_FILES['sample_file']['name'])) {
        $_SESSION['error'] = "⚠️ Please fill in all required fields including Client and Sample File.";
    } else {
        $uploadPath = 'uploads/' . basename($_FILES['sample_file']['name']);
        move_uploaded_file($_FILES['sample_file']['tmp_name'], $uploadPath); // Upload the file to the server

        // Insert new document into MongoDB
        $collection->insertOne([
            'system_name' => $_POST['system_name'],
            'module'      => $_POST['module'],
            'description' => $_POST['description'],
            'client'      => $_POST['client'],
            'sample_file' => $uploadPath, // Store the file path
            'created_at'  => new MongoDB\BSON\UTCDateTime() // Record creation timestamp
        ]);

        unset($_SESSION['old_input']); // Clear old input on successful addition
        $_SESSION['success'] = "✅ Feature added successfully!";
        header("Location: index.php");
        exit;
    }
}
// --- End Handle New Feature Addition ---
?>

<!DOCTYPE html>
<html>
<head>
    <title>Features Documentation Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; }
        .hero h1 { font-size: 2.2rem; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand text-success" href="#">Media <span class="text-dark">Track</span></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero py-5 text-center bg-light">
    <div class="container">
        <h1 class="fw-bold">How We Support the <span class="text-success">Media Intelligence</span> Community</h1>
        <p class="text-muted mt-3">Your centralized solution to document, update, and manage system features with clarity.</p>
    </div>
</section>

<div class="container py-4">
    <div class="card p-4 mb-5 shadow-sm">
        <h4 class="mb-3">Add New Feature</h4>
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">System Name</label>
                    <input type="text" name="system_name" class="form-control" required
                           placeholder="Enter system name"
                           value="<?= $_SESSION['old_input']['system_name'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Module</label>
                    <input type="text" name="module" class="form-control" required
                           placeholder="Enter module name"
                           value="<?= $_SESSION['old_input']['module'] ?? '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" required
                              placeholder="Describe the feature or functionality"><?= $_SESSION['old_input']['description'] ?? '' ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client</label>
                    <input type="text" name="client" class="form-control" required
                           placeholder="Enter client or department name"
                           value="<?= $_SESSION['old_input']['client'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sample File (image/txt)</label>
                    <input type="file" name="sample_file" class="form-control" required
                           placeholder="Upload an image or .txt file">
                </div>
                <div class="col-12">
                    <button class="btn btn-success w-100" name="add">Add Feature</button>
                </div>
            </div>
        </form>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Features</h4>
        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search..." onkeyup="filterTable()">
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle custom-table">
           <thead class="table-header-green">
                <tr>
                    <th>Date Added</th>
                    <th>System</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>Client</th>
                    <th>Sample</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch features from MongoDB with pagination
                $features = $collection->find([], [
                    'sort' => ['created_at' => -1], // Sort by creation date, newest first
                    'skip' => $skip, // Skip documents for pagination
                    'limit' => $limit // Limit documents per page
                ]);

                foreach ($features as $f):
                    $id = (string)$f['_id']; // Convert MongoDB ObjectId to string for HTML IDs
                ?>
                <tr>
                    <td>
                        <?php
                        if (isset($f['created_at'])) {
                            $dt = $f['created_at']->toDateTime();
                            $dt->setTimezone(new DateTimeZone('Asia/Manila')); // Set timezone for display
                            echo $dt->format('Y-m-d') . ' | ' . $dt->format('H:i');
                        } else {
                            echo '<span class="text-muted">N/A</span>';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($f['system_name']) ?></td>
                    <td><?= htmlspecialchars($f['module']) ?></td>
                    <td><?= htmlspecialchars($f['description']) ?></td>
                    <td><?= htmlspecialchars($f['client']) ?></td>
                    <td>
                        <?php if (!empty($f['sample_file'])):
                            $ext = strtolower(pathinfo($f['sample_file'], PATHINFO_EXTENSION));
                            $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                            $icons = [
                                'pdf' => '📄',
                                'doc' => '📝', 'docx' => '📝',
                                'txt' => '📃',
                                'zip' => '🗜️', 'rar' => '🗜️',
                            ];

                            if (in_array($ext, $imageTypes)): ?>
                                <a href="<?= htmlspecialchars($f['sample_file']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($f['sample_file']) ?>" alt="Preview" style="max-height: 50px; border: 1px solid #ccc; border-radius: 5px;">
                                </a>
                            <?php else:
                                $icon = $icons[$ext] ?? '📁';
                                $filename = basename($f['sample_file']);
                                $truncated = strlen($filename) > 20 ? substr($filename, 0, 17) . '...' : $filename;
                            ?>
                                <?= $icon ?>
                                <a href="<?= htmlspecialchars($f['sample_file']) ?>" target="_blank" title="<?= htmlspecialchars($filename) ?>">
                                    <?= htmlspecialchars($truncated) ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-edit" data-bs-toggle="modal" data-bs-target="#edit<?= $id ?>">Edit</button>
                            <button class="btn btn-sm btn-delete" data-bs-toggle="modal" data-bs-target="#delete<?= $id ?>">Delete</button>
                        </div>

                        <div class="modal fade" id="edit<?= $id ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" enctype="multipart/form-data" class="modal-content" id="editModal<?= $id ?>" onsubmit="return handleSaveEdit('<?= $id ?>')">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Feature</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="handleCancelEdit('<?= $id ?>')"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="edit_id" value="<?= $id ?>">
                                        <div class="mb-3">
                                            <label class="form-label">System Name</label>
                                            <input name="edit_system_name" value="<?= htmlspecialchars($f['system_name']) ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Module</label>
                                            <input name="edit_module" value="<?= htmlspecialchars($f['module']) ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="edit_description" class="form-control" rows="2" required><?= htmlspecialchars($f['description']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Client</label>
                                            <input name="edit_client" value="<?= htmlspecialchars($f['client']) ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Sample File (image/txt)</label>
                                            <input type="file" name="edit_sample_file" class="form-control">
                                            <?php if (!empty($f['sample_file'])): ?>
                                            <div class="mt-2" id="file-wrapper-<?= $id ?>" data-original-url="<?= htmlspecialchars($f['sample_file']) ?>">
                                                <div id="file-preview-<?= $id ?>" class="d-flex align-items-center gap-2">
                                                    <a href="<?= htmlspecialchars($f['sample_file']) ?>" target="_blank" class="text-decoration-underline">View current file</a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile('<?= $id ?>')">❌ Remove</button>
                                                </div>
                                                <div class="alert alert-warning alert-dismissible fade show mt-2 d-none" id="file-alert-<?= $id ?>" role="alert">
                                                    Sample file marked for removal.
                                                    <button type="button" class="btn-close" onclick="hideAlert('<?= $id ?>')"></button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="delete_file" id="delete_file_<?= $id ?>" value="0">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="update_feature" class="btn btn-success">Save</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="handleCancelEdit('<?= $id ?>')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="modal fade" id="delete<?= $id ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="POST" action="delete.php" class="modal-content">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Deletion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete <strong><?= htmlspecialchars($f['system_name']) ?></strong>?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="delete_feature" class="btn btn-delete">Yes, Delete</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="toastMessage" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastContent">Success message here</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
// Function to display toast messages (success/error notifications)
function showToast(message, type = 'success') {
    const toastEl = document.getElementById('toastMessage');
    const toastBody = document.getElementById('toastContent');

    toastEl.classList.remove('bg-success', 'bg-danger'); // Remove existing background classes
    toastEl.classList.add(type === 'error' ? 'bg-danger' : 'bg-success'); // Add appropriate background
    toastBody.innerText = message; // Set toast message

    const toast = new bootstrap.Toast(toastEl);
    toast.show(); // Show the toast
}

/*
// --- Example of Server-Side Search (more robust for larger datasets) ---
// This commented-out section shows how you *could* implement a server-side search
// by sending an AJAX request to a 'search.php' file.
// The current `filterTable()` function performs client-side filtering.
document.getElementById("searchInput").addEventListener("input", async function () {
    const query = this.value.trim();

    if (query === '') {
        location.reload(); // If input is cleared, reload to show all again
        return;
    }

    try {
        // Fetches data from search.php based on the query
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json(); // Assumes search.php returns JSON

        const tbody = document.querySelector("table tbody");
        tbody.innerHTML = ''; // Clear current table

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No results found.</td></tr>';
            return;
        }

        // Populates the table with search results
        data.forEach(f => {
            let sampleCell = '';
            const ext = f.sample_file ? f.sample_file.split('.').pop().toLowerCase() : '';
            const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const icons = {
                pdf: '📄', doc: '📝', docx: '📝',
                txt: '📃', zip: '🗜️', rar: '🗜️'
            };

            if (!f.sample_file) {
                sampleCell = `<span class="text-muted">N/A</span>`;
            } else if (imageTypes.includes(ext)) {
                sampleCell = `<a href="${f.sample_file}" target="_blank">
                                    <img src="${f.sample_file}" alt="Preview" style="max-height: 50px; border: 1px solid #ccc; border-radius: 5px;">
                                  </a>`;
            } else {
                const icon = icons[ext] || '📁';
                const filename = f.sample_file.split('/').pop();
                const truncated = filename.length > 20 ? filename.slice(0, 17) + '...' : filename;
                sampleCell = `${icon} <a href="${f.sample_file}" target="_blank" title="${filename}">${truncated}</a>`;
            }

            // Note: The edit and delete buttons are disabled in this example as their modals
            // would need dynamic generation or attachment which is more complex with AJAX search results.
            tbody.innerHTML += `
                <tr>
                    <td>${f.created_at}</td>
                    <td>${f.system_name}</td>
                    <td>${f.module}</td>
                    <td>${f.description}</td>
                    <td>${f.client}</td>
                    <td>${sampleCell}</td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-edit disabled">Edit</button>
                            <button class="btn btn-sm btn-delete disabled">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        });

    } catch (err) {
        console.error("Search error:", err);
    }
});
*/
/** End Search Functionality **/


/** 🗂️ FILE PREVIEW & EDIT/REMOVE FUNCTIONALITY **/

// Object to keep track of files marked for removal during edit
const removedFiles = {};

// Function to handle the "Remove" button click for a file
function removeFile(id) {
    removedFiles[id] = true; // Mark file as removed for this ID

    const preview = document.getElementById('file-preview-' + id);
    const alert = document.getElementById('file-alert-' + id);
    const deleteInput = document.getElementById('delete_file_' + id);

    if (preview) preview.remove(); // Remove the "View current file" link and "Remove" button
    if (alert) alert.classList.remove('d-none'); // Show the "Sample file marked for removal" alert
    if (deleteInput) deleteInput.value = "1"; // Set hidden input to 1 to signal file deletion on server

    // Auto-dismiss the alert after 5 seconds
    setTimeout(() => hideAlert(id), 5000);
}

// Function to hide the "Sample file marked for removal" alert
function hideAlert(id) {
    const alertBox = document.getElementById('file-alert-' + id);
    if (alertBox) alertBox.classList.add('d-none');
}

const pagination = document.querySelector(".pagination"); // Add this near top of your event listener

document.getElementById("searchInput").addEventListener("input", async function () {
    const query = this.value.trim();
    const tbody = document.querySelector("table tbody");

    if (query === '') {
        location.reload(); // Restore full dataset with pagination
        return;
    }

    // 👇 Hide pagination while searching
    if (pagination) pagination.style.display = 'none';

    try {
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();

        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No results found.</td></tr>';
            return;
        }

        data.forEach(f => {
            // your sampleCell and row rendering logic here...
            // (same as before, omitted for brevity)
        });

    } catch (err) {
        console.error("❌ Search error:", err);
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error fetching results.</td></tr>';
    }
});

// Function to handle "Cancel" button click in the edit modal
function handleCancelEdit(id) {
    // If a file was marked for removal but the edit was canceled, restore the preview
    if (removedFiles[id]) {
        const wrapper = document.getElementById('file-wrapper-' + id);
        const alert = document.getElementById('file-alert-' + id);
        const deleteInput = document.getElementById('delete_file_' + id);

        // Restore the file preview if it's currently missing from the DOM
        if (wrapper && !document.getElementById('file-preview-' + id)) {
            const viewUrl = wrapper.getAttribute('data-original-url'); // Get original file URL
            const preview = document.createElement('div');
            preview.id = 'file-preview-' + id;
            preview.className = 'd-flex align-items-center gap-2';

            const viewLink = document.createElement('a');
            viewLink.href = viewUrl;
            viewLink.target = '_blank';
            viewLink.className = 'text-decoration-underline';
            viewLink.innerText = 'View current file';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.innerText = '❌ Remove';
            removeBtn.onclick = () => removeFile(id);

            preview.appendChild(viewLink);
            preview.appendChild(removeBtn);
            wrapper.prepend(preview); // Add the preview back to the beginning of the wrapper
        }

        if (alert) alert.classList.add('d-none'); // Hide the alert
        if (deleteInput) deleteInput.value = "0"; // Reset the delete_file flag
        removedFiles[id] = false; // Unmark file as removed
    }
}

document.getElementById("searchInput").addEventListener("input", async function () {
    const query = this.value.trim();

    const tbody = document.querySelector("table tbody");

    if (query === '') {
        location.reload(); // Reload full table if search is cleared
        return;
    }

    try {
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();

        tbody.innerHTML = ''; // Clear table before inserting new data

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No results found.</td></tr>';
            return;
        }

        data.forEach(f => {
            let sampleCell = '';
            const ext = f.sample_file ? f.sample_file.split('.').pop().toLowerCase() : '';
            const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const icons = {
                pdf: '📄', doc: '📝', docx: '📝',
                txt: '📃', zip: '🗜️', rar: '🗜️'
            };

            if (!f.sample_file) {
                sampleCell = `<span class="text-muted">N/A</span>`;
            } else if (imageTypes.includes(ext)) {
                sampleCell = `<a href="${f.sample_file}" target="_blank">
                                <img src="${f.sample_file}" alt="Preview" style="max-height: 50px; border: 1px solid #ccc; border-radius: 5px;">
                              </a>`;
            } else {
                const icon = icons[ext] || '📁';
                const filename = f.sample_file.split('/').pop();
                const truncated = filename.length > 20 ? filename.slice(0, 17) + '...' : filename;
                sampleCell = `${icon} <a href="${f.sample_file}" target="_blank" title="${filename}">${truncated}</a>`;
            }

            tbody.innerHTML += `
                <tr>
                    <td>${f.created_at}</td>
                    <td>${f.system_name}</td>
                    <td>${f.module}</td>
                    <td>${f.description}</td>
                    <td>${f.client}</td>
                    <td>${sampleCell}</td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-edit disabled">Edit</button>
                            <button class="btn btn-sm btn-delete disabled">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        });

    } catch (err) {
        console.error("❌ Search error:", err);
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error fetching results.</td></tr>';
    }
});
</script>

<script>
document.getElementById("searchInput").addEventListener("input", async function () {
    const query = this.value.trim();

    const tbody = document.querySelector("table tbody");

    if (query === '') {
        location.reload(); // Reload full table if search is cleared
        return;
    }

    try {
        const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();

        tbody.innerHTML = ''; // Clear table before inserting new data

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No results found.</td></tr>';
            return;
        }

        data.forEach(f => {
            let sampleCell = '';
            const ext = f.sample_file ? f.sample_file.split('.').pop().toLowerCase() : '';
            const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const icons = {
                pdf: '📄', doc: '📝', docx: '📝',
                txt: '📃', zip: '🗜️', rar: '🗜️'
            };

            if (!f.sample_file) {
                sampleCell = `<span class="text-muted">N/A</span>`;
            } else if (imageTypes.includes(ext)) {
                sampleCell = `<a href="${f.sample_file}" target="_blank">
                                <img src="${f.sample_file}" alt="Preview" style="max-height: 50px; border: 1px solid #ccc; border-radius: 5px;">
                              </a>`;
            } else {
                const icon = icons[ext] || '📁';
                const filename = f.sample_file.split('/').pop();
                const truncated = filename.length > 20 ? filename.slice(0, 17) + '...' : filename;
                sampleCell = `${icon} <a href="${f.sample_file}" target="_blank" title="${filename}">${truncated}</a>`;
            }

            tbody.innerHTML += `
                <tr>
                    <td>${f.created_at}</td>
                    <td>${f.system_name}</td>
                    <td>${f.module}</td>
                    <td>${f.description}</td>
                    <td>${f.client}</td>
                    <td>${sampleCell}</td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-edit disabled">Edit</button>
                            <button class="btn btn-sm btn-delete disabled">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        });

    } catch (err) {
        console.error("❌ Search error:", err);
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error fetching results.</td></tr>';
    }
});
</script>

<script>
//Function to validate the form before saving changes in the edit modal
function handleSaveEdit(id) {
    const isRemoved = document.getElementById('delete_file_' + id)?.value === "1"; // Check if file was marked for removal
    const fileInput = document.querySelector(`#editModal${id} input[type="file"]`); // Get the file input element

    //If the file was removed and no new file has been selected, show an error
    if (isRemoved && !fileInput.value) {
        showToast("⚠️ Please upload a new file before saving.", "error");
        return false; // Prevent form submission
    }
    return true; // Allow form submission
}
</script>

<?php if (isset($_SESSION['success'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast("<?= $_SESSION['success'] ?>", 'success');
        });
    </script>
    <?php unset($_SESSION['success']); // Clear the session message after displaying ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast("<?= $_SESSION['error'] ?>", 'error');
        });
    </script>
    <?php unset($_SESSION['error']); // Clear the session message after displaying ?>
<?php endif; ?>

</body>
</html>
