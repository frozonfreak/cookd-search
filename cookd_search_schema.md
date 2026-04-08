You are a senior backend engineer specializing in Laravel 13 and PostgreSQL.

Design and implement a **production-grade recipe search backend** that supports:

- ingredient normalization
- DSL-based querying (AND / OR / EXCLUDE)
- high-performance PostgreSQL search
- NLP-based query understanding
- dynamic scoring + ranking (intent-aware)
- future AI/semantic extensibility

The system must be modular, scalable, explainable, and optimized for read-heavy workloads.

---

## CORE ARCHITECTURE (IMPORTANT)

The system must follow this flow:

Natural Language Query
→ NLP Pipeline (Laravel Pipeline pattern)
→ QueryContext DTO
→ DSL
→ Dynamic Scoring Engine
→ Ranked Results

This is NOT a simple filter system.

---

## PART 1: DATABASE SCHEMA (POSTGRESQL)

[KEEP EXACTLY AS PROVIDED — DO NOT MODIFY STRUCTURE]

(Use the schema, indexes, constraints exactly as defined in original prompt)

---

## PART 2: INGREDIENT NORMALIZATION LAYER

[KEEP AS PROVIDED]

Additionally:

- Must integrate into NLP pipeline (not standalone)
- Must map aliases BEFORE DSL generation

---

## PART 3: NLP PIPELINE (CRITICAL)

Implement using:

Illuminate\Pipeline\Pipeline

Create:

App\Services\QueryPipeline

### Pipeline Stages (ORDER MATTERS)

1. PreprocessPipe
2. TokenizePipe
3. OperatorClassificationPipe
4. PhraseChunkingPipe
5. EntityExtractionPipe
6. IngredientNormalizationPipe
7. IntentDetectionPipe
8. ScoringProfilePipe <-- NEW
9. DSLBuilderPipe

---

## PART 4: QUERY CONTEXT DTO (EXPANDED)

Create:

App\DTO\QueryContext

Must include:

- rawQuery, cleanedQuery

- tokens, classifiedTokens

- phrases, ngrams

- entities:
    - ingredients:
        - include_all
        - include_any
        - exclude
        - raw_detected

    - time (max, raw)
    - meal_type
    - dish_type
    - dietary

- normalized:
    - ingredients
    - failed_matches

- intent + intentConfidence

- scoring:
    - weights (dynamic)
    - modifiers (strict_mode, boost_exact_match)

- confidence map

- DSL output

- debug logs (per pipe)

DTO must:

- be mutable
- track state across pipeline
- support debugging

---

## PART 5: ENTITY EXTRACTION RULES

Must correctly parse:

"recipes with onion but no garlic"

Output:

- include_all: ["onion"]
- exclude: ["garlic"]

Operators MUST NOT leak into ingredient phrases.

---

## PART 6: INTENT DETECTION

Detect:

1. quick_search
    - keywords: quick, fast, under X mins

2. ingredient_strict
    - pattern: "with X but no Y"

3. exploratory
    - vague queries

Store:

- intent
- intentConfidence (0–1)

---

## PART 7: SCORING PROFILE (NLP + RANKING FUSION)

Create ScoringProfilePipe.

Map intent → weights:

quick_search:

- time weight HIGH
- ingredient weight MEDIUM

ingredient_strict:

- ingredient weight VERY HIGH
- exclude penalty HARD

exploratory:

- popularity HIGH
- recency HIGH

Apply:

context.scoring.weights = dynamic weights × intentConfidence

---

## PART 8: QUERY ENGINE (POSTGRESQL)

Use array operators:

- @> (contains)
- && (overlap)
- NOT @> (exclude)

Use Laravel Query Builder + raw SQL for scoring.

---

## PART 9: DYNAMIC SCORING ENGINE

Score must be computed in SQL.

### Formula:

score =
(ingredient_all_score \* :w_all)

- (ingredient_any_score \* :w_any)
- (time_score \* :w_time)
- (popularity_score \* :w_pop)
- (recency_score \* :w_rec)

* exclude_penalty

Weights come from QueryContext.

---

## PART 10: CONFIDENCE-AWARE ADJUSTMENT

If NLP confidence is low:

- reduce strict filters
- increase popularity weight
- allow broader matches

---

## PART 11: EXPLAINABLE OUTPUT

Return:

{
"score": number,
"breakdown": {
"ingredients": number,
"time": number,
"popularity": number,
"recency": number
},
"intent": "detected_intent"
}

---

## PART 12: LARAVEL IMPLEMENTATION

Generate:

1. Migrations:
    - recipes
    - ingredients
    - ingredient_aliases
    - indexes (separate)

2. Models:
    - Recipe
    - Ingredient
    - IngredientAlias

3. Services:
    - QueryPipeline
    - IngredientNormalizerService
    - RecipeSearchService
    - (optional) ScoringEngine

4. Pipeline Pipes (separate classes)

---

## PART 13: PERFORMANCE REQUIREMENTS

- Use GIN indexes for arrays

- Avoid JSONB in hot filters

- Precompute:
    - cooking_time
    - popularity_score (optional)

- Ensure queries are optimized (EXPLAIN ANALYZE)

---

## PART 14: DEBUGGING + OBSERVABILITY

Each pipeline stage must:

- log input/output
- append to context.debug

System must be fully inspectable.

---

## PART 15: OUTPUT REQUIREMENTS

Return:

1. Laravel migrations
2. Models with casts
3. Full pipeline implementation (all pipes)
4. Services
5. Example query flow
6. Example input → DSL → SQL → output

---

## GOAL

Build a system that behaves like a real search engine:

- understands natural language
- extracts structured intent
- dynamically adapts ranking
- produces explainable results

NOT a static filtering backend.
