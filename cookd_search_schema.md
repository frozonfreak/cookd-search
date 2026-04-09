You are a senior Laravel 13 + PostgreSQL backend architect.

Design and implement a **production-grade recipe search engine** that behaves like an intelligent food decision system.

The system must support:

- ingredient normalization + relationships
- DSL-based querying (AND / OR / EXCLUDE)
- quantity-aware ingredient matching
- nutrition-aware filtering
- taste profile modeling
- NLP-based query understanding (pipeline architecture)
- dynamic scoring (intent-aware ranking)
- explainable results

---

# CORE ARCHITECTURE

Natural Language Query
→ NLP Pipeline (Laravel Pipeline)
→ QueryContext DTO
→ DSL
→ SQL Query + Dynamic Scoring
→ Ranked Results

---

# PART 1: DATABASE SCHEMA (POSTGRESQL)

Follow strict best practices:

- Use **columns for frequently queried fields**
- Use **JSONB only for flexible fields**
- Use **GIN indexes for arrays/JSONB**
- Avoid putting core filters inside JSONB

---

## TABLE: recipes

Columns:

- id (bigint PK)
- title (text)
- normalized_title (text, indexed)

### Structured fields (HOT PATH)

- dish_type (text, indexed)
- cooking_time (int, indexed)
- dietary (text nullable)

### Nutrition (IMPORTANT)

- calories FLOAT
- fat FLOAT
- protein FLOAT
- sodium FLOAT

### Ingredient arrays

- ingredients TEXT[]
- ingredient_ids INT[]

### Flexible fields

- meal_type JSONB
- cuisine JSONB
- nutrition JSONB
- taste_profile JSONB

### Timestamps

- created_at
- updated_at

---

## TABLE: recipe_ingredients

- recipe_id
- ingredient_id

### Quantity (CRITICAL)

- quantity_value FLOAT (0–1 normalized)
- quantity_text TEXT
- unit TEXT

---

## TABLE: ingredients

- id
- name (canonical)
- group_id

---

## TABLE: ingredient_aliases

- id
- alias
- ingredient_id

---

## TABLE: ingredient_relations

- ingredient_id
- related_ingredient_id
- relation_type (similar | substitute | weak_substitute)
- strength FLOAT

---

## INDEXING

- GIN on ingredients arrays
- GIN on JSONB fields
- B-tree on cooking_time, dish_type, nutrition columns

---

# PART 2: NLP PIPELINE

Use Laravel Pipeline:

Stages:

1. PreprocessPipe
2. TokenizePipe
3. OperatorClassificationPipe
4. PhraseChunkingPipe
5. EntityExtractionPipe
6. IngredientResolutionPipe
7. IntentDetectionPipe
8. ScoringProfilePipe
9. DSLBuilderPipe

---

# PART 3: QUERY CONTEXT DTO

Must store:

- tokens, classified tokens

- phrases, ngrams

- ingredients:
    - include_all
    - include_any
    - exclude
    - quantity constraints

- ingredient_relations:
    - exact
    - strong substitutes
    - weak substitutes

- nutrition constraints

- taste preferences

- intent + confidence

- scoring weights

- DSL output

- debug logs

---

# PART 4: INGREDIENT RESOLUTION

Must support:

### Alias normalization

"curd" → "yogurt"

### Relationship handling

- similar (broad match)
- substitute (strong)
- weak substitute (low confidence)

### Rules:

INCLUDE:

- allow substitutes
- allow weak substitutes (lower score)

EXCLUDE:

- exclude exact + strong substitutes
- DO NOT exclude weak substitutes

---

# PART 5: QUANTITY-AWARE SEARCH

Support phrases:

- "no onion" → 0.0
- "little onion" → max 0.3
- "extra onion" → min 0.7

Store in DSL:

{
ingredient: "onion",
quantity: { min: X, max: Y, target: Z }
}

---

# PART 6: NUTRITION-AWARE SEARCH

Parse:

- "low oil" → fat <= threshold
- "low sodium"
- "high protein"

DSL:

{
nutrition: {
fat: { max: 10 },
protein: { min: 20 }
}
}

Use columns for filtering.

---

# PART 7: TASTE PROFILE MODELING

Each recipe has:

taste_profile JSONB:

{
spicy: 0.8,
tangy: 0.5,
sweet: 0.2,
rich: 0.7
}

NLP extracts:

"spicy tangy curry" →

{
spicy: 0.9,
tangy: 0.6
}

---

# PART 8: QUERY ENGINE

Use PostgreSQL:

- array operators (@>, &&)
- EXISTS for ingredient checks
- numeric filters for nutrition
- JSONB for taste

---

# PART 9: SCORING ENGINE (FUSION)

Score =

ingredient_score

- quantity_score
- nutrition_score
- taste_score
- time_score
- popularity_score
- recency_score

---

## Quantity scoring

1 - ABS(actual - target)

---

## Taste scoring

1 - ABS(actual - requested)

---

## Nutrition scoring

penalize exceeding limits

---

## Ingredient scoring

- exact > substitute > weak substitute

---

# PART 10: INTENT-AWARE SCORING

Detect:

- quick_search → boost time
- ingredient_strict → boost ingredient match
- healthy → boost nutrition
- flavor-focused → boost taste

Apply dynamic weights.

---

# PART 11: CONFIDENCE HANDLING

If low confidence:

- relax filters
- boost popularity
- widen matches

---

# PART 12: EXPLAINABLE OUTPUT

Return:

{
score,
breakdown: {
ingredients,
quantity,
nutrition,
taste,
time
},
intent
}

---

# PART 13: LARAVEL IMPLEMENTATION

Generate:

- migrations
- models
- pipeline pipes
- QueryPipeline service
- RecipeSearchService
- IngredientResolutionService

---

# PART 14: PERFORMANCE

- Use GIN indexes for arrays/JSONB
- Use columns for hot filters
- Avoid JSONB for frequent WHERE clauses
- Ensure EXPLAIN ANALYZE passes

---

# GOAL

Build a system that:

- understands natural language
- models ingredients, nutrition, and taste
- adapts ranking dynamically
- produces explainable results

This is NOT a CRUD filter system.

This is a **food intelligence engine**.
