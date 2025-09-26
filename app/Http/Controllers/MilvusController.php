<?php

namespace App\Http\Controllers;

use App\Services\MilvusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MilvusController extends Controller
{
    public function __construct(
        protected MilvusService $milvusService
    ) {}

    /**
     * Test Milvus connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $isConnected = $this->milvusService->testConnection();
            
            return response()->json([
                'success' => true,
                'connected' => $isConnected,
                'message' => $isConnected ? 'Milvus connection successful' : 'Milvus connection failed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all collections
     */
    public function listCollections(): JsonResponse
    {
        try {
            $collections = $this->milvusService->listCollections();
            
            return response()->json([
                'success' => true,
                'collections' => $collections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list collections: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new collection
     */
    public function createCollection(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'dimension' => 'integer|min:1|max:32768'
        ]);

        try {
            $result = $this->milvusService->createCollection(
                $request->input('name'),
                $request->input('dimension', 768)
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Collection created successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create collection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Insert sample vectors
     */
    public function insertSampleVectors(Request $request): JsonResponse
    {
        $request->validate([
            'collection' => 'required|string|max:255'
        ]);

        try {
            // Sample data with random vectors
            $sampleData = [
                [
                    'vector' => array_map(fn() => (float) rand(-100, 100) / 100, range(1, 768)),
                    'text' => 'Sample document 1: This is a test document for vector similarity search.'
                ],
                [
                    'vector' => array_map(fn() => (float) rand(-100, 100) / 100, range(1, 768)),
                    'text' => 'Sample document 2: Another example document with different content.'
                ],
                [
                    'vector' => array_map(fn() => (float) rand(-100, 100) / 100, range(1, 768)),
                    'text' => 'Sample document 3: A third document to demonstrate vector search capabilities.'
                ]
            ];

            $result = $this->milvusService->insertVectors(
                $request->input('collection'),
                $sampleData
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Sample vectors inserted successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to insert vectors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for similar vectors
     */
    public function searchSimilar(Request $request): JsonResponse
    {
        $request->validate([
            'collection' => 'required|string|max:255',
            'limit' => 'integer|min:1|max:100'
        ]);

        try {
            // Generate a random query vector
            $queryVector = array_map(fn() => (float) rand(-100, 100) / 100, range(1, 768));
            
            $results = $this->milvusService->searchSimilar(
                $request->input('collection'),
                $queryVector,
                $request->input('limit', 5)
            );
            
            return response()->json([
                'success' => true,
                'results' => $results,
                'query_vector_sample' => array_slice($queryVector, 0, 5) // Show first 5 elements
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search vectors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Drop a collection
     */
    public function dropCollection(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        try {
            $result = $this->milvusService->dropCollection($request->input('name'));
            
            return response()->json([
                'success' => true,
                'message' => 'Collection dropped successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to drop collection: ' . $e->getMessage()
            ], 500);
        }
    }
}