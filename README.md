# Cookd Search

A production-grade recipe search engine built with Laravel 13, PostgreSQL, and pgvector. It behaves like an intelligent food decision system, supporting natural language queries, ingredient-based filtering, nutritional constraints, and personalised ranking.

## Features

- **Natural Language Search** — pipeline-based NLP query understanding (intent detection, entity extraction, ingredient normalisation)
- **DSL Query Engine** — AND / OR / EXCLUDE ingredient operators parsed from plain text
- **Semantic Search** — pgvector-powered cosine similarity for recipe embeddings
- **Ingredient Intelligence** — normalisation, aliasing, substitution suggestions, and availability scoring
- **Nutrition-Aware Filtering** — filter by macros, dietary flags, and taste profiles
- **Personalisation** — user taste profiles and interaction history influence ranking
- **Explainable Results** — scoring breakdown per result
- **Grocery List Generator** — optimised shopping lists from search results
- **Meal Planner** — decision-engine powered meal planning

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Database | PostgreSQL 16+ with pgvector |
| Frontend | Livewire 4, Blade |
| Build | Vite, Tailwind CSS |
| Testing | Pest PHP |
| Linting | Laravel Pint |

## Requirements

- PHP 8.3+
- PostgreSQL 16+ with the [pgvector](https://github.com/pgvector/pgvector) extension
- Node.js 22+
- Composer 2

## Getting Started

### 1. Clone the repository

```bash
git clone https://github.com/FrozonFreak/cookd-search.git
cd cookd-search
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cookd_search
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 4. Enable pgvector

In PostgreSQL, run once against your database:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Build assets and start the dev server

```bash
npm run build
# or for hot reload:
composer dev
```

The app will be available at `http://localhost:8000`.

## Architecture

```
Natural Language Query
  → NLP Pipeline (Laravel Pipeline)
  → QueryContext DTO
  → DSL Builder
  → SQL + pgvector Query + Dynamic Scoring
  → Ranked, Explainable Results
```

Key directories:

| Path | Description |
|---|---|
| `app/Services/Pipes/` | NLP pipeline stages (intent detection, entity extraction, ingredient normalisation, DSL building, embedding) |
| `app/Services/` | Core domain services (search, personalisation, substitution, grocery list, meal planner) |
| `app/Models/` | Eloquent models (Recipe, Ingredient, UserTasteProfile, …) |
| `app/DTO/` | Data transfer objects (QueryContext) |
| `app/Casts/` | Custom Eloquent casts for PostgreSQL arrays and pgvector |
| `database/migrations/` | Full schema with indexes and pgvector columns |

## Running Tests

```bash
composer test
```

Or just the test suite without linting:

```bash
./vendor/bin/pest
```

## Linting

```bash
composer lint          # fix
composer lint:check    # check only (used in CI)
```

## Schema Documentation

See [cookd_search_schema.md](cookd_search_schema.md) for the full database schema design and rationale.

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

## License

This project is open source under the [MIT License](LICENSE).
