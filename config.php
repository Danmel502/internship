<?php
/**
 * Database Configuration File
 * Aligned with FeatureController architecture using 7 collections + users collection
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

            // Indexes for reference collections
            $referenceCollections = ['system_names', 'modules', 'features', 'clients', 'sources'];
            foreach ($referenceCollections as $collectionName) {
                $collection = $this->getCollection($collectionName);
                $collection->createIndex(['name' => 1], ['unique' => true]);
                $collection->createIndex(['id' => 1], ['unique' => true]);
                $collection->createIndex(['is_active' => 1]);
                $collection->createIndex(['created_at' => -1]);
                $collection->createIndex(['name' => 1, 'is_active' => 1]);
            }

            // Indexes for users collection
            $usersCollection = $this->getCollection('users');
            $usersCollection->createIndex(['username' => 1], ['unique' => true]);
            $usersCollection->createIndex(['email' => 1], ['unique' => true]);
            $usersCollection->createIndex(['is_active' => 1]);
            $usersCollection->createIndex(['created_at' => -1]);
            $usersCollection->createIndex(['last_login' => -1]);

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
                $collection->createIndex(['id' => 1], ['unique' => true]);
                $collection->createIndex(['is_active' => 1]);
                $collection->createIndex(['created_at' => -1]);
                $collection->createIndex(['name' => 1, 'is_active' => 1]);

            } catch (MongoDB\Driver\Exception\Exception $e) {
                error_log("Error initializing collection {$collectionName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Initialize keywords collection with proper structure
     */
    public function initializeKeywordsCollection(): void {
        try {
            $collection = $this->getCollection('keywords');
            
            // Create indexes for keywords collection
            $collection->createIndex(['keyword' => 1], ['unique' => true]);
            $collection->createIndex(['id' => 1], ['unique' => true]);
            $collection->createIndex(['is_active' => 1]);
            $collection->createIndex(['created_at' => -1]);
            $collection->createIndex(['keyword' => 1, 'is_active' => 1]);
            
            error_log("Keywords collection initialized successfully");
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error initializing keywords collection: " . $e->getMessage());
        }
    }

    /**
     * Initialize users collection and create default admin user
     */
    public function initializeUsersCollection(): void {
        try {
            $collection = $this->getCollection('users');
            
            // Create indexes for users collection
            $collection->createIndex(['username' => 1], ['unique' => true]);
            $collection->createIndex(['email' => 1], ['unique' => true]);
            $collection->createIndex(['is_active' => 1]);
            $collection->createIndex(['created_at' => -1]);
            $collection->createIndex(['last_login' => -1]);

            // Check if default admin user exists
            $adminExists = $collection->findOne(['username' => 'admin']);
            
            if (!$adminExists) {
                // Create default admin user
                $defaultAdmin = [
                    'username' => 'admin',
                    'email' => 'admin@mediatrack.com',
                    'password' => password_hash('admin123', PASSWORD_BCRYPT),
                    'full_name' => 'System Administrator',
                    'role' => 'admin',
                    'is_active' => true,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_at' => new MongoDB\BSON\UTCDateTime(),
                    'last_login' => null
                ];
                
                $result = $collection->insertOne($defaultAdmin);
                if ($result->getInsertedCount() === 1) {
                    error_log("Default admin user created successfully");
                }
            }
            
            error_log("Users collection initialized successfully");
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error initializing users collection: " . $e->getMessage());
        }
    }

    /**
     * USER MANAGEMENT METHODS
     */

    /**
     * Create a new user
     */
    public function createUser(array $userData): array {
        try {
            $collection = $this->getCollection('users');
            
            // Check if username already exists
            $existingUser = $collection->findOne(['username' => $userData['username']]);
            if ($existingUser) {
                return ['success' => false, 'error' => 'Username already exists'];
            }
            
            // Check if email already exists
            $existingEmail = $collection->findOne(['email' => $userData['email']]);
            if ($existingEmail) {
                return ['success' => false, 'error' => 'Email already exists'];
            }
            
            // Hash password
            $userData['password'] = password_hash($userData['password'], PASSWORD_BCRYPT);
            
            // Set default values
            $userData['is_active'] = true;
            $userData['role'] = $userData['role'] ?? 'user';
            $userData['created_at'] = new MongoDB\BSON\UTCDateTime();
            $userData['updated_at'] = new MongoDB\BSON\UTCDateTime();
            $userData['last_login'] = null;
            
            $result = $collection->insertOne($userData);
            
            return [
                'success' => $result->getInsertedCount() === 1,
                'user_id' => $result->getInsertedId()
            ];
            
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }

    /**
     * Authenticate user login
     */
    public function authenticateUser(string $username, string $password): array {
        try {
            $collection = $this->getCollection('users');
            
            $user = $collection->findOne([
                'username' => $username,
                'is_active' => true
            ]);
            
            if (!$user) {
                return ['success' => false, 'error' => 'Invalid username or password'];
            }
            
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'error' => 'Invalid username or password'];
            }
            
            // Update last login
            $collection->updateOne(
                ['_id' => $user['_id']],
                ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            return [
                'success' => true,
                'user' => [
                    'id' => (string)$user['_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'] ?? '',
                    'role' => $user['role'] ?? 'user',
                    'last_login' => $user['last_login']
                ]
            ];
            
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log("Error authenticating user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }

    /**
     * Get user by username
     */
    public function getUserByUsername(string $username): ?array {
        try {
            $collection = $this->getCollection('users');
            $user = $collection->findOne(['username' => $username, 'is_active' => true]);
            
            if ($user) {
                return [
                    'id' => (string)$user['_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'] ?? '',
                    'role' => $user['role'] ?? 'user',
                    'created_at' => $user['created_at'],
                    'last_login' => $user['last_login']
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user profile
     */
    public function updateUser(string $userId, array $updateData): bool {
        try {
            $collection = $this->getCollection('users');
            
            // Remove sensitive fields that shouldn't be updated directly
            unset($updateData['password'], $updateData['_id'], $updateData['created_at']);
            
            $updateData['updated_at'] = new MongoDB\BSON\UTCDateTime();
            
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => $updateData]
            );
            
            return $result->getModifiedCount() === 1;
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change user password
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): array {
        try {
            $collection = $this->getCollection('users');
            
            // Get current user
            $user = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'password' => $hashedPassword,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            return ['success' => $result->getModifiedCount() === 1];
            
        } catch (Exception $e) {
            error_log("Error changing password: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }

    /**
     * Get all users (admin function)
     */
    public function getAllUsers(int $limit = 50, int $skip = 0): array {
        try {
            $collection = $this->getCollection('users');
            
            $cursor = $collection->find(
                ['is_active' => true],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit,
                    'skip' => $skip,
                    'projection' => ['password' => 0] // Exclude password field
                ]
            );
            
            $users = [];
            foreach ($cursor as $user) {
                $users[] = [
                    'id' => (string)$user['_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'] ?? '',
                    'role' => $user['role'] ?? 'user',
                    'created_at' => $user['created_at'],
                    'last_login' => $user['last_login']
                ];
            }
            
            return $users;
        } catch (Exception $e) {
            error_log("Error getting all users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deactivate user (soft delete)
     */
    public function deactivateUser(string $userId): bool {
        try {
            $collection = $this->getCollection('users');
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'is_active' => false,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            return $result->getModifiedCount() === 1;
        } catch (Exception $e) {
            error_log("Error deactivating user: " . $e->getMessage());
            return false;
        }
    }

    // ... (keep all existing methods from original config.php)

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
     * Add new reference data with auto-increment ID
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
                'id' => $nextId,
                'name' => trim($name),
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->insertOne($document);
            return $result->getInsertedCount() === 1 ? $nextId : false;
        } catch (MongoDB\Driver\Exception\Exception $e) {
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
     * Delete feature from overall collection
     */
    public function deleteFeature(string $id): bool {
        try {
            $collection = $this->getCollection();
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
     * Delete feature with session message handling
     */
    public function deleteFeatureWithSession(string $id): array {
        try {
            $collection = $this->getCollection();
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
            $collections = ['overall', 'system_names', 'modules', 'features', 'clients', 'sources', 'users'];
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
    $db->initializeKeywordsCollection();
    $db->initializeUsersCollection(); // Initialize users collection
    error_log("Database configuration loaded successfully");
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}
?>