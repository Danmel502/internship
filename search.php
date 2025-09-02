<?php
require_once 'config.php';
require_once 'controllers/FeatureController.php';
header('Content-Type: application/json');

use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

// Set error reporting to log errors instead of displaying them
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get search query from GET parameter
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Return empty array if query is empty
if ($search === '') {
    echo json_encode([]);
    exit;
}

// Validate search query length (prevent very long queries)
if (strlen($search) > 100) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Search query too long',
        'message' => 'Please shorten your search query to less than 100 characters.'
    ]);
    exit;
}

// Validate search query for potentially malicious content
if (preg_match('/[<>"\']/', $search)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid characters in search',
        'message' => 'Search query contains invalid characters.'
    ]);
    exit;
}

try {
    // Add connection check before performing search
    if (!isset($db)) {
        throw new Exception('Database connection not available');
    }
    
    // Use the enhanced search from FeatureController instead of duplicating logic
    $results = FeatureController::searchFeatures($search, 100, 0);
    
    // Check if results is null or false
    if ($results === null || $results === false) {
        throw new Exception('Failed to retrieve search results');
    }
    
    // Format results for API response
    $formattedResults = [];
    
    foreach ($results as $doc) {
        try {
            // Ensure all fields exist before accessing
            $result = [
                '_id'         => (string) ($doc['_id'] ?? ''),
                'system_name' => $doc['system_name'] ?? '',
                'module'      => $doc['module'] ?? '',
                'feature'     => $doc['feature'] ?? '',
                'description' => $doc['description'] ?? '',
                'client'      => $doc['client'] ?? '',
                'source'      => $doc['source'] ?? '',
                'sample_file' => $doc['sample_file'] ?? '',
                'created_at'  => 'N/A'
            ];
            
            // Handle date conversion more safely
            if (isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime) {
                try {
                    $dateTime = $doc['created_at']->toDateTime();
                    $dateTime->setTimezone(new DateTimeZone('Asia/Manila'));
                    $result['created_at'] = $dateTime->format('Y-m-d | H:i');
                } catch (Exception $dateError) {
                    // Keep 'N/A' if date conversion fails
                    error_log("Date conversion error for document " . $result['_id'] . ": " . $dateError->getMessage());
                }
            }
            
            $formattedResults[] = $result;
            
        } catch (Exception $docError) {
            error_log("Error processing document: " . $docError->getMessage());
            // Continue processing other documents
            continue;
        }
    }
    
    // Return results with metadata
    echo json_encode([
        'success' => true,
        'data' => $formattedResults,
        'count' => count($formattedResults),
        'query' => $search,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    error_log("MongoDB Connection Timeout: " . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'error' => 'Database connection timeout',
        'message' => 'The search service is temporarily unavailable. Please try again in a moment.',
        'retry' => true
    ]);
    
} catch (MongoDB\Driver\Exception\Exception $e) {
    error_log("MongoDB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => 'There was an issue with the database. Please try again later.',
        'retry' => true
    ]);
    
} catch (Exception $e) {
    error_log("Search Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Search failed',
        'message' => 'An unexpected error occurred while searching. Please try again.',
        'details' => $e->getMessage(),
        'retry' => true
    ]);
}
?>