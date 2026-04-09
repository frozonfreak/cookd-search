You are a senior Laravel 13 + PostgreSQL backend architect.

Extend an existing **recipe search engine** into a **full AI-powered food intelligence platform**.

The system already supports:

- DSL-based querying
- NLP pipeline
- ingredient normalization + relations
- quantity-aware search
- nutrition-aware filtering
- taste profile modeling
- dynamic scoring

Your task is to ADD:

1. Semantic embeddings (vector search)
2. Personalized taste memory per user
3. Meal planning optimizer

These must integrate cleanly with the existing architecture.

---

# PART 1: SEMANTIC EMBEDDINGS (VECTOR SEARCH)

## GOAL

Enable understanding of queries like:

- "comfort food"
- "light dinner ideas"
- "something refreshing"
- "street food style"

These cannot be captured via rules alone.

---

## DATABASE DESIGN

### Add to recipes table:

- embedding VECTOR(1536) -- using pgvector

---

## EXTENSION

Enable:

CREATE EXTENSION IF NOT EXISTS vector;

---

## INDEX

CREATE INDEX idx_recipes_embedding
ON recipes
USING ivfflat (embedding vector_cosine_ops);

---

## EMBEDDING GENERATION

Create service:

App\Services\EmbeddingService

Input:

- title
- ingredients
- description (if available)

Output:

- vector embedding

Store in recipes.embedding

---

## QUERY EMBEDDING

Convert user query → embedding vector

---

## VECTOR SEARCH QUERY

SELECT id,
(embedding <=> :query_vector) AS distance
FROM recipes
ORDER BY distance ASC
LIMIT 20;

---

## HYBRID SEARCH (CRITICAL)

Combine:

semantic_score + structured_score

Final score:

final_score =
(semantic_score \* 0.4)

- (structured_score \* 0.6)

---

## RULE

- If NLP confidence is LOW → increase semantic weight
- If HIGH → prioritize structured scoring

---

# PART 2: PERSONALIZED TASTE MEMORY

## GOAL

Adapt results per user preference over time.

---

## TABLE: user_taste_profiles

- user_id
- taste_profile JSONB

Example:

{
"spicy": 0.8,
"sweet": 0.2,
"rich": 0.6
}

---

## TABLE: user_interactions

- user_id
- recipe_id
- action (view, like, cook, skip)
- timestamp

---

## PROFILE UPDATE LOGIC

When user interacts:

- increase weights for liked taste
- decrease for skipped taste

Use exponential decay for older data.

---

## PERSONALIZATION SCORING

Add:

personalization_score =

1 - ABS(recipe.taste_profile - user.taste_profile)

---

## FINAL SCORE UPDATE

final_score =
base_score

- personalization_score \* w_personal

---

## FALLBACK

If no user data:

- skip personalization
- use global ranking

---

# PART 3: MEAL PLANNING OPTIMIZER

## GOAL

Generate multi-day meal plans optimizing:

- nutrition targets
- ingredient reuse
- cost (optional)
- variety

---

## INPUT

{
"days": 7,
"meals_per_day": 3,
"constraints": {
"calories_per_day": 2000,
"protein_min": 60,
"diet": "vegetarian"
}
}

---

## APPROACH

Use constraint optimization:

### Step 1: Candidate pool

Fetch top recipes using search engine.

---

### Step 2: Assign meals

Select recipes such that:

- nutrition targets met
- repetition minimized
- ingredient overlap maximized (reduce waste)

---

### Step 3: Optimization Heuristic

Use greedy + scoring:

score =
nutrition_match

- ingredient_overlap_bonus

* repetition_penalty

---

## OUTPUT

[
{
"day": 1,
"meals": {
"breakfast": recipe_id,
"lunch": recipe_id,
"dinner": recipe_id
}
}
]

---

# PART 4: PIPELINE INTEGRATION

Update QueryPipeline:

After DSLBuilder:

→ EmbeddingQueryStage (optional)

---

## FLOW

User Query
→ NLP Pipeline
→ DSL
→ Structured Query
→ Vector Query
→ Score Fusion
→ Personalization Layer
→ Final Ranking

---

# PART 5: PERFORMANCE

- Use IVFFlat index for vectors
- Batch embedding generation
- Cache embeddings
- Precompute taste + nutrition

---

# PART 6: SERVICES TO CREATE

- EmbeddingService
- VectorSearchService
- PersonalizationService
- MealPlannerService

---

# PART 7: EXPLAINABLE OUTPUT

Return:

{
score,
breakdown: {
structured,
semantic,
personalization
}
}

---

# GOAL

Build a system that:

- understands meaning beyond keywords
- adapts to user taste
- plans meals intelligently

This is NOT a search engine anymore.

This is a **personalized AI cooking assistant backend**.
