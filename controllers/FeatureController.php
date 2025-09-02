<?php
require_once __DIR__ . '/../config.php';

class FeatureController {
    
    private static $uploadDir = 'uploads/';
    private static $maxFileSize = 10 * 1024 * 1024; // 10MB
    private static $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx'];

    /**
 * Expand search query using synonym dictionary
 */
private static function expandSearchQuery($searchTerm) {
    $synonyms = [
        // Error-related terms
        'error' => ['error', 'incorrect', 'wrong', 'invalid', 'failed', 'issue', 'problem', 'bug', 'fault', 'duplicate'],
        'bug' => ['bug', 'error', 'issue', 'problem', 'fault', 'incorrect'],
        'issue' => ['issue', 'problem', 'error', 'bug', 'trouble', 'fault'],

        // Merge-related terms
        'merge' => ['merge', 'combine', 'integrate', 'unify', 'join', 'consolidate', 'fusion'],

        // Duplicate-related terms
        'duplicate' => ['duplicate', 'copy', 'redundant', 'replicate', 'clone', 'repeat', 'redundancy'],

        // Validation-related terms
        'validation' => ['validation', 'check', 'verify', 'confirmation', 'inspection', 'assessment', 'review', 'verification'],

        // Incorrect XML-related terms
        'incorrect xml' => ['incorrect xml', 'invalid xml', 'malformed xml', 'xml error', 'xml issue', 'wrong xml', 'parsing error'],

        // Food-related terms
        'food' => ['jollibee', 'chicken', 'drink', 'ice cream', 'meal', 'snack', 'beverage', 'recipe'],
        'meal' => ['meal', 'food', 'lunch', 'dinner', 'breakfast', 'snack'],

        // Authentication terms
        'login' => ['login', 'signin', 'authentication', 'access', 'auth', 'sign in'],
        'auth' => ['auth', 'authentication', 'login', 'signin', 'access'],

        // Data terms
        'data' => ['data', 'information', 'record', 'entry', 'content', 'database'],
        'database' => ['database', 'data', 'db', 'storage', 'records'],

        // User interface terms
        'ui' => ['ui', 'interface', 'user interface', 'frontend', 'design'],
        'interface' => ['interface', 'ui', 'user interface', 'frontend'],
    ];

    // --- Normalize search term ---
    $searchTerm = strtolower(trim($searchTerm));

    // Handle plural forms (simple cases)
    if (substr($searchTerm, -3) === 'ies') {
        $searchTerm = substr($searchTerm, 0, -3) . 'y'; // e.g. "companies" -> "company"
    } elseif (substr($searchTerm, -2) === 'es') {
        $searchTerm = substr($searchTerm, 0, -2); // e.g. "merges" -> "merge"
    } elseif (substr($searchTerm, -1) === 's') {
        $searchTerm = substr($searchTerm, 0, -1); // e.g. "foods" -> "food"
    }

    // Return expanded terms if synonym exists, otherwise return original term
    return $synonyms[$searchTerm] ?? [$searchTerm];
}

    /**
     * Update reference data - replaces old value with new value
     */
    private static function updateReferenceData($db, $collectionName, $oldValue, $newValue) {
        if (empty(trim($oldValue)) || empty(trim($newValue))) return false;
        
        $oldValue = trim($oldValue);
        $newValue = trim($newValue);
        
        // Debug logging
        error_log("Updating {$collectionName}: '{$oldValue}' -> '{$newValue}'");
        
        // If values are the same, no update needed
        if ($oldValue === $newValue) {
            error_log("Values are the same, no update needed");
            return true;
        }
        
        $collection = $db->getCollection($collectionName);
        
        try {
            // Check if new value already exists
            $existingNew = $collection->findOne(['name' => $newValue, 'is_active' => true]);
            error_log("New value exists: " . ($existingNew ? 'yes' : 'no'));
            
            if (!$existingNew) {
                // Update the old value to new value
                $result = $collection->updateOne(
                    ['name' => $oldValue, 'is_active' => true],
                    [
                        '$set' => [
                            'name' => $newValue,
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                
                error_log("Update matched count: " . $result->getMatchedCount());
                error_log("Update modified count: " . $result->getModifiedCount());
                
                // If no document was updated (old value doesn't exist), create new one
                if ($result->getMatchedCount() === 0) {
                    error_log("Old value not found, creating new entry");
                    self::ensureReferenceDataWithId($db, $collectionName, $newValue);
                }
            } else {
                // New value already exists, so we need to check if old value is still used elsewhere
                $overallCollection = $db->getCollection('overall');
                $fieldName = rtrim($collectionName, 's'); // Remove 's' to get field name
                
                $stillUsed = $overallCollection->countDocuments([$fieldName => $oldValue]);
                error_log("Old value '{$oldValue}' still used in {$stillUsed} documents");
                
                // If old value is not used elsewhere, deactivate it
                if ($stillUsed <= 1) { // <= 1 because current document still has old value
                    error_log("Deactivating old value '{$oldValue}'");
                    $deactivateResult = $collection->updateOne(
                        ['name' => $oldValue, 'is_active' => true],
                        [
                            '$set' => [
                                'is_active' => false,
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ]
                    );
                    error_log("Deactivate matched count: " . $deactivateResult->getMatchedCount());
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error updating reference data in {$collectionName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable a feature for a specific client/source/system combination
     */
    public static function enableFeature($featureName, $clientId, $systemId, $sourceId, $additionalData = []) {
        try {
            // Validate required parameters
            if (empty($featureName) || empty($clientId) || empty($systemId) || empty($sourceId)) {
                return ['success' => false, 'error' => 'Missing required parameters: feature, client_id, system_id, source_id'];
            }

            $db = Database::getInstance();
            $overallCollection = $db->getCollection('overall');

            // Check if this combination already exists
            $existingRecord = $overallCollection->findOne([
                'feature' => $featureName,
                'client_id' => $clientId,
                'system_id' => $systemId,
                'source_id' => $sourceId
            ]);

            if ($existingRecord) {
                return ['success' => false, 'error' => 'Feature already enabled for this combination'];
            }

            // Get the actual names from reference collections for the overall record
            $systemName = self::getSystemNameById($systemId);
            $clientName = self::getClientNameById($clientId);
            $sourceName = self::getSourceNameById($sourceId);

            // Prepare the document
            $document = [
                'feature' => self::sanitizeString($featureName),
                'client_id' => self::sanitizeString($clientId),
                'system_id' => $systemId,
                'source_id' => self::sanitizeString($sourceId),
                
                // Also store the names for easier querying (your existing pattern)
                'system_name' => $systemName ?: 'Unknown System',
                'client' => $clientName ?: 'Unknown Client',
                'source' => $sourceName ?: 'Unknown Source',
                'module' => $additionalData['module'] ?? 'Feature Management',
                'description' => $additionalData['description'] ?? "Feature '{$featureName}' enabled for {$clientName} on {$sourceName}",
                
                'enabled' => true,
                'enabled_at' => new MongoDB\BSON\UTCDateTime(),
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];

            // Add any additional configuration data
            if (!empty($additionalData['config'])) {
                $document['config'] = $additionalData['config'];
            }

            // Insert the record
            $result = $overallCollection->insertOne($document);

            if (!$result->getInsertedId()) {
                throw new Exception('Failed to enable feature');
            }

            // Ensure reference data exists in separate collections
            $database = $db->getDatabase();
            self::ensureReferenceDataWithId($database, 'features', $featureName);
            self::ensureReferenceDataWithId($database, 'clients', $clientName ?: $clientId);
            self::ensureReferenceDataWithId($database, 'sources', $sourceName ?: $sourceId);
            if ($systemName) {
                self::ensureReferenceDataWithId($database, 'system_names', $systemName);
            }

            return [
                'success' => true, 
                'id' => (string)$result->getInsertedId(),
                'message' => "Feature '{$featureName}' enabled successfully"
            ];

        } catch (Exception $e) {
            error_log("Error enabling feature: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Disable a feature for a specific combination
     */
    public static function disableFeature($featureName, $clientId, $systemId, $sourceId) {
        try {
            $db = Database::getInstance();
            $overallCollection = $db->getCollection('overall');

            $result = $overallCollection->updateOne(
                [
                    'feature' => $featureName,
                    'client_id' => $clientId,
                    'system_id' => $systemId,
                    'source_id' => $sourceId
                ],
                [
                    '$set' => [
                        'enabled' => false,
                        'disabled_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );

            if ($result->getMatchedCount() === 0) {
                return ['success' => false, 'error' => 'Feature configuration not found'];
            }

            return ['success' => true, 'message' => "Feature '{$featureName}' disabled successfully"];

        } catch (Exception $e) {
            error_log("Error disabling feature: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if a feature is enabled for a specific combination
     */
    public static function isFeatureEnabled($featureName, $clientId, $systemId, $sourceId) {
        try {
            $db = Database::getInstance();
            $overallCollection = $db->getCollection('overall');

            $record = $overallCollection->findOne([
                'feature' => $featureName,
                'client_id' => $clientId,
                'system_id' => $systemId,
                'source_id' => $sourceId,
                'enabled' => true
            ]);

            return $record !== null;

        } catch (Exception $e) {
            error_log("Error checking feature status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all enabled features for a client/system/source combination
     */
    public static function getEnabledFeatures($clientId, $systemId, $sourceId) {
        try {
            $db = Database::getInstance();
            $overallCollection = $db->getCollection('overall');

            $cursor = $overallCollection->find([
                'client_id' => $clientId,
                'system_id' => $systemId,
                'source_id' => $sourceId,
                'enabled' => true
            ]);

            return $cursor->toArray();

        } catch (Exception $e) {
            error_log("Error getting enabled features: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Bulk enable features
     */
    public static function bulkEnableFeatures($features, $clientId, $systemId, $sourceId) {
        $results = ['success' => [], 'errors' => []];

        foreach ($features as $featureName) {
            $result = self::enableFeature($featureName, $clientId, $systemId, $sourceId);
            if ($result['success']) {
                $results['success'][] = $featureName;
            } else {
                $results['errors'][$featureName] = $result['error'];
            }
        }

        return $results;
    }

    /**
 * Get cascading dropdown data based on filters
 * Add this method to your FeatureController.php class
 */
public static function getCascadingData($type, $filters = []) {
    try {
        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            return ['success' => false, 'error' => 'Database not available'];
        }

        $query = [];
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $query[$field] = $value;
            }
        }

        // Ensure the target field is not empty
        $query[$type] = ['$ne' => null, '$ne' => ''];

        // Get distinct values
        $values = $collection->distinct($type, $query);
        
        // Filter out empty values and sort
        $filteredValues = array_filter($values, function($value) {
            return !empty(trim($value));
        });
        
        sort($filteredValues);

        return [
            'success' => true,
            'data' => array_values($filteredValues)
        ];

    } catch (Exception $e) {
        error_log("Error getting cascading data: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

    /**
     * Bulk delete features - Fixed version aligned with delete.php
     */
    public static function bulkDeleteFeatures($ids) {
        try {
            if (empty($ids) || !is_array($ids)) {
                return ['success' => false, 'error' => 'No valid IDs provided'];
            }

            $collection = Database::getInstance()->getCollection('overall');
            if (!$collection) {
                throw new Exception('Database collection not available');
            }

            $objectIds = [];
            $validIds = [];
            
            // Validate all IDs first
            foreach ($ids as $id) {
                if (self::isValidObjectId($id)) {
                    try {
                        $objectIds[] = new MongoDB\BSON\ObjectId($id);
                        $validIds[] = $id;
                    } catch (Exception $e) {
                        error_log("Invalid ObjectId: $id - " . $e->getMessage());
                        continue;
                    }
                }
            }

            if (empty($objectIds)) {
                return ['success' => false, 'error' => 'No valid IDs provided'];
            }

            // First, get features to delete (for file cleanup and reference tracking)
            $features = $collection->find(['_id' => ['$in' => $objectIds]]);
            $featuresToDelete = $features->toArray(); // Convert to array to avoid cursor issues
            $filesToDelete = [];
            
            foreach ($featuresToDelete as $feature) {
                if (!empty($feature['sample_file'])) {
                    $filePath = $feature['sample_file'];
                    // Only delete local files, not URLs
                    if (!filter_var($filePath, FILTER_VALIDATE_URL) && file_exists($filePath)) {
                        $filesToDelete[] = $filePath;
                    }
                }
            }

            // Delete the features from database
            $result = $collection->deleteMany(['_id' => ['$in' => $objectIds]]);
            
            if ($result->getDeletedCount() === 0) {
                return ['success' => false, 'error' => 'No features were deleted - they may not exist'];
            }

            // Delete associated files after successful database deletion
            $filesDeleted = 0;
            foreach ($filesToDelete as $file) {
                if (file_exists($file)) {
                    if (unlink($file)) {
                        $filesDeleted++;
                    } else {
                        error_log("Failed to delete file: $file");
                    }
                }
            }

            // Clean up reference collections (system_names, clients, modules, features, sources)
            $cleanupCount = 0;
            $db = Database::getInstance()->getDatabase();
            if ($db) {
                // Get all unique values that were deleted
                $deletedValues = [];
                
                foreach ($featuresToDelete as $feature) {
                    $fields = ['system_name', 'module', 'feature', 'client', 'source'];
                    foreach ($fields as $field) {
                        $value = $feature[$field] ?? '';
                        if (!empty($value)) {
                            if (!isset($deletedValues[$field])) {
                                $deletedValues[$field] = [];
                            }
                            $deletedValues[$field][] = $value;
                        }
                    }
                }

                // Clean up each reference collection
                foreach ($deletedValues as $field => $values) {
                    $uniqueValues = array_unique($values);
                    $collectionName = $field . 's'; // pluralize (system_names, clients, etc.)
                    
                    foreach ($uniqueValues as $value) {
                        // Check if this value is still used in other documents
                        $stillUsed = $collection->countDocuments([$field => $value]);
                        
                        if ($stillUsed === 0) {
                            // Value is no longer used, remove from reference collection
                            $refCollection = $db->getCollection($collectionName);
                            $deleteResult = $refCollection->deleteOne(['name' => $value, 'is_active' => true]);
                            if ($deleteResult->getDeletedCount() > 0) {
                                $cleanupCount++;
                                error_log("Cleaned up unused reference: {$collectionName} - {$value}");
                            }
                        }
                    }
                }
            }

            $message = "Successfully deleted {$result->getDeletedCount()} feature(s)";
            if ($filesDeleted > 0) {
                $message .= " and {$filesDeleted} associated file(s)";
            }
            if ($cleanupCount > 0) {
                $message .= " and cleaned {$cleanupCount} unused reference(s)";
            }

            return [
                'success' => true, 
                'deleted_count' => $result->getDeletedCount(),
                'files_deleted' => $filesDeleted,
                'references_cleaned' => $cleanupCount,
                'message' => $message
            ];

        } catch (MongoDB\Exception\Exception $e) {
            error_log("MongoDB error in bulk delete: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        } catch (Exception $e) {
            error_log("Error bulk deleting features: " . $e->getMessage());
            return ['success' => false, 'error' => 'Server error: ' . $e->getMessage()];
        }
    }

    /**
     * Helper method to get system name by ID
     */
    private static function getSystemNameById($systemId) {
        try {
            $db = Database::getInstance();
            $systemCollection = $db->getCollection('system_names');
            
            // Look for system by ID
            $system = $systemCollection->findOne([
                'id' => $systemId,
                'is_active' => true
            ]);

            return $system ? $system['name'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Helper method to get client name by ID
     */
    private static function getClientNameById($clientId) {
    try {
        $db = Database::getInstance();
        $clientCollection = $db->getCollection('clients');
        
        // Extract the numeric ID from client_id (e.g., "abs_1" -> 1)
        if (preg_match('/[a-z0-9]+_(\d+)/', $clientId, $matches)) {
            $numericId = (int)$matches[1];
            
            $client = $clientCollection->findOne([
                'id' => $numericId,
                'is_active' => true
            ]);

            return $client ? $client['name'] : null;
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

    /**
     * Helper method to get source name by ID
     */
    private static function getSourceNameById($sourceId) {
    try {
        $db = Database::getInstance();
        $sourceCollection = $db->getCollection('sources');
        
        // Extract the numeric ID from source_id (e.g., "media_2" -> 2)
        if (preg_match('/[a-z0-9]+_(\d+)/', $sourceId, $matches)) {
            $numericId = (int)$matches[1];
            
            $source = $sourceCollection->findOne([
                'id' => $numericId,
                'is_active' => true
            ]);

            return $source ? $source['name'] : null;
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

    // ====== ALL YOUR ORIGINAL METHODS BELOW ======

    /**
 * Convert stored IDs back to display names for frontend
 */

private static function convertToDisplayFormat($features) {
    if (empty($features)) return $features;
    
    $db = Database::getInstance();
    if (!$db) return $features;
    
    // Get all reference collections
    $systemNamesCollection = $db->getCollection('system_names');
    $modulesCollection = $db->getCollection('modules');
    $featuresCollection = $db->getCollection('features');
    $clientsCollection = $db->getCollection('clients');
    $sourcesCollection = $db->getCollection('sources');
    
    // Cache for lookups to improve performance
    $systemNamesCache = [];
    $modulesCache = [];
    $featuresCache = [];
    $clientsCache = [];
    $sourcesCache = [];
    
    foreach ($features as &$feature) {
        // Convert system name ID (facebook_1) back to display name (Facebook)
        if (!empty($feature['system_name']) && preg_match('/[a-z0-9]+_(\d+)/', $feature['system_name'], $matches)) {
            $numericId = (int)$matches[1];
            
            if (!isset($systemNamesCache[$numericId])) {
                $systemName = $systemNamesCollection->findOne(['id' => $numericId, 'is_active' => true]);
                $systemNamesCache[$numericId] = $systemName ? $systemName['name'] : $feature['system_name'];
            }
            $feature['system_name'] = $systemNamesCache[$numericId];
        }
        
        // Convert module ID (seven_1) back to display name (Seven)
        if (!empty($feature['module']) && preg_match('/[a-z0-9]+_(\d+)/', $feature['module'], $matches)) {
            $numericId = (int)$matches[1];
            
            if (!isset($modulesCache[$numericId])) {
                $module = $modulesCollection->findOne(['id' => $numericId, 'is_active' => true]);
                $modulesCache[$numericId] = $module ? $module['name'] : $feature['module'];
            }
            $feature['module'] = $modulesCache[$numericId];
        }
        
        // Convert feature ID (seen_1) back to display name (Seen)
        if (!empty($feature['feature']) && preg_match('/[a-z0-9]+_(\d+)/', $feature['feature'], $matches)) {
            $numericId = (int)$matches[1];
            
            if (!isset($featuresCache[$numericId])) {
                $featureDoc = $featuresCollection->findOne(['id' => $numericId, 'is_active' => true]);
                $featuresCache[$numericId] = $featureDoc ? $featureDoc['name'] : $feature['feature'];
            }
            $feature['feature'] = $featuresCache[$numericId];
        }
        
        // Convert client ID (abs_1) back to display name (ABS CBN)
        if (!empty($feature['client']) && preg_match('/[a-z0-9]+_(\d+)/', $feature['client'], $matches)) {
            $numericId = (int)$matches[1];
            
            if (!isset($clientsCache[$numericId])) {
                $client = $clientsCollection->findOne(['id' => $numericId, 'is_active' => true]);
                $clientsCache[$numericId] = $client ? $client['name'] : $feature['client'];
            }
            $feature['client'] = $clientsCache[$numericId];
        }
        
        // Convert source ID (media_2) back to display name (Media)
        if (!empty($feature['source']) && preg_match('/[a-z0-9]+_(\d+)/', $feature['source'], $matches)) {
            $numericId = (int)$matches[1];
            
            if (!isset($sourcesCache[$numericId])) {
                $source = $sourcesCollection->findOne(['id' => $numericId, 'is_active' => true]);
                $sourcesCache[$numericId] = $source ? $source['name'] : $feature['source'];
            }
            $feature['source'] = $sourcesCache[$numericId];
        }
    }
    
    return $features;
}
/**
 * Get paginated features with optional filtering - UPDATED
 */
public static function getFeatures($limit = 0, $skip = 0, $filters = []) {
    try {
        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            throw new Exception('Database collection not available');
        }

        $query = [];
        $allowedFilters = ['system_name', 'module', 'client', 'source', 'feature', 'enabled'];
        foreach ($allowedFilters as $field) {
            if (!empty($filters[$field])) {
                if ($field === 'enabled') {
                    $query[$field] = (bool)$filters[$field];
                } else {
                    $query[$field] = $filters[$field];
                }
            }
        }

        $options = ['sort' => ['created_at' => -1]];
        if ($limit > 0) {
            $options['limit'] = (int)$limit;
            $options['skip'] = (int)$skip;
        }

        $cursor = $collection->find($query, $options);
        $features = $cursor->toArray();
        
        // Convert IDs back to display names
        return self::convertToDisplayFormat($features);
    } catch (Exception $e) {
        error_log("Error getting features: " . $e->getMessage());
        return [];
    }
}

/**
 * Get feature by ID - UPDATED
 */
public static function getFeatureById($id) {
    try {
        if (!self::isValidObjectId($id)) {
            return null;
        }

        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            return null;
        }

        $objectId = new MongoDB\BSON\ObjectId($id);
        $feature = $collection->findOne(['_id' => $objectId]);
        
        if ($feature) {
            $features = self::convertToDisplayFormat([$feature]);
            return $features[0] ?? null;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting feature by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Enhanced search with synonym expansion
 */
public static function searchFeatures($keyword, $limit = 0, $skip = 0) {
    try {
        if (empty($keyword)) {
            return self::getFeatures($limit, $skip);
        }

        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            return [];
        }

        // First try enhanced search with synonyms
        $enhancedResults = self::searchWithSynonyms($keyword, $limit, $skip);
        
        // If enhanced search returns results, use them
        if (!empty($enhancedResults)) {
            return $enhancedResults;
        }
        
        // Fallback to original regex search
        $sanitizedKeyword = self::sanitizeString($keyword);
        $regex = new MongoDB\BSON\Regex($sanitizedKeyword, 'i');
        
        $query = [
            '$or' => [
                ['system_name' => $regex],
                ['module' => $regex],
                ['feature' => $regex],
                ['description' => $regex],
                ['client' => $regex],
                ['source' => $regex]
            ]
        ];

        $options = ['sort' => ['created_at' => -1]];
        if ($limit > 0) {
            $options['limit'] = (int)$limit;
            $options['skip'] = (int)$skip;
        }

        $cursor = $collection->find($query, $options);
        $features = $cursor->toArray();
        
        return self::convertToDisplayFormat($features);
    } catch (Exception $e) {
        error_log("Error in enhanced search: " . $e->getMessage());
        return [];
    }
}

/**
 * Search with synonym expansion
 */
private static function searchWithSynonyms($keyword, $limit = 0, $skip = 0) {
    try {
        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            return [];
        }

        $expandedTerms = self::expandSearchQuery($keyword);
        $searchConditions = [];
        
        foreach ($expandedTerms as $term) {
            $regex = new MongoDB\BSON\Regex($term, 'i');
            $searchConditions[] = [
                '$or' => [
                    ['system_name' => $regex],
                    ['module' => $regex],
                    ['feature' => $regex],
                    ['description' => $regex],
                    ['client' => $regex],
                    ['source' => $regex]
                ]
            ];
        }
        
        $query = ['$or' => $searchConditions];

        $options = ['sort' => ['created_at' => -1]];
        if ($limit > 0) {
            $options['limit'] = (int)$limit;
            $options['skip'] = (int)$skip;
        }

        $cursor = $collection->find($query, $options);
        $features = $cursor->toArray();
        
        return self::convertToDisplayFormat($features);
    } catch (Exception $e) {
        error_log("Error in synonym search: " . $e->getMessage());
        return [];
    }
}

// Add this method to your FeatureController class or run separately
public static function createSearchIndexes() {
    try {
        $collection = Database::getInstance()->getCollection('overall');
        
        // Create text index for search fields
        $collection->createIndex([
            'system_name' => 'text',
            'module' => 'text',
            'feature' => 'text',
            'description' => 'text',
            'client' => 'text',
            'source' => 'text'
        ], ['name' => 'search_index']);
        
        // Create individual indexes for exact matches and sorting
        $collection->createIndex(['system_name' => 1]);
        $collection->createIndex(['client' => 1]);
        $collection->createIndex(['source' => 1]);
        $collection->createIndex(['created_at' => -1]);
        
        return ['success' => true, 'message' => 'Indexes created successfully'];
        
    } catch (Exception $e) {
        error_log("Error creating indexes: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Alternative search method using text index (more efficient)
public static function searchFeaturesWithTextIndex($keyword, $limit = 0, $skip = 0) {
    try {
        if (empty($keyword)) {
            return self::getFeatures($limit, $skip);
        }

        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            return [];
        }

        // Use MongoDB's text search instead of regex for better performance
        $query = ['$text' => ['$search' => $keyword]];

        $options = [
            'sort' => ['score' => ['$meta' => 'textScore'], 'created_at' => -1]
        ];
        
        if ($limit > 0) {
            $options['limit'] = (int)$limit;
            $options['skip'] = (int)$skip;
        }

        $cursor = $collection->find($query, $options);
        $features = $cursor->toArray();
        
        return self::convertToDisplayFormat($features);
        
    } catch (Exception $e) {
        error_log("Error in text search: " . $e->getMessage());
        // Fallback to regex search
        return self::searchFeatures($keyword, $limit, $skip);
    }
}

/**
 * Fixed addFeature method with proper sample_file handling
 */
public static function addFeature($postData, $fileData = []) {
    try {
        $requiredFields = ['system_name', 'module', 'feature', 'description', 'client', 'source'];
        $errors = [];

        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                $errors[$field] = "Field '" . ucfirst(str_replace('_', ' ', $field)) . "' is required";
            }
        }

        $fileUrl = isset($postData['file_url']) ? trim($postData['file_url']) : '';
        $fileUploaded = isset($fileData['sample_file']) && $fileData['sample_file']['error'] !== UPLOAD_ERR_NO_FILE;

        if (empty($fileUrl) && !$fileUploaded) {
            $errors['sample_file'] = "Sample file or URL is required";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $overallCollection = Database::getInstance()->getCollection('overall');
        if (!$overallCollection) {
            throw new Exception('Database collection not available');
        }

        

        // Store original names for display
$originalSystemName = self::sanitizeString($postData['system_name']);
$originalModule = self::sanitizeString($postData['module']);
$originalFeature = self::sanitizeString($postData['feature']);
$originalClient = self::sanitizeString($postData['client']);
$originalSource = self::sanitizeString($postData['source']);

        // Handle file upload or URL first
        $sampleFile = null;
        if ($fileUploaded) {
            $uploadResult = self::handleFileUpload($fileData, 'sample_file');
            if ($uploadResult['success']) {
                $sampleFile = $uploadResult['path'];
            } else {
                return ['success' => false, 'errors' => ['sample_file' => $uploadResult['error']]];
            }
        } elseif (!empty($fileUrl)) {
            $validatedUrl = filter_var($fileUrl, FILTER_VALIDATE_URL);
            if ($validatedUrl) {
                $sampleFile = $validatedUrl;
            } else {
                return ['success' => false, 'errors' => ['sample_file' => 'Invalid URL provided']];
            }
        }

        // Generate IDs and prepare data
        $db = Database::getInstance()->getDatabase();
        $data = [];
        
        if ($db) {
            $systemNameId = self::ensureReferenceDataWithId($db, 'system_names', $originalSystemName);
$moduleId = self::ensureReferenceDataWithId($db, 'modules', $originalModule);
$featureId = self::ensureReferenceDataWithId($db, 'features', $originalFeature);
$clientId = self::ensureReferenceDataWithId($db, 'clients', $originalClient);
$sourceId = self::ensureReferenceDataWithId($db, 'sources', $originalSource);
            
            // Build data array with proper order
            $data = [
    'system_name' => $originalSystemName,
    'system_name_id' => $systemNameId,
    'module' => $originalModule,
    'module_id' => $moduleId,
    'feature' => $originalFeature,
    'feature_id' => $featureId,
    'description' => self::sanitizeString($postData['description']),
    'client' => $originalClient,
    'client_id' => $clientId,
    'source' => $originalSource,
    'source_id' => $sourceId
];
            
            // Add sample_file if exists
            if ($sampleFile) {
                $data['sample_file'] = $sampleFile;
            }
            
            // Add timestamps
            $data['created_at'] = new MongoDB\BSON\UTCDateTime();
            $data['updated_at'] = new MongoDB\BSON\UTCDateTime();
            
            // Ensure other reference data
            self::ensureReferenceDataWithId($db, 'features', $data['feature']);
            self::ensureReferenceDataWithId($db, 'modules', $data['module']);
        }

        $result = $overallCollection->insertOne($data);
        
        if (!$result->getInsertedId()) {
            throw new Exception('Failed to insert feature');
        }

        return ['success' => true, 'id' => (string)$result->getInsertedId()];
    } catch (Exception $e) {
        error_log("Error adding feature: " . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
    }
}


/**
 * Update feature - handles both prefixed and non-prefixed field names
 */
/**
 * Update feature - handles both prefixed and non-prefixed field names
 */
public static function updateFeature($postData, $fileData = []) {
    try {
        $isFormUpdate = isset($postData['edit_id']);
        
        if ($isFormUpdate) {
            $requiredFields = ['edit_id', 'edit_system_name', 'edit_module', 'edit_feature', 'edit_description', 'edit_client', 'edit_source'];
            $id = $postData['edit_id'];
            $fieldMapping = [
                'system_name' => 'edit_system_name',
                'module' => 'edit_module',
                'feature' => 'edit_feature',
                'description' => 'edit_description',
                'client' => 'edit_client',
                'source' => 'edit_source'
            ];

            
        } else {
            $requiredFields = ['system_name', 'module', 'feature', 'description', 'client', 'source'];
            $id = $postData['id'] ?? null;
            $fieldMapping = [
                'system_name' => 'system_name',
                'module' => 'module',
                'feature' => 'feature',
                'description' => 'description',
                'client' => 'client',
                'source' => 'source'
            ];
        }

        $errors = [];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                $fieldName = str_replace('edit_', '', $field);
                $errors[$fieldName] = "Field '" . ucfirst(str_replace('_', ' ', $fieldName)) . "' is required";
            }
        }

        if (!$id) {
            $errors['id'] = 'Feature ID is required';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            throw new Exception('Database collection not available');
        }

        if (!self::isValidObjectId($id)) {
            throw new Exception('Invalid feature ID');
        }

        $objectId = new MongoDB\BSON\ObjectId($id);
        $existingFeature = $collection->findOne(['_id' => $objectId]);
        if (!$existingFeature) {
            throw new Exception('Feature not found');
        }

        // Store old values for reference data update
        $oldValues = [
            'system_name' => $existingFeature['system_name'] ?? '',
            'module' => $existingFeature['module'] ?? '',
            'feature' => $existingFeature['feature'] ?? '',
            'client' => $existingFeature['client'] ?? '',
            'source' => $existingFeature['source'] ?? ''
        ];

        // Store original names for display
        $originalSystemName = self::sanitizeString($postData[$fieldMapping['system_name']]);
        $originalModule = self::sanitizeString($postData[$fieldMapping['module']]);
        $originalFeature = self::sanitizeString($postData[$fieldMapping['feature']]);
        $originalClient = self::sanitizeString($postData[$fieldMapping['client']]);
        $originalSource = self::sanitizeString($postData[$fieldMapping['source']]);

        // Generate IDs for new values
        $db = Database::getInstance()->getDatabase();
        $systemNameId = null;
        $moduleId = null;
        $featureId = null;
        $clientId = null;
        $sourceId = null;
        
        if ($db) {
            $systemNameId = self::ensureReferenceDataWithId($db, 'system_names', $originalSystemName);
            $moduleId = self::ensureReferenceDataWithId($db, 'modules', $originalModule);
            $featureId = self::ensureReferenceDataWithId($db, 'features', $originalFeature);
            $clientId = self::ensureReferenceDataWithId($db, 'clients', $originalClient);
            $sourceId = self::ensureReferenceDataWithId($db, 'sources', $originalSource);
        }

        $updateData = [
            'system_name' => $originalSystemName,
            'system_name_id' => $systemNameId,
            'module' => $originalModule,
            'module_id' => $moduleId,
            'feature' => $originalFeature,
            'feature_id' => $featureId,
            'description' => self::sanitizeString($postData[$fieldMapping['description']]),
            'client' => $originalClient,
            'client_id' => $clientId,
            'source' => $originalSource,
            'source_id' => $sourceId,
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        if ($isFormUpdate) {
            $deleteFile = isset($postData['delete_file']) && $postData['delete_file'] === "1";
            
            // Get file URL from form
            $fileUrl = isset($postData['edit_file_url']) ? trim($postData['edit_file_url']) : '';
            $fileUploaded = isset($fileData['edit_sample_file']) && $fileData['edit_sample_file']['error'] !== UPLOAD_ERR_NO_FILE;
            
            if ($deleteFile) {
                // User wants to delete the current file
                if (!empty($existingFeature['sample_file']) && file_exists($existingFeature['sample_file'])) {
                    unlink($existingFeature['sample_file']);
                }
                $updateData['sample_file'] = null;
            } elseif ($fileUploaded) {
                // User uploaded a new file
                $uploadResult = self::handleFileUpload($fileData, 'edit_sample_file');
                if ($uploadResult['success']) {
                    // Delete old file if it exists and is a local file
                    if (!empty($existingFeature['sample_file']) && 
                        !filter_var($existingFeature['sample_file'], FILTER_VALIDATE_URL) && 
                        file_exists($existingFeature['sample_file'])) {
                        unlink($existingFeature['sample_file']);
                    }
                    $updateData['sample_file'] = $uploadResult['path'];
                } elseif ($uploadResult['error']) {
                    return ['success' => false, 'errors' => ['sample_file' => $uploadResult['error']]];
                }
            } elseif (!empty($fileUrl)) {
                // User provided a URL
                $validatedUrl = filter_var($fileUrl, FILTER_VALIDATE_URL);
                if ($validatedUrl) {
                    // Delete old file if it exists and is a local file (not URL)
                    if (!empty($existingFeature['sample_file']) && 
                        !filter_var($existingFeature['sample_file'], FILTER_VALIDATE_URL) && 
                        file_exists($existingFeature['sample_file'])) {
                        unlink($existingFeature['sample_file']);
                    }
                    $updateData['sample_file'] = $validatedUrl;
                } else {
                    return ['success' => false, 'errors' => ['sample_file' => 'Invalid URL provided']];
                }
            }
            // If none of the above conditions are met, keep the existing file
        }

        // Update the main document first
        $result = $collection->updateOne(['_id' => $objectId], ['$set' => $updateData]);
        
        if ($result->getModifiedCount() === 0 && $result->getMatchedCount() === 0) {
            throw new Exception('Feature not found or no changes made');
        }

        // Now handle reference data updates
        if ($db) {
            // Update reference collections with old -> new value mapping
            error_log("Starting reference data updates...");
            self::updateReferenceData($db, 'system_names', $oldValues['system_name'], $updateData['system_name']);
            self::updateReferenceData($db, 'modules', $oldValues['module'], $updateData['module']);
            self::updateReferenceData($db, 'features', $oldValues['feature'], $updateData['feature']);
            self::updateReferenceData($db, 'clients', $oldValues['client'], $updateData['client']);
            self::updateReferenceData($db, 'sources', $oldValues['source'], $updateData['source']);
            error_log("Reference data updates completed");
        }

        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error updating feature: " . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
    }
}

/**
 * Delete feature
 */
public static function deleteFeature($id) {
    try {
        if (!self::isValidObjectId($id)) {
            throw new Exception('Invalid feature ID');
        }

        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            throw new Exception('Database collection not available');
        }

        $objectId = new MongoDB\BSON\ObjectId($id);
        $feature = $collection->findOne(['_id' => $objectId]);

        if (!$feature) {
            throw new Exception('Feature not found');
        }

        // Delete associated file if it exists
        if (!empty($feature['sample_file']) && file_exists($feature['sample_file'])) {
            unlink($feature['sample_file']);
        }

        // Delete the feature
        $result = $collection->deleteOne(['_id' => $objectId]);

        if ($result->getDeletedCount() === 0) {
            throw new Exception('Failed to delete feature');
        }

        // Reference fields
        $fields = ['system_name', 'module', 'feature', 'client', 'source'];
        $db = Database::getInstance()->getDatabase();

        foreach ($fields as $field) {
            $value = $feature[$field] ?? '';
            if (empty($value)) continue;

            $stillUsed = $collection->countDocuments([$field => $value]);
            if ($stillUsed === 0) {
                $refCollection = $db->getCollection($field . 's'); // pluralize
                $refCollection->deleteOne(['name' => $value, 'is_active' => true]);
            }
        }

        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error deleting feature: " . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
    }
}

    /**
 * Get search results count with synonym support
 */
public static function getSearchCount($keyword) {
    try {
        $collection = Database::getInstance()->getCollection('overall');
        if (!$collection) {
            return 0;
        }

        if (empty($keyword)) {
            return $collection->countDocuments();
        }

        // Try synonym-based count first
        $expandedTerms = self::expandSearchQuery($keyword);
        $searchConditions = [];
        
        foreach ($expandedTerms as $term) {
            $regex = new MongoDB\BSON\Regex($term, 'i');
            $searchConditions[] = [
                '$or' => [
                    ['system_name' => $regex],
                    ['module' => $regex],
                    ['feature' => $regex],
                    ['description' => $regex],
                    ['client' => $regex],
                    ['source' => $regex]
                ]
            ];
        }
        
        $query = ['$or' => $searchConditions];
        return $collection->countDocuments($query);
        
    } catch (Exception $e) {
        error_log("Error getting enhanced search count: " . $e->getMessage());
        // Fallback to original search count
        $sanitizedKeyword = self::sanitizeString($keyword);
        $regex = new MongoDB\BSON\Regex($sanitizedKeyword, 'i');
        
        $query = [
            '$or' => [
                ['system_name' => $regex],
                ['module' => $regex],
                ['feature' => $regex],
                ['description' => $regex],
                ['client' => $regex],
                ['source' => $regex]
            ]
        ];

        return $collection->countDocuments($query);
    }
}

    /**
     * Calculate total pages
     */
    public static function getTotalPages($limit) {
        try {
            $collection = Database::getInstance()->getCollection('overall');
            if (!$collection) {
                return 1;
            }

            $total = $collection->countDocuments();
            return $limit > 0 ? ceil($total / $limit) : 1;
        } catch (Exception $e) {
            error_log("Error getting total pages: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get unique values for dropdown - now gets from correct collections
     */
    public static function getDistinctValues($field) {
        try {
            $db = Database::getInstance()->getDatabase();
            if (!$db) {
                return [];
            }

            // Map fields to their respective collections
            $collectionMap = [
                'system_name' => 'system_names',
                'module' => 'modules',
                'feature' => 'features',
                'client' => 'clients',
                'source' => 'sources'
            ];

            if (!isset($collectionMap[$field])) {
                return [];
            }

            $collection = $db->getCollection($collectionMap[$field]);
            
            // Get values from the reference collections
            $cursor = $collection->find(['is_active' => true], ['sort' => ['name' => 1]]);
            $values = [];
            
            foreach ($cursor as $doc) {
                if (!empty($doc['name'])) {
                    $values[] = trim($doc['name']);
                }
            }

            return array_unique($values);
        } catch (Exception $e) {
            error_log("Error getting distinct values for '$field': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add reference data if it doesn't exist
     */
    private static function ensureReferenceDataWithId($db, $collectionName, $value) {
    if (empty(trim($value))) return null;
    
    $value = trim($value);
    $collection = $db->getCollection($collectionName);
    
    // Check if exists
    $existing = $collection->findOne(['name' => $value, 'is_active' => true]);
    if ($existing) {
        return $existing['id'] ?? $existing['_id'];
    }
    
    // Get next ID
    $lastDoc = $collection->findOne([], ['sort' => ['id' => -1]]);
    $nextId = ($lastDoc['id'] ?? 0) + 1;
    
    $doc = [
        'id' => $nextId,
        'name' => $value,
        'is_active' => true,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $collection->insertOne($doc);
    return $nextId;
}

/**
 * Generate Data ID based on system name
 */
private static function generateDataId($systemName, $systemId) {
    // Get the first word and convert to lowercase
    $words = explode(' ', trim($systemName));
    $firstWord = strtolower($words[0]);
    
    // Remove any non-alphanumeric characters for cleaner IDs
    $firstWord = preg_replace('/[^a-z0-9]/', '', $firstWord);
    
    return $firstWord . '_' . $systemId;
}

/**
 * Generate System Name ID
 */
private static function generateSystemNameId($systemName, $systemId) {
    // Get the first word and convert to lowercase
    $words = explode(' ', trim($systemName));
    $firstWord = strtolower($words[0]);
    
    // Remove any non-alphanumeric characters for cleaner IDs
    $firstWord = preg_replace('/[^a-z0-9]/', '', $firstWord);
    
    return $firstWord . '_' . $systemId;
}

/**
 * Generate Module ID
 */
private static function generateModuleId($moduleName, $moduleId) {
    // Get the first word and convert to lowercase
    $words = explode(' ', trim($moduleName));
    $firstWord = strtolower($words[0]);
    
    // Remove any non-alphanumeric characters for cleaner IDs
    $firstWord = preg_replace('/[^a-z0-9]/', '', $firstWord);
    
    return $firstWord . '_' . $moduleId;
}

/**
 * Generate Feature ID
 */
private static function generateFeatureId($featureName, $featureId) {
    // Get the first word and convert to lowercase
    $words = explode(' ', trim($featureName));
    $firstWord = strtolower($words[0]);
    
    // Remove any non-alphanumeric characters for cleaner IDs
    $firstWord = preg_replace('/[^a-z0-9]/', '', $firstWord);
    
    return $firstWord . '_' . $featureId;
}

private static function generateSourceId($featureName, $featureId) {
    // Get the first word and convert to lowercase
    $words = explode(' ', trim($featureName));
    $firstWord = strtolower($words[0]);
    
    // Remove any non-alphanumeric characters for cleaner IDs
    $firstWord = preg_replace('/[^a-z0-9]/', '', $firstWord);
    
    return $firstWord . '_' . $featureId;
}

private static function generateClientId($clientName, $clientId) {
    // Get the first word and convert to lowercase
    $words = explode(' ', trim($clientName));
    $firstWord = strtolower($words[0]);
    
    // Remove any non-alphanumeric characters for cleaner IDs
    $firstWord = preg_replace('/[^a-z0-9]/', '', $firstWord);
    
    return $firstWord . '_' . $clientId;
}

    /**
     * Handle file upload
     */
    private static function handleFileUpload($fileData, $fieldName) {
        if (!isset($fileData[$fieldName]) || $fileData[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => null];
        }

        $file = $fileData[$fieldName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds php.ini limit)',
                UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            
            return ['success' => false, 'error' => $errorMessages[$file['error']] ?? 'Unknown upload error'];
        }

        if ($file['size'] > self::$maxFileSize) {
            return ['success' => false, 'error' => 'File is too large (max 10MB)'];
        }

        $filename = basename($file['name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($extension, self::$allowedExtensions)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }

        if (!is_dir(self::$uploadDir)) {
            if (!mkdir(self::$uploadDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
        }

        $uniqueFilename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        $targetPath = self::$uploadDir . $uniqueFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }

        return ['success' => true, 'path' => $targetPath];
    }

    /**
     * Sanitize string input - FIXED to handle HTML entities properly
     */
    private static function sanitizeString($input) {
        if ($input === null) return '';
        
         return html_entity_decode(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate MongoDB ObjectId
     */
    private static function isValidObjectId($id) {
        return preg_match('/^[0-9a-fA-F]{24}$/', $id);
    }
}
?>
