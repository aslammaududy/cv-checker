# CV Checker

AI-assisted CV and project evaluator built with Laravel. It extracts text from PDF uploads, generates embeddings with Google Gemini, stores vectors in Milvus, retrieves rubric-aligned context, and produces concise, JSON-structured scoring and feedback.

## Overview

- **Core**: Laravel 12, Sanctum auth, queues for background evaluation
- **AI**: `google-gemini-php/laravel` for embeddings and generation
- **Vector DB**: Milvus via `helgesverre/milvus`
- **PDF**: `spatie/pdf-to-text` to extract content
- **Storage**: SQLite by default (simple local setup)

## Features

- **Upload PDFs**: CV and project as PDF (1MB max each)
- **Embeddings**: Chunked text → Gemini embeddings (3072-d)
- **Vector Search**: Similarity against rubric and job descriptions in Milvus
- **Scoring**: Gemini produces JSON with match rates and actionable feedback
- **Async Processing**: Queue-backed evaluation with status polling

## Architecture

- **`app/Services/EvaluationService`**: End-to-end pipeline (extract → embed → retrieve → score → combine)
- **Console commands**: Seed Milvus with rubric and job descriptions
  - `php artisan rubric:load`
  - `php artisan job-desc:load`
- **HTTP endpoints**: Auth, upload, evaluate, and result retrieval

## Requirements

- PHP 8.2+
- Composer
- Node 18+ (for Vite dev script)
- Milvus (Docker)
- Google Gemini API key

## Quick Start

1) Install dependencies
- `composer install`
- `npm install`

2) Configure environment
- Copy `.env.example` → `.env`
- Set at least these variables:
  - `APP_KEY` (run `php artisan key:generate` if empty)
  - `GEMINI_API_KEY`
  - `MILVUS_HOST`, `MILVUS_PORT`, `MILVUS_DATABASE`, `MILVUS_TOKEN` (optional)
  - `QUEUE_CONNECTION=database` (recommended for background jobs)
  - Database (SQLite recommended for local):
    - `DB_CONNECTION=sqlite`
    - Ensure `database/database.sqlite` exists (create empty file if missing)

3) Run database migrations
- `php artisan migrate`

4) Configure And Start Milvus
```bash
#Download the installation script
curl -sfL https://raw.githubusercontent.com/milvus-io/milvus/master/scripts/standalone_embed.sh -o standalone_embed.sh

#Start the Docker container
bash standalone_embed.sh start
```

5) Seed vector database
- `php artisan rubric:load`
- `php artisan job-desc:load`

6) Run the app (dev mode)
- One-shot dev script (serve, queue listener, logs, Vite):
  - `composer dev`
- Or run individually:
  - `php artisan serve`
  - `php artisan queue:listen --tries=1`

## Environment Variables

- `GEMINI_API_KEY`: Google Gemini API key
- `MILVUS_HOST`: Milvus host (default `localhost`)
- `MILVUS_PORT`: Milvus port (default `19530`)
- `MILVUS_TOKEN`: Milvus token (if enabled)
- `MILVUS_DATABASE`: Milvus database (default `default`)
- `MILVUS_DEFAULT_COLLECTION`: Default collection (not required for this flow)
- `MILVUS_VECTOR_DIMENSION`: Default dimension (note: embeddings here are 3072)

## Milvus Notes

- Collections created/used: `cv`, `project`, `rubric`, `jobdesc`
- Embedding dimension is 3072 for `cv`, `project`, `rubric`, `jobdesc` (Gemini embeddings)
- Ensure Milvus is reachable from the app host and credentials match `.env`

## HTTP API

Base URL: `http://localhost:8000/api`

Auth (Sanctum)
- `POST /api/register` → `{ name, email, password, confirm_password }`
- `POST /api/login` → `{ email, password }` → returns `token`
- Use header: `Authorization: Bearer <token>` for all endpoints below

Upload + Evaluate
- `POST /api/upload`
  - form-data: `cv` (PDF, ≤1MB), `project` (PDF, ≤1MB)
- `POST /api/evaluate`
  - queues an evaluation job, returns `{ id, status: "queued" }`
- `GET /api/result/{id}`
  - returns current status or final JSON result

Examples (cURL)
- Register:
  - `curl -X POST http://localhost:8000/api/register \
     -H "Content-Type: application/json" \
     -d '{"name":"Jane","email":"jane@example.com","password":"secret123","confirm_password":"secret123"}'`
- Login:
  - `curl -X POST http://localhost:8000/api/login \
     -H "Content-Type: application/json" \
     -d '{"email":"jane@example.com","password":"secret123"}'`
- Upload:
  - `curl -X POST http://localhost:8000/api/upload \
     -H "Authorization: Bearer <TOKEN>" \
     -F cv=@/path/to/cv.pdf \
     -F project=@/path/to/project.pdf`
- Evaluate:
  - `curl -X POST http://localhost:8000/api/evaluate \
     -H "Authorization: Bearer <TOKEN>"`
- Result:
  - `curl http://localhost:8000/api/result/<ID> \
     -H "Authorization: Bearer <TOKEN>"`

## Scoring Flow

- **Extraction**: PDF → text via `spatie/pdf-to-text`
- **Chunking**: Simple fixed-size chunking for embeddings
- **Embedding**: Gemini `gemini-embedding-001` → 3072-d vectors
- **Seeding**: `rubric:load` and `job-desc:load` populate Milvus with reference vectors
- **Retrieval**:
  - CV vectors → search similar job descriptions
  - Rubric vectors → retrieve top-k CV and project snippets per category
- **Generation**:
  - Gemini `gemini-2.5-flash` produces CV score and project score JSON
  - A refinement step adjusts project score to a 10-point scale
  - Final JSON merges both into a single response

## Files & Structure

- `app/Services/EvaluationService.php`: Main pipeline and Gemini/Milvus integration
- `app/Console/Commands/LoadRubricScoreCommand.php`: Seeds rubric
- `app/Console/Commands/LoadJobDescCommand.php`: Seeds job descriptions
- `app/Http/Controllers/*`: Auth, upload, evaluate, result endpoints
- `config/milvus.php` and `config/gemini.php`: Configuration

## Troubleshooting

- **Empty PDF text**: Ensure your PDFs are text-based or install `pdftotext` backend for your OS
- **Milvus connection**: Verify Docker container is running and ports are exposed (`19530`, `9091`)
- **Dimension mismatch**: Ensure collections for embeddings use `3072` dim to match Gemini
- **Queue not processing**: Start a worker (`php artisan queue:listen`) and set `QUEUE_CONNECTION`
- **Auth issues**: Include `Authorization: Bearer <token>` on protected routes

## License

MIT
