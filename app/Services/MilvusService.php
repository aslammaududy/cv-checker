<?php

namespace App\Services;

use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Support\Facades\Log;

class MilvusService
{
    /**
     * Test the connection to Milvus
     */
    public function testConnection(): bool
    {
        try {
            $collections = Milvus::collections()->list();
            Log::info('Milvus connection successful', ['collections' => $collections]);
            return true;
        } catch (\Exception $e) {
            Log::error('Milvus connection failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create a collection for storing vectors
     */
    public function createCollection(string $collectionName, int $dimension = 768): array
    {
        try {
            $schema = [
                'collectionName' => $collectionName,
                'description' => 'Collection for storing document embeddings',
                'fields' => [
                    [
                        'fieldName' => 'id',
                        'dataType' => 'Int64',
                        'isPrimary' => true,
                        'autoID' => true
                    ],
                    [
                        'fieldName' => 'vector',
                        'dataType' => 'FloatVector',
                        'elementTypeParams' => [
                            'dim' => $dimension
                        ]
                    ],
                    [
                        'fieldName' => 'text',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => [
                            'max_length' => 65535
                        ]
                    ]
                ]
            ];

            $response = Milvus::collections()->create($schema);
            $result = $response->json();
            Log::info('Collection created successfully', ['collection' => $collectionName]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to create collection', ['collection' => $collectionName, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Insert vectors into a collection
     */
    public function insertVectors(string $collectionName, array $data): array
    {
        try {
            $response = Milvus::vector()->insert(
                collectionName: $collectionName,
                data: $data
            );

            $result = $response->json();

            Log::info('Vectors inserted successfully', [
                'collection' => $collectionName,
                'count' => count($data)
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to insert vectors', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Search for similar vectors
     */
    public function searchSimilar(string $collectionName, array $queryVector, int $limit = 10): array
    {
        try {
            $response = Milvus::vector()->search(
                collectionName: $collectionName,
                vector: [$queryVector],
                annsField: 'vector',
                limit: $limit,
                outputFields: ['text']
            );

            $result = $response->json();

            Log::info('Vector search completed', [
                'collection' => $collectionName,
                'limit' => $limit
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to search vectors', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * List all collections
     */
    public function listCollections(): array
    {
        try {
            $response = Milvus::collections()->list();
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to list collections', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Drop a collection
     */
    public function dropCollection(string $collectionName): array
    {
        try {
            $response = Milvus::collections()->drop($collectionName);
            $result = $response->json();

            Log::info('Collection dropped successfully', ['collection' => $collectionName]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to drop collection', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
