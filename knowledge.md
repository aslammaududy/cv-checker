# CV Checker - Milvus Vector Database Integration

## Overview
This Laravel application integrates with Milvus vector database using the `HelgeSverre/milvus` package for vector similarity search capabilities.

## Package Information
- **Package**: `helgesverre/milvus` v0.1.0
- **Dependencies**: Built on Saloon HTTP client and Spatie Laravel Data
- **Milvus API Version**: v2.3.3

## Configuration

### Environment Variables
Add these variables to your `.env` file:

```
MILVUS_HOST=localhost
MILVUS_PORT=19530
MILVUS_TOKEN=
MILVUS_DATABASE=default
MILVUS_DEFAULT_COLLECTION=documents
MILVUS_VECTOR_DIMENSION=768
```

### Docker Milvus Setup
For local development with Docker:

```bash
# Default Milvus port is 19530
# Make sure your Docker container exposes this port
# Example docker run command:
docker run -d --name milvus-standalone \
  -p 19530:19530 \
  -p 9091:9091 \
  milvusdb/milvus:latest
```

## Files Created

1. **`config/milvus.php`** - Configuration file for Milvus connection settings
2. **`app/Services/MilvusService.php`** - Service class handling all Milvus operations
3. **`app/Http/Controllers/MilvusController.php`** - API controller for Milvus endpoints

## Available API Endpoints

All endpoints are prefixed with `/api/milvus`:

- `GET /test-connection` - Test Milvus connection
- `GET /collections` - List all collections
- `POST /collections` - Create new collection
- `DELETE /collections` - Drop collection
- `POST /vectors/insert-sample` - Insert sample vectors
- `POST /vectors/search` - Search similar vectors

## Usage Examples

### Test Connection
```bash
curl http://localhost:8000/api/milvus/test-connection
```

### Create Collection
```bash
curl -X POST http://localhost:8000/api/milvus/collections \
  -H "Content-Type: application/json" \
  -d '{"name":"documents","dimension":768}'
```

### Insert Sample Data
```bash
curl -X POST http://localhost:8000/api/milvus/vectors/insert-sample \
  -H "Content-Type: application/json" \
  -d '{"collection":"documents"}'
```

### Search Vectors
```bash
curl -X POST http://localhost:8000/api/milvus/vectors/search \
  -H "Content-Type: application/json" \
  -d '{"collection":"documents","limit":5}'
```

## Service Integration

The `MilvusService` is registered as a singleton in `AppServiceProvider` and can be injected into controllers or other services:

```php
use App\Services\MilvusService;

public function __construct(MilvusService $milvusService)
{
    $this->milvusService = $milvusService;
}
```

## Vector Operations

- **Collection Schema**: Includes `id` (Int64, primary, auto-generated), `vector` (FloatVector), and `text` (VarChar)
- **Default Dimension**: 768 (configurable)
- **Search**: Uses approximate nearest neighbor search (ANNS)
- **Logging**: All operations are logged for debugging

## Correct API Methods

The HelgeSverre/milvus package uses a fluent API structure:

- `Milvus::collections()->list()` - List collections
- `Milvus::collections()->create($schema)` - Create collection
- `Milvus::collections()->drop($name)` - Drop collection
- `Milvus::vector()->insert(collectionName: $name, data: $data)` - Insert vectors
- `Milvus::vector()->search(collectionName: $name, data: [$vector], ...)` - Search vectors

All methods return Response objects, use `->json()` to get array data.

## Existing Dependencies

The project already includes `pgvector/pgvector` for PostgreSQL vector operations, making it suitable for hybrid vector storage approaches.

## Next Steps

1. Configure your `.env` file with Milvus connection details
2. Start your Milvus Docker container
3. Test the connection using the API endpoint
4. Create collections and start inserting your vector data
5. Integrate with your CV processing pipeline for semantic search