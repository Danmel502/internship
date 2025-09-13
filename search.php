<?php
// Prevent any output before HTML
ob_start();

require_once 'config.php';
require_once 'controllers/FeatureController.php';

// Clean any previous output
ob_clean();

// Set headers for HTML response
header('Content-Type: text/html');
header('Cache-Control: no-cache, must-revalidate');

// Set error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

// Helper functions - DECLARE THESE ONCE OUTSIDE THE LOOP
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

function truncateFilename($filename, $maxLength = 20) {
    return strlen($filename) > $maxLength ? substr($filename, 0, $maxLength - 3) . '...' : $filename;
}

function safeDisplayText($text) {
    if (empty($text)) return '';
    $decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
}

// Get search query from GET parameter
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Return empty if query is empty
if ($search === '') {
    echo '';
    exit;
}

// Validate search query length
if (strlen($search) > 100) {
    http_response_code(400);
    echo '<tr><td colspan="10" class="text-center text-danger">Search query too long</td></tr>';
    exit;
}

// Validate search query for malicious content
if (preg_match('/[<>"\']/', $search)) {
    http_response_code(400);
    echo '<tr><td colspan="10" class="text-center text-danger">Invalid characters in search</td></tr>';
    exit;
}

try {
    // Add connection check
    if (!isset($db)) {
        throw new Exception('Database connection not available');
    }
    
    // Use the enhanced search from FeatureController
    $results = FeatureController::searchFeatures($search, 100, 0);
    
    // Check if results is null or false
    if ($results === null || $results === false) {
        throw new Exception('Failed to retrieve search results');
    }
    
    // Return empty if no results
    if (empty($results)) {
        echo '<tr><td colspan="10" class="text-center text-muted py-4">No results found for "' . htmlspecialchars($search) . '"</td></tr>';
        exit;
    }
    
    // Format results as HTML table rows with modals
    $html = '';
    $modals = '';
    
    foreach ($results as $doc) {
        try {
            // Format the row HTML
            $file = $doc['sample_file'] ?? '';
            $fileCell = '<span class="text-muted fst-italic">N/A</span>';
            
            if (!empty($file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                $filename = basename($file);
                
                if (filter_var($file, FILTER_VALIDATE_URL)) {
                    $parsedUrl = parse_url($file);
                    $domain = $parsedUrl['host'] ?? '';
                    $favicon = "https://www.google.com/s2/favicons?sz=32&domain={$domain}";
                    $fileCell = '
                        <div class="d-flex align-items-center">
                            <img src="' . htmlspecialchars($favicon, ENT_QUOTES) . '"
                                 alt="favicon"
                                 width="16" height="16"
                                 class="me-2 rounded">
                            <a href="' . htmlspecialchars($file, ENT_QUOTES) . '"
                               target="_blank"
                               class="text-decoration-none text-truncate" style="max-width: 120px;">
                                ' . htmlspecialchars($domain, ENT_QUOTES) . '
                            </a>
                        </div>';
                } elseif (in_array($ext, $imageTypes)) {
                    $fileCell = '
                        <a href="' . htmlspecialchars($file, ENT_QUOTES) . '"
                           target="_blank"
                           class="d-inline-block text-decoration-none">
                            <img src="' . htmlspecialchars($file, ENT_QUOTES) . '"
                                 alt="Preview"
                                 class="preview-thumb rounded"
                                 style="width: 40px; height: 40px; object-fit: cover;">
                        </a>';
                } else {
                    $fileCell = '
                        <div class="d-flex align-items-center">
                            <span class="me-2">' . getFileIcon($ext) . '</span>
                            <a href="' . htmlspecialchars($file, ENT_QUOTES) . '"
                               target="_blank"
                               class="text-decoration-none text-truncate" style="max-width: 120px;"
                               title="' . safeDisplayText($filename) . '">
                                ' . safeDisplayText(truncateFilename($filename)) . '
                            </a>
                        </div>';
                }
            }
            
            $html .= '
        <tr class="position-relative" data-feature-id="' . (string)($doc['_id'] ?? '') . '">
            <td class="ps-3">
                <input type="checkbox" class="feature-checkbox form-check-input" 
                       value="' . (string)($doc['_id'] ?? '') . '"
                       data-name="' . safeDisplayText($doc['feature'] ?? 'Unknown') . '">
            </td>
                
                <td>
                    <div class="d-flex flex-column">
                        <span class="fw-medium">' . formatDate($doc['created_at'] ?? null) . '</span>
                        <small class="text-muted">Date</small>
                    </div>
                </td>
                
                <td>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary bg-opacity-10 text-primary me-2">Sys</span>
                        ' . safeDisplayText($doc['system_name'] ?? '') . '
                    </div>
                </td>
                
                <td>
                    <div class="d-flex align-items-center">
                        <span class="text-dark">
                            ' . safeDisplayText($doc['module'] ?? '') . '
                        </span>
                    </div>
                </td>
                
                <td>
                    <span class="badge bg-success text-white">
                        ' . safeDisplayText($doc['feature'] ?? '') . '
                    </span>
                </td>
                
                <td>
                    <div class="text-truncate" style="max-width: 200px;" 
                         data-bs-toggle="tooltip" 
                         data-bs-placement="top" 
                         title="' . safeDisplayText($doc['description'] ?? '') . '">
                        ' . safeDisplayText($doc['description'] ?? '') . '
                    </div>
                </td>
                
                <td>
                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                        ' . safeDisplayText($doc['client'] ?? '') . '
                    </span>
                </td>
                
                <td>
                    <span class="badge bg-warning bg-opacity-15 text-dark">
                        ' . safeDisplayText($doc['source'] ?? '') . '
                    </span>
                </td>
                
                <td>' . $fileCell . '</td>
                
                <td class="pe-4">
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-sm btn-outline-success rounded-pill d-flex align-items-center edit-module-btn"
                            data-id="' . (string)($doc['_id'] ?? '') . '"
                            data-bs-toggle="modal"
                            data-bs-target="#editModal' . (string)($doc['_id'] ?? '') . '"
                            data-feature-id="' . (string)($doc['_id'] ?? '') . '"
                            data-system-name="' . htmlspecialchars($doc['system_name'] ?? '') . '"
                            data-module-name="' . htmlspecialchars($doc['module'] ?? '') . '"
                            title="Edit feature">
                            <i class="fas fa-edit me-1"></i>Edit
                        </button>
                        
                        <button class="btn btn-sm btn-outline-danger rounded-pill d-flex align-items-center"
                                onclick="confirmDelete(\'' . (string)($doc['_id'] ?? '') . '\', \'' . safeDisplayText($doc['feature'] ?? $doc['system_name'] ?? 'Unknown') . '\')"
                                title="Delete feature">
                            <i class="fas fa-trash-alt me-1"></i>Delete
                        </button>
                    </div>
                </td>
            </tr>';
            
            // Generate edit modal for this search result
            $feature = $doc; // Rename variable for compatibility with edit_modal.php
            ob_start();
            if (file_exists('/views/get_edit_modal.php')) {
                include '/views/get_edit_modal.php';
            }
            $modals .= ob_get_clean();
            
        } catch (Exception $docError) {
            error_log("Error processing document: " . $docError->getMessage());
            continue;
        }
    }
    
    // Output table rows followed by modals
    echo $html . $modals;
    
} catch (Exception $e) {
    error_log("Search Error: " . $e->getMessage());
    echo '<tr><td colspan="10" class="text-center text-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
}

// End output buffering
ob_end_flush();
?>