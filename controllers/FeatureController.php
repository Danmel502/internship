<?php
require_once __DIR__ . '/../config.php';

class FeatureController {
    
    private static $uploadDir = 'uploads/';
    private static $maxFileSize = 10 * 1024 * 1024; // 10MB
    private static $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx'];

 /**
 * Expand search query using database synonym dictionary
 */
private static function expandSearchQuery($searchTerm) {
    try {
        $db = Database::getInstance()->getDatabase();
        $keywordsCollection = $db->getCollection('keywords');
        
        // Normalize search term
        $searchTerm = strtolower(trim($searchTerm));

        // Handle plural forms (simple cases)
        if (substr($searchTerm, -3) === 'ies') {
            $searchTerm = substr($searchTerm, 0, -3) . 'y';
        } elseif (substr($searchTerm, -2) === 'es') {
            $searchTerm = substr($searchTerm, 0, -2);
        } elseif (substr($searchTerm, -1) === 's') {
            $searchTerm = substr($searchTerm, 0, -1);
        }

        // Find matching keyword in database
        $keywordDoc = $keywordsCollection->findOne([
            'keyword' => $searchTerm,
            'is_active' => true
        ]);

        if ($keywordDoc && isset($keywordDoc['synonyms'])) {
            return $keywordDoc['synonyms'];
        }

        // If no match found, also search in synonyms arrays
        $keywordDocs = $keywordsCollection->find([
            'synonyms' => $searchTerm,
            'is_active' => true
        ]);

        foreach ($keywordDocs as $doc) {
            if (isset($doc['synonyms'])) {
                return $doc['synonyms'];
            }
        }

        // Return original term if no synonyms found
        return [$searchTerm];
        
    } catch (Exception $e) {
        error_log("Error expanding search query: " . $e->getMessage());
        return [$searchTerm];
    }
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
 * Get cascading dropdown data based on filters - FIXED for proper system name to module filtering
 */
public static function getCascadingData($type, $filters = []) {
    try {
        $db = Database::getInstance()->getDatabase();
        if (!$db) {
            return ['success' => false, 'error' => 'Database not available'];
        }

        // Map request types to collection names and ID fields
        $collectionMap = [
            'system_name' => ['collection' => 'system_names', 'id_field' => 'system_name_id'],
            'module' => ['collection' => 'modules', 'id_field' => 'module_id'],
            'feature' => ['collection' => 'features', 'id_field' => 'feature_id'], 
            'client' => ['collection' => 'clients', 'id_field' => 'client_id'],
            'source' => ['collection' => 'sources', 'id_field' => 'source_id']
        ];

        if (!isset($collectionMap[$type])) {
            return ['success' => false, 'error' => 'Invalid type parameter'];
        }

        $targetCollection = $collectionMap[$type]['collection'];
        $targetIdField = $collectionMap[$type]['id_field'];

        // If no filters, get all active items from reference collection
        if (empty($filters)) {
            $collection = $db->getCollection($targetCollection);
            $cursor = $collection->find(['is_active' => true], ['sort' => ['name' => 1]]);
            
            $values = [];
            foreach ($cursor as $doc) {
                if (!empty($doc['name'])) {
                    $values[] = trim($doc['name']);
                }
            }
            
            return ['success' => true, 'data' => array_values(array_unique($values))];
        }

        // SPECIAL HANDLING FOR MODULES WHEN SYSTEM_NAME IS FILTERED
        if ($type === 'module' && isset($filters['system_name']) && !empty($filters['system_name'])) {
            $systemNameFilter = $filters['system_name'];
            
            // First, get the system_name_id from the system name
            $systemNamesCollection = $db->getCollection('system_names');
            $systemNameDoc = $systemNamesCollection->findOne([
                'name' => $systemNameFilter, 
                'is_active' => true
            ]);
            
            if (!$systemNameDoc) {
                return ['success' => true, 'data' => []];
            }
            
            $systemNameId = $systemNameDoc['id'];
            
            // Now get modules that belong to this system name
            $modulesCollection = $db->getCollection('modules');
            $cursor = $modulesCollection->find([
                'system_name_id' => $systemNameId,
                'is_active' => true
            ], ['sort' => ['name' => 1]]);
            
            $values = [];
            foreach ($cursor as $doc) {
                if (!empty($doc['name'])) {
                    $values[] = trim($doc['name']);
                }
            }
            
            return ['success' => true, 'data' => array_values(array_unique($values))];
        }

        // For other types or complex filters, use the overall collection approach
        $overallCollection = $db->getCollection('overall');
        $filterQuery = [];

        // Convert filter names to their corresponding ID lookups for overall collection filtering
        foreach ($filters as $filterField => $filterValue) {
            if (empty($filterValue)) continue;
            
            if (isset($collectionMap[$filterField])) {
                $filterCollection = $collectionMap[$filterField]['collection'];
                $filterIdField = $collectionMap[$filterField]['id_field'];
                
                // Get the ID for this filter value
                $refCollection = $db->getCollection($filterCollection);
                $refDoc = $refCollection->findOne(['name' => $filterValue, 'is_active' => true]);
                
                if ($refDoc) {
                    $filterQuery[$filterIdField] = $refDoc['id'];
                }
            }
        }

        // Get distinct target IDs from overall collection that match filters
        $validIds = $overallCollection->distinct($targetIdField, $filterQuery);
        
        if (empty($validIds)) {
            return ['success' => true, 'data' => []];
        }

        // Get names for these valid IDs from reference collection
        $targetCollectionObj = $db->getCollection($targetCollection);
        $cursor = $targetCollectionObj->find([
            'id' => ['$in' => $validIds],
            'is_active' => true
        ], ['sort' => ['name' => 1]]);
        
        $values = [];
        foreach ($cursor as $doc) {
            if (!empty($doc['name'])) {
                $values[] = trim($doc['name']);
            }
        }

        return ['success' => true, 'data' => array_values(array_unique($values))];

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

            // Clean up reference collections based on IDs
$cleanupCount = 0;
$db = Database::getInstance()->getDatabase();
if ($db) {
    // Get all unique IDs that were deleted
    $deletedIds = [];
    
    foreach ($featuresToDelete as $feature) {
        $idFields = [
            'system_name_id' => 'system_names',
            'module_id' => 'modules',
            'feature_id' => 'features',
            'client_id' => 'clients',
            'source_id' => 'sources'
        ];
        
        foreach ($idFields as $idField => $collectionName) {
            $id = $feature[$idField] ?? null;
            if (!empty($id)) {
                if (!isset($deletedIds[$collectionName])) {
                    $deletedIds[$collectionName] = [];
                }
                $deletedIds[$collectionName][] = $id;
            }
        }
    }

    // Clean up each reference collection
    foreach ($deletedIds as $collectionName => $ids) {
        $uniqueIds = array_unique($ids);
        $idFieldMap = [
            'system_names' => 'system_name_id',
            'modules' => 'module_id', 
            'features' => 'feature_id',
            'clients' => 'client_id',
            'sources' => 'source_id'
        ];
        
        $idField = $idFieldMap[$collectionName];
        
        foreach ($uniqueIds as $id) {
            // Check if this ID is still used in other documents
            $stillUsed = $collection->countDocuments([$idField => $id]);
            
            if ($stillUsed === 0) {
                // ID is no longer used, remove from reference collection
                $refCollection = $db->getCollection($collectionName);
                $deleteResult = $refCollection->deleteOne(['id' => $id, 'is_active' => true]);
                if ($deleteResult->getDeletedCount() > 0) {
                    $cleanupCount++;
                    error_log("Cleaned up unused reference: {$collectionName} - ID {$id}");
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
        // Get system name from ID
        if (!empty($feature['system_name_id'])) {
            $systemNameId = $feature['system_name_id'];
            if (!isset($systemNamesCache[$systemNameId])) {
                $systemName = $systemNamesCollection->findOne(['id' => $systemNameId, 'is_active' => true]);
                $systemNamesCache[$systemNameId] = $systemName ? $systemName['name'] : 'Unknown System';
            }
            $feature['system_name'] = $systemNamesCache[$systemNameId];
        }
        
        // Get module name from ID
        if (!empty($feature['module_id'])) {
            $moduleId = $feature['module_id'];
            if (!isset($modulesCache[$moduleId])) {
                $module = $modulesCollection->findOne(['id' => $moduleId, 'is_active' => true]);
                $modulesCache[$moduleId] = $module ? $module['name'] : 'Unknown Module';
            }
            $feature['module'] = $modulesCache[$moduleId];
        }
        
        // Get client name from ID
        if (!empty($feature['client_id'])) {
            $clientId = $feature['client_id'];
            if (!isset($clientsCache[$clientId])) {
                $client = $clientsCollection->findOne(['id' => $clientId, 'is_active' => true]);
                $clientsCache[$clientId] = $client ? $client['name'] : 'Unknown Client';
            }
            $feature['client'] = $clientsCache[$clientId];
        }
        
        // Get source name from ID
        if (!empty($feature['source_id'])) {
            $sourceId = $feature['source_id'];
            if (!isset($sourcesCache[$sourceId])) {
                $source = $sourcesCollection->findOne(['id' => $sourceId, 'is_active' => true]);
                $sourcesCache[$sourceId] = $source ? $source['name'] : 'Unknown Source';
            }
            $feature['source'] = $sourcesCache[$sourceId];
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
            
            // Build data array - ADD THE MISSING system_name AND module FIELDS
            $data = [
                'system_name' => $originalSystemName, // ← THIS WAS MISSING
                'module' => $originalModule,          // ← THIS WAS MISSING
                'feature' => $originalFeature,
                'client' => $originalClient,          // ← ADD FOR CONSISTENCY
                'source' => $originalSource,          // ← ADD FOR CONSISTENCY
                'feature_id' => $featureId,
                'system_name_id' => $systemNameId,
                'module_id' => $moduleId,
                'client_id' => $clientId,
                'source_id' => $sourceId,
                'description' => self::sanitizeString($postData['description'])
            ];
            
            // Add sample_file if exists
            if ($sampleFile) {
                $data['sample_file'] = $sampleFile;
            }
            
            // Add timestamps
            $data['created_at'] = new MongoDB\BSON\UTCDateTime();
            $data['updated_at'] = new MongoDB\BSON\UTCDateTime();
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

        // Store original names for display
        $originalSystemName = self::sanitizeString($postData[$fieldMapping['system_name']]);
        $originalModule = self::sanitizeString($postData[$fieldMapping['module']]);
        $originalFeature = self::sanitizeString($postData[$fieldMapping['feature']]);
        $originalClient = self::sanitizeString($postData[$fieldMapping['client']]);
        $originalSource = self::sanitizeString($postData[$fieldMapping['source']]);

        // Store old values for module relationship update
        $oldSystemName = $existingFeature['system_name'] ?? '';
        $oldModule = $existingFeature['module'] ?? '';

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
            'module' => $originalModule,
            'feature' => $originalFeature,
            'feature_id' => $featureId,
            'system_name_id' => $systemNameId,
            'module_id' => $moduleId,
            'client_id' => $clientId,
            'source_id' => $sourceId,
            'description' => self::sanitizeString($postData[$fieldMapping['description']]),
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

        // ===== CRITICAL: UPDATE MODULE RELATIONSHIPS FOR CASCADING DROPDOWNS =====
        // Check if system_name or module was changed
        if (($oldSystemName !== $originalSystemName || $oldModule !== $originalModule) && $db) {
            self::updateModuleRelationships($db, $oldSystemName, $oldModule, $originalSystemName, $originalModule);
        }

        // Set timestamp for refresh
        $_SESSION['last_update_time'] = time();

        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error updating feature: " . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
    }
}

// ===== ADD THIS NEW METHOD TO YOUR FeatureController CLASS =====
private static function updateModuleRelationships($db, $oldSystemName, $oldModule, $newSystemName, $newModule) {
    try {
        $modulesCollection = $db->getCollection('modules');
        $overallCollection = $db->getCollection('overall');
        
        // Check if the new module already exists for this system
        $existingModule = $modulesCollection->findOne([
            'name' => $newModule,
            'system_name' => $newSystemName,
            'is_active' => true
        ]);
        
        if (!$existingModule) {
            // Create the new module if it doesn't exist
            $lastModuleDoc = $modulesCollection->findOne([], ['sort' => ['id' => -1]]);
            $nextModuleId = ($lastModuleDoc['id'] ?? 0) + 1;
            
            // Try to find system_name_id
            $systemNamesCollection = $db->getCollection('system_names');
            $systemNameDoc = $systemNamesCollection->findOne(['name' => $newSystemName]);
            $systemNameId = $systemNameDoc['id'] ?? 0;
            
            $modulesCollection->insertOne([
                'id' => $nextModuleId,
                'name' => $newModule,
                'description' => 'Created from feature edit',
                'system_name' => $newSystemName,
                'system_name_id' => $systemNameId,
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            // Also update the overall collection for AJAX compatibility
            $overallCollection->updateMany(
                [
                    'system_name' => $newSystemName,
                    'module' => $newModule
                ],
                ['$set' => [
                    'module_id' => $nextModuleId,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]],
                ['upsert' => true]
            );
        }
        
        // Update all features with the old module to use the new module
        if (!empty($oldSystemName) && !empty($oldModule)) {
            $overallCollection->updateMany(
                [
                    'system_name' => $oldSystemName,
                    'module' => $oldModule
                ],
                ['$set' => [
                    'system_name' => $newSystemName,
                    'module' => $newModule,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Module relationship update error: " . $e->getMessage());
        return false;
    }
}

public static function updateFeatureModuleRelationship($systemName, $oldModule, $newModule, $featureId = null) {
    try {
        $db = Database::getInstance()->getDatabase();
        $modulesCollection = $db->getCollection('modules');
        $overallCollection = $db->getCollection('overall');
        
        // Check if the new module already exists for this system
        $existingModule = $modulesCollection->findOne([
            'name' => $newModule,
            'system_name' => $systemName,
            'is_active' => true
        ]);
        
        if (!$existingModule) {
            // Create the new module if it doesn't exist
            $lastModuleDoc = $modulesCollection->findOne([], ['sort' => ['id' => -1]]);
            $nextModuleId = ($lastModuleDoc['id'] ?? 0) + 1;
            
            $modulesCollection->insertOne([
                'id' => $nextModuleId,
                'name' => $newModule,
                'description' => 'Created from feature edit',
                'system_name' => $systemName,
                'system_name_id' => 0, // You might need to look this up
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            // Also update the overall collection for AJAX compatibility
            $overallCollection->updateMany(
                [
                    'system_name' => $systemName,
                    'module' => $newModule
                ],
                ['$set' => [
                    'module_id' => $nextModuleId,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]],
                ['upsert' => true]
            );
        }
        
        // Update all features with the old module to use the new module
        $overallCollection->updateMany(
            [
                'system_name' => $systemName,
                'module' => $oldModule
            ],
            ['$set' => [
                'module' => $newModule,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Module update error: " . $e->getMessage());
        return false;
    }
}

// Add this method to your FeatureController class
public static function detectOrphanedModules() {
    try {
        $db = Database::getInstance()->getDatabase();
        $overallCollection = $db->getCollection('overall');
        $modulesCollection = $db->getCollection('modules');
        
        // Get all features with modules
        $features = $overallCollection->find([
            'module' => ['$ne' => ''],
            'system_name' => ['$ne' => '']
        ]);
        
        $orphanedModules = [];
        
        foreach ($features as $feature) {
            $systemName = $feature['system_name'] ?? '';
            $moduleName = $feature['module'] ?? '';
            
            if (!empty($systemName) && !empty($moduleName)) {
                // Check if this module exists for this system
                $moduleExists = $modulesCollection->findOne([
                    'name' => $moduleName,
                    'system_name' => $systemName,
                    'is_active' => true
                ]);
                
                if (!$moduleExists) {
                    $orphanedModules[] = [
                        'feature_id' => (string)$feature['_id'],
                        'system_name' => $systemName,
                        'module' => $moduleName,
                        'description' => $feature['description'] ?? ''
                    ];
                }
            }
        }
        
        return $orphanedModules;
        
    } catch (Exception $e) {
        error_log("Error detecting orphaned modules: " . $e->getMessage());
        return [];
    }
}

// Update the detectMovedModules method in FeatureController.php
public static function detectMovedModules() {
    try {
        $db = Database::getInstance()->getDatabase();
        $overallCollection = $db->getCollection('overall');
        $modulesCollection = $db->getCollection('modules');
        
        // Get all modules with their current system assignments
        $modulesCursor = $modulesCollection->find(['is_active' => true]);
        $moduleSystemMap = [];
        
        foreach ($modulesCursor as $module) {
            if (isset($module['name']) && isset($module['system_name'])) {
                $moduleSystemMap[$module['name']] = $module['system_name'];
            }
        }
        
        // Find features with moved modules
        $movedModules = [];
        $featuresCursor = $overallCollection->find([
            'module' => ['$ne' => ''],
            'system_name' => ['$exists' => true] // Only documents with system_name field
        ]);
        
        foreach ($featuresCursor as $feature) {
            $moduleName = $feature['module'] ?? '';
            $currentSystem = $feature['system_name'] ?? '';
            
            // Only check if both module name and system name are present
            if (!empty($moduleName) && !empty($currentSystem) && isset($moduleSystemMap[$moduleName])) {
                $correctSystem = $moduleSystemMap[$moduleName];
                
                // If the system doesn't match, mark as moved
                if ($currentSystem !== $correctSystem) {
                    $movedModules[(string)$feature['_id']] = [
                        'current_system' => $currentSystem,
                        'correct_system' => $correctSystem,
                        'module' => $moduleName,
                        'feature_name' => $feature['feature'] ?? 'Unknown'
                    ];
                }
            }
        }
        
        return $movedModules;
        
    } catch (Exception $e) {
        error_log("Error detecting moved modules: " . $e->getMessage());
        return [];
    }
}

// Add this method to fix features with missing system_name
public static function fixMissingSystemNames() {
    try {
        $db = Database::getInstance()->getDatabase();
        $overallCollection = $db->getCollection('overall');
        $modulesCollection = $db->getCollection('modules');
        
        // Find features with modules but missing system_name
        $featuresToFix = $overallCollection->find([
            'module' => ['$ne' => ''],
            'system_name' => ['$exists' => false]
        ]);
        
        $fixedCount = 0;
        
        foreach ($featuresToFix as $feature) {
            $moduleName = $feature['module'] ?? '';
            
            if (!empty($moduleName)) {
                // Find the correct system for this module
                $moduleDoc = $modulesCollection->findOne([
                    'name' => $moduleName,
                    'is_active' => true
                ]);
                
                if ($moduleDoc && isset($moduleDoc['system_name'])) {
                    // Update the feature with the correct system name
                    $overallCollection->updateOne(
                        ['_id' => $feature['_id']],
                        ['$set' => [
                            'system_name' => $moduleDoc['system_name'],
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                    $fixedCount++;
                }
            }
        }
        
        return $fixedCount;
        
    } catch (Exception $e) {
        error_log("Error fixing missing system names: " . $e->getMessage());
        return 0;
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

        // Reference cleanup based on IDs
$db = Database::getInstance()->getDatabase();
$idFields = [
    'system_name_id' => 'system_names',
    'module_id' => 'modules', 
    'feature_id' => 'features',
    'client_id' => 'clients',
    'source_id' => 'sources'
];

foreach ($idFields as $idField => $collectionName) {
    $id = $feature[$idField] ?? null;
    if (empty($id)) continue;

    $stillUsed = $collection->countDocuments([$idField => $id]);
    if ($stillUsed === 0) {
        $refCollection = $db->getCollection($collectionName);
        $refCollection->deleteOne(['id' => $id, 'is_active' => true]);
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
    'source' => 'sources',
    'keyword' => 'keywords' // Add this line
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