<?php
require_once __DIR__ . '/../config.php';

// Include reference models
require_once __DIR__ . '/SystemName.php';
require_once __DIR__ . '/Module.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/Source.php';

class Features
{
    private $collection;
    private $systemNameModel;
    private $moduleModel;
    private $clientModel;
    private $sourceModel;

    public function __construct()
    {
        $this->collection = Database::getInstance()->getCollection('features');
        $this->systemNameModel = new SystemName();
        $this->moduleModel = new Module();
        $this->clientModel = new Client();
        $this->sourceModel = new Source();
    }

    /**
     * Get all features with pagination
     * 
     * @param int $skip Number of documents to skip
     * @param int $limit Maximum number of documents to return
     * @return array
     * 
     * 
     */
    public function getFeatures($skip = 0, $limit = 10)
    {
        return $this->collection->find([], [
            'skip' => $skip,
            'limit' => $limit,
            'sort' => ['created_at' => -1]
        ]);
    }

    public function getAllFeatures() {
    return $this->collection->find()->toArray();
}


    /**
     * Count all documents
     * 
     * @return int
     */
    public function countFeatures()
    {
        return $this->collection->countDocuments();
    }

    /**
     * Find a single feature by ID
     * 
     * @param string $id Feature ID
     * @return array|null
     */
    public function getFeatureById($id)
    {
        return $this->collection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($id)
        ]);
    }

    /**
     * Add a new feature
     * 
     * @param array $data Feature data
     * @return mixed
     */
    public function addFeature($data)
    {
        // Ensure all required fields exist
        $requiredFields = ['system_name', 'module', 'feature', 'description', 'client', 'source'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new InvalidArgumentException("Field '{$field}' is required");
            }
        }

        // Clean and prepare data
        $cleanData = [
            'system_name' => trim($data['system_name']),
            'module' => trim($data['module']),
            'feature' => trim($data['feature']),
            'description' => trim($data['description']),
            'client' => trim($data['client']),
            'source' => trim($data['source']),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        // Handle file upload data
        if (isset($data['file_path'])) {
            $cleanData['file_path'] = $data['file_path'];
        }
        if (isset($data['file_name'])) {
            $cleanData['file_name'] = $data['file_name'];
        }
        if (isset($data['file_size'])) {
            $cleanData['file_size'] = $data['file_size'];
        }
        if (isset($data['file_url'])) {
            $cleanData['file_url'] = $data['file_url'];
        }

        // Auto-add reference data to respective collections
        $this->addToReferenceCollections($cleanData);

       $result = $this->collection->insertOne($cleanData);

// Also insert the full feature data into the "overall" collection
try {
    $overallCollection = Database::getInstance()->getCollection('overall');
    // Clone the data and remove the existing _id (so MongoDB creates a new one)
    $overallData = $cleanData;
    unset($overallData['_id']);
    $overallCollection->insertOne($overallData);
} catch (Exception $e) {
    error_log("Failed to insert into overall collection: " . $e->getMessage());
}

return $result;
    }

    /**
     * Update an existing feature
     * 
     * @param string $id Feature ID
     * @param array $data Updated data
     * @return mixed
     */
    public function updateFeature($id, $data)
    {
        // Clean data
        $cleanData = [];
        $allowedFields = ['system_name', 'module', 'feature', 'description', 'client', 'source', 'file_path', 'file_name', 'file_size', 'file_url'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $cleanData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        $cleanData['updated_at'] = new MongoDB\BSON\UTCDateTime();

        // Auto-add new reference data
        $this->addToReferenceCollections($cleanData);

        return $this->collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => $cleanData]
        );
    }

    /**
     * Delete a feature
     * 
     * @param string $id Feature ID
     * @return mixed
     */
    public function deleteFeature($id)
    {
        return $this->collection->deleteOne([
            '_id' => new MongoDB\BSON\ObjectId($id)
        ]);
    }

    /**
     * Search features (used in AJAX search)
     * 
     * @param string $query Search query
     * @return array
     */
    public function searchFeatures($query)
    {
        $regex = new MongoDB\BSON\Regex($query, 'i');

        return $this->collection->find([
            '$or' => [
                ['system_name' => $regex],
                ['module' => $regex],
                ['feature' => $regex],
                ['description' => $regex],
                ['client' => $regex],
                ['source' => $regex],
            ]
        ], [
            'sort' => ['created_at' => -1]
        ])->toArray();
    }

    /**
     * Get distinct values for a field (for dropdowns)
     * 
     * @param string $field Field name
     * @return array
     */
    public function getDistinctValues($field)
    {
        try {
            switch ($field) {
                case 'system_name':
                    return $this->systemNameModel->getDistinctNames();
                case 'module':
                    return $this->moduleModel->getDistinctNames();
                case 'client':
                    return $this->clientModel->getDistinctNames();
                case 'source':
                    return $this->sourceModel->getDistinctNames();
                case 'feature':
                    // Features are usually unique, get from features collection directly
                    return $this->collection->distinct('feature');
                default:
                    return [];
            }
        } catch (Exception $e) {
            error_log("Error getting distinct values for {$field}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get features statistics
     * 
     * @return array
     */
    public function getStatistics()
    {
        try {
            $pipeline = [
                [
                    '$group' => [
                        '_id' => null,
                        'total_features' => ['$sum' => 1],
                        'unique_systems' => ['$addToSet' => '$system_name'],
                        'unique_modules' => ['$addToSet' => '$module'],
                        'unique_clients' => ['$addToSet' => '$client']
                    ]
                ],
                [
                    '$project' => [
                        'total_features' => 1,
                        'unique_systems_count' => ['$size' => '$unique_systems'],
                        'unique_modules_count' => ['$size' => '$unique_modules'],
                        'unique_clients_count' => ['$size' => '$unique_clients']
                    ]
                ]
            ];

            $result = $this->collection->aggregate($pipeline)->toArray();
            return $result[0] ?? [
                'total_features' => 0,
                'unique_systems_count' => 0,
                'unique_modules_count' => 0,
                'unique_clients_count' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return [
                'total_features' => 0,
                'unique_systems_count' => 0,
                'unique_modules_count' => 0,
                'unique_clients_count' => 0
            ];
        }
    }

    /**
     * Get features by system name
     * 
     * @param string $systemName System name
     * @param int $limit Maximum results
     * @return array
     */
    public function getFeaturesBySystem($systemName, $limit = 50)
    {
        return $this->collection->find([
            'system_name' => $systemName
        ], [
            'sort' => ['created_at' => -1],
            'limit' => $limit
        ])->toArray();
    }

    /**
     * Get features by client
     * 
     * @param string $client Client name
     * @param int $limit Maximum results
     * @return array
     */
    public function getFeaturesByClient($client, $limit = 50)
    {
        return $this->collection->find([
            'client' => $client
        ], [
            'sort' => ['created_at' => -1],
            'limit' => $limit
        ])->toArray();
    }

    /**
     * Auto-add values to reference collections
     * 
     * @param array $data Feature data
     * @return void
     */
    private function addToReferenceCollections($data)
    {
        try {
            // Add system name if not exists
            if (!empty($data['system_name'])) {
                $this->systemNameModel->add($data['system_name']);
            }

            // Add module if not exists
            if (!empty($data['module'])) {
                $systemName = $data['system_name'] ?? null;
                $this->moduleModel->addWithSystem($data['module'], $systemName);
            }

            // Add client if not exists
            if (!empty($data['client'])) {
                $this->clientModel->add($data['client']);
            }

            // Add source if not exists
            if (!empty($data['source'])) {
                $this->sourceModel->add($data['source']);
            }
        } catch (Exception $e) {
            // Don't fail the main operation if reference data addition fails
            error_log("Error adding to reference collections: " . $e->getMessage());
        }
    }

    /**
     * Get reference models
     */
    public function getSystemNameModel() { return $this->systemNameModel; }
    public function getModuleModel() { return $this->moduleModel; }
    public function getClientModel() { return $this->clientModel; }
    public function getSourceModel() { return $this->sourceModel; }
}
?>