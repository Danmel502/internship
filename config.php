<?php
/**
 * Database Configuration File
 * Aligned with FeatureController architecture using 6 collections
 */

require_once 'vendor/autoload.php';

class Database {
    private static $instance = null;
    private $client;
    private $database;
    private $connectionString;
    private $databaseName;

    private function __construct(
        string $connectionString = 'mongodb://localhost:27017',
        string $databaseName = 'features_db'
    ) {
        $this->connectionString = $connectionString;
        $this->databaseName = $databaseName;
        $this->connect();
    }

    private function connect(): void {
        try {
            $this->client = new MongoDB\Client($this->connectionString);
            $this->client->selectDatabase('admin')->command(['ping' => 1]);
            $this->database = $this->client->selectDatabase($this->databaseName);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    public static function getInstance(
        string $connectionString = 'mongodb://localhost:27017',
        string $databaseName = 'features_db'
    ): Database {
        if (self::$instance === null) {
            self::$instance = new self($connectionString, $databaseName);
        }
        return self::$instance;
    }

    public function getCollection(string $collectionName = 'overall'): MongoDB\Collection {
        return $this->database->selectCollection($collectionName);
    }

    public function getDatabase(): MongoDB\Database {
        return $this->database;
    }

    public function getClient(): MongoDB\Client {
        return $this->client;
    }

    public function getConnectionInfo(): array {
        return [
            'connection_string' => $this->connectionString,
            'database' => $this->databaseName
        ];
    }

    public function testConnection(): bool {
        try {
            $this->client->selectDatabase('admin')->command(['ping' => 1]);
            return true;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            return false;
        }
    }

    /**
     * Create indexes for all collections
     */
    public function createIndexes(): void {
        try {
            // Indexes for main 'overall' collection
            $overallCollection = $this->getCollection('overall');
            $overallCollection->createIndex(['created_at' => -1]);
            $overallCollection->createIndex(['updated_at' => -1]);
            $overallCollection->createIndex(['system_name' => 1]);
            $overallCollection->createIndex(['module' => 1]);
            $overallCollection->createIndex(['client' => 1]);
            $overallCollection->createIndex(['source' => 1]);
            $overallCollection->createIndex(['feature' => 1]);
            
            // Text index for search functionality
            $overallCollection->createIndex([
                'system_name' => 'text',
                'module' => 'text',
                'feature' => 'text',
                'description' => 'text',
                'client' => 'text',
                'source' => 'text'
            ]);

            // Compound indexes for common queries
            $overallCollection->createIndex(['system_name' => 1, 'module' => 1]);
            $overallCollection->createIndex(['client' => 1, 'source' => 1]);

            // Indexes for reference collections - UPDATED to include 'id' field
            $referenceCollections = ['system_names', 'modules', 'features', 'clients', 'sources'];
            foreach ($referenceCollections as $collectionName) {
                $collection = $this->getCollection($collectionName);
                $collection->createIndex(['name' => 1], ['unique' => true]);
                $collection->createIndex(['id' => 1], ['unique' => true]); // Added id index
                $collection->createIndex(['is_active' => 1]);
                $collection->createIndex(['created_at' => -1]);
                $collection->createIndex(['name' => 1, 'is_active' => 1]);
            }

            error_log("Database indexes created successfully");
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error creating indexes: " . $e->getMessage());
        }
    }

    /**
     * Initialize reference collections with proper structure
     */
    public function initializeReferenceCollections(): void {
        $referenceCollections = ['system_names', 'modules', 'features', 'clients', 'sources'];

        foreach ($referenceCollections as $collectionName) {
            try {
                $collection = $this->getCollection($collectionName);

                // Ensure unique indexes on both name and id fields
                $collection->createIndex(['name' => 1], ['unique' => true]);
                $collection->createIndex(['id' => 1], ['unique' => true]); // Added id index
                $collection->createIndex(['is_active' => 1]);
                $collection->createIndex(['created_at' => -1]);
                $collection->createIndex(['name' => 1, 'is_active' => 1]);

            } catch (MongoDB\Driver\Exception\Exception $e) {
                error_log("Error initializing collection {$collectionName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get active reference data for dropdowns
     */
    public function getActiveReferenceData(string $collectionName): array {
        try {
            $collection = $this->getCollection($collectionName);
            $cursor = $collection->find(
                ['is_active' => true], 
                ['sort' => ['name' => 1]]
            );
            
            $results = [];
            foreach ($cursor as $doc) {
                if (!empty($doc['name'])) {
                    $results[] = $doc['name'];
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error getting reference data from {$collectionName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add new reference data with auto-increment ID - FIXED
     */
    public function addReferenceData(string $collectionName, string $name): int|bool {
        if (empty(trim($name))) {
            return false;
        }

        try {
            $collection = $this->getCollection($collectionName);
            
            // Check if already exists
            $existing = $collection->findOne(['name' => trim($name), 'is_active' => true]);
            if ($existing) {
                return $existing['id'] ?? false;
            }
            
            // Get next ID
            $lastDoc = $collection->findOne([], ['sort' => ['id' => -1]]);
            $nextId = ($lastDoc['id'] ?? 0) + 1;
            
            $document = [
                'id' => $nextId, // Added auto-increment ID
                'name' => trim($name),
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->insertOne($document);
            return $result->getInsertedCount() === 1 ? $nextId : false;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            // Don't log duplicate key errors as they're expected
            if (strpos($e->getMessage(), 'duplicate key') === false) {
                error_log("Error adding reference data to {$collectionName}: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Update reference data
     */
    public function updateReferenceData(string $collectionName, string $oldName, string $newName): bool {
        if (empty(trim($newName))) {
            return false;
        }

        try {
            $collection = $this->getCollection($collectionName);
            $result = $collection->updateOne(
                ['name' => $oldName],
                ['$set' => [
                    'name' => trim($newName), 
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            return $result->getModifiedCount() === 1;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error updating reference data in {$collectionName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate reference data (soft delete)
     */
    public function deactivateReferenceData(string $collectionName, string $name): bool {
        try {
            $collection = $this->getCollection($collectionName);
            $result = $collection->updateOne(
                ['name' => $name],
                ['$set' => [
                    'is_active' => false, 
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            return $result->getModifiedCount() === 1;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error deactivating reference data in {$collectionName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reactivate reference data
     */
    public function reactivateReferenceData(string $collectionName, string $name): bool {
        try {
            $collection = $this->getCollection($collectionName);
            $result = $collection->updateOne(
                ['name' => $name],
                ['$set' => [
                    'is_active' => true, 
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            return $result->getModifiedCount() === 1;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error reactivating reference data in {$collectionName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete feature from overall collection (matches delete.php pattern)
     */
    public function deleteFeature(string $id): bool {
        try {
            $collection = $this->getCollection(); // Uses default 'overall' collection
            $objectId = new MongoDB\BSON\ObjectId($id);
            
            $result = $collection->deleteOne(['_id' => $objectId]);
            return $result->getDeletedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error deleting feature: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Error deleting feature: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete feature with session message handling (matches delete.php exactly)
     */
    public function deleteFeatureWithSession(string $id): array {
        try {
            $collection = $this->getCollection(); // Uses default 'overall' collection
            $result = $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);

            if ($result->getDeletedCount() > 0) {
                return ['success' => true, 'message' => "✅ Feature deleted successfully!"];
            } else {
                return ['success' => false, 'message' => "⚠️ Failed to delete. Feature not found."];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => "❌ Deletion error: " . $e->getMessage()];
        }
    }

    /**
     * Get feature by ID from overall collection
     */
    public function getFeatureById(string $id): ?array {
        try {
            if (!$this->isValidObjectId($id)) {
                return null;
            }

            $collection = $this->getCollection('overall');
            $objectId = new MongoDB\BSON\ObjectId($id);
            
            $feature = $collection->findOne(['_id' => $objectId]);
            return $feature ? $feature->toArray() : null;
        } catch (Exception $e) {
            error_log("Error getting feature by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Bulk delete features
     */
    public function bulkDeleteFeatures(array $ids): array {
        $results = ['deleted' => 0, 'errors' => []];
        
        foreach ($ids as $id) {
            if ($this->deleteFeature($id)) {
                $results['deleted']++;
            } else {
                $results['errors'][] = $id;
            }
        }
        
        return $results;
    }

    /**
     * Validate MongoDB ObjectId format
     */
    private function isValidObjectId(string $id): bool {
        return preg_match('/^[0-9a-fA-F]{24}$/', $id);
    }

    /**
     * Get database statistics
     */
    public function getStats(): array {
        try {
            $collections = ['overall', 'system_names', 'modules', 'features', 'clients', 'sources'];
            $stats = [];
            
            foreach ($collections as $collectionName) {
                $collection = $this->getCollection($collectionName);
                $stats[$collectionName] = [
                    'count' => $collection->countDocuments(),
                    'active_count' => ($collectionName !== 'overall') 
                        ? $collection->countDocuments(['is_active' => true])
                        : null
                ];
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting database stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up inactive reference data (hard delete)
     */
    public function cleanupInactiveReferenceData(): array {
        $referenceCollections = ['system_names', 'modules', 'features', 'clients', 'sources'];
        $results = [];
        
        foreach ($referenceCollections as $collectionName) {
            try {
                $collection = $this->getCollection($collectionName);
                $result = $collection->deleteMany(['is_active' => false]);
                $results[$collectionName] = $result->getDeletedCount();
            } catch (Exception $e) {
                error_log("Error cleaning up {$collectionName}: " . $e->getMessage());
                $results[$collectionName] = 0;
            }
        }
        
        return $results;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

// Bootstrap database initialization
try {
    $db = Database::getInstance();
    $db->createIndexes();
    $db->initializeReferenceCollections();
    error_log("Database configuration loaded successfully");
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}
?>