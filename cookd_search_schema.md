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

# PART 15: GROCERY LIST OPTIMIZER

## Goal

Generate a smart grocery list from selected recipes or meal plans.

The optimizer must account for:

- total required ingredients
- pantry items already available
- ingredient reuse across recipes
- cost
- availability
- substitutions if unavailable

## Tables

### user_pantry_items

- id
- user_id
- ingredient_id
- quantity_value
- unit
- expires_at nullable
- created_at
- updated_at

### ingredient_prices

- id
- ingredient_id
- location nullable
- vendor nullable
- price_per_unit
- unit
- availability_status: available / limited / unavailable
- updated_at

### grocery_lists

- id
- user_id
- meal_plan_id nullable
- status: draft / finalized / purchased
- estimated_total_cost
- created_at
- updated_at

### grocery_list_items

- id
- grocery_list_id
- ingredient_id
- required_quantity
- pantry_quantity_used
- quantity_to_buy
- unit
- estimated_cost
- substitution_used nullable
- notes nullable

## Logic

1. Collect all ingredients from selected recipes.
2. Merge duplicate ingredients.
3. Check pantry availability.
4. Subtract pantry quantities.
5. Check price + availability.
6. If unavailable, call substitution engine.
7. Generate final grocery list.
8. Prefer ingredients reused across multiple recipes.
9. Minimize waste and cost.

---

# PART 16: REAL-TIME SUBSTITUTION ENGINE

## Goal

When a recipe ingredient is missing or unavailable, suggest safe alternatives.

Important rule:

Similar does NOT always mean substitutable.

Use ingredient_relations table:

- substitute: strong replacement
- weak_substitute: possible, but lower confidence
- similar: related, but not necessarily usable

## Substitution rules

For missing ingredient:

1. Prefer exact ingredient if available.
2. Else use strong substitute.
3. Else use weak substitute with warning.
4. Never use "similar" as an automatic substitute.
5. Consider dish_type and taste impact.

## Example

Onion:

- shallot → strong substitute
- spring onion → weak substitute
- garlic → similar, not substitute

## Output

Return:

{
"missing": "onion",
"suggestions": [
{
"ingredient": "shallot",
"relation": "substitute",
"confidence": 0.9,
"taste_impact": "mildly sweeter",
"quantity_adjustment": 0.8
}
]
}

## Services

Create:

- SubstitutionService
- IngredientAvailabilityService

---

# PART 17: REINFORCEMENT LEARNING FOR RANKING

## Goal

Improve ranking over time based on user behavior.

Do NOT implement complex ML initially.

Implement a lightweight feedback-based ranking system.

## User actions

Track:

- view
- save
- cook
- like
- dislike
- skip
- search_click

## Table: recipe_ranking_feedback

- id
- user_id nullable
- recipe_id
- query_text nullable
- query_dsl JSONB nullable
- action
- reward_score
- created_at

## Reward scores

Use simple rewards:

- view: +0.1
- click: +0.3
- save: +0.6
- cook: +1.0
- like: +1.0
- skip: -0.3
- dislike: -1.0

## Ranking adjustment

Calculate feedback_score:

feedback_score =
weighted_sum(user_actions with time decay)

Use exponential decay so old actions matter less.

## Final ranking formula

final_score =
structured_score

- semantic_score
- personalization_score
- feedback_score

## Exploration vs exploitation

To avoid always showing the same recipes:

- 90% exploit best-ranked recipes
- 10% explore newer or lower-confidence recipes

## Services

Create:

- RankingFeedbackService
- ReinforcementRankingService

---

# PART 18: FINAL INTELLIGENCE FLOW

Final system flow:

User Query
→ NLP Pipeline
→ DSL
→ Structured Search
→ Semantic Search
→ Personalization
→ Reinforcement Ranking
→ Meal Planning
→ Grocery Optimization
→ Substitution Suggestions

---

# PART 19: EXPLAINABLE OUTPUT

Every result must explain why it was selected.

Example:

{
"recipe_id": 123,
"score": 8.7,
"breakdown": {
"structured": 3.1,
"semantic": 1.8,
"taste_match": 1.2,
"nutrition": 1.0,
"personalization": 0.9,
"feedback": 0.7
},
"explanation": [
"Matches requested ingredient: onion",
"Low oil requirement satisfied",
"Fits user's spicy preference",
"Frequently saved after similar searches"
]
}

---

# PART 20: GOAL

Extend the system from recipe search into a full cooking decision engine:

- find recipes
- adapt to user taste
- plan meals
- build grocery lists
- suggest substitutions
- improve ranking from behavior

Keep the implementation modular. Do not overfit everything into one service.

# PART 21: UI LAYER (FRONTEND)

You must now build a **frontend UI layer** on top of the backend system.

IMPORTANT:

- Do NOT remove or change backend architecture
- UI must NOT expose DSL, embeddings, or scoring internals
- UI must stay simple despite backend complexity

---

# CORE UI PRINCIPLE

The UI must feel like:

Search → Choose → Plan → Shop → Cook → Learn

NOT:

A complex system or ERP-like interface.

---

# MAIN USER FLOW

1. User searches naturally
2. Browses recipes
3. Selects recipes
4. Generates grocery list
5. Substitutes ingredients if needed
6. Cooks
7. System learns preferences

---

# PART 22: SCREEN — RECIPE SEARCH

## Purpose

Natural language discovery.

## UI Elements

- Search bar
- Suggestion chips:
    - Quick
    - Breakfast
    - Low oil
    - High protein
    - Spicy
    - No garlic

## Results

Recipe cards:

- title
- image placeholder
- cooking time
- tags
- Save button
- Add to Plan button

## UX Rule

Show:

"Good match because it is quick and low oil"

DO NOT show score.

---

# PART 23: SCREEN — RECIPE DETAIL

## Sections

- title
- cooking time
- meal type
- tags
- ingredients list
- instructions
- nutrition summary
- taste badges

## Ingredient Row

Onion — 1 medium  
[Substitute]

---

# PART 24: SUBSTITUTION MODAL

## Trigger

Click Substitute

## UI

Substitute Onion

Options:

1. Shallots (Best match)
    - slightly sweeter
    - use 0.8x

2. Spring onion (Possible)
    - milder
    - use 1.2x

## Rules

- No auto replacement
- User must choose
- Clearly mark weak substitutes
- Similar ≠ substitute

---

# PART 25: SCREEN — MEAL PLAN

## Purpose

Select recipes before grocery generation.

## UI

- selected recipe list
- remove option
- optional meal labels

## Actions

- Generate Grocery List
- Clear Plan

---

# PART 26: SCREEN — GROCERY LIST

## Layout

Grouped items:

- Vegetables
- Spices
- Dairy
- Pantry

## Item

Onion  
Required: 4  
Have: 1  
Buy: 3  
Cost: ₹30

## Actions

- Mark as “I have this”
- Substitute
- Remove

## Summary

- total cost
- item count
- savings

---

# PART 27: SMART BEHAVIOR

### Ingredient Merge

No duplicates.

### Pantry Awareness

Toggle availability.

### Substitution Integration

Available directly in grocery screen.

---

# PART 28: FEEDBACK SYSTEM

Minimal UI:

- Save ❤️
- Like 👍
- Not Interested 👎

Implicit tracking:

- clicks
- time spent
- add to plan
- cooking completion

---

# PART 29: OPTIONAL EXPLANATION UI

"Why this recipe?"

Show:

- matches preference
- matches nutrition
- similar to saved

---

# PART 30: PERSONALIZATION (UI SIDE)

Do NOT expose settings initially.

Instead:

- reorder results silently
- improve over time

---

# PART 31: COMPONENTS

- SearchBar
- FilterChips
- RecipeCard
- RecipeList
- IngredientRow
- SubstituteModal
- MealPlanList
- GroceryListItem
- NutritionBadges
- TasteBadges
- FeedbackButtons
- ExplanationPanel

---

# PART 32: API INTEGRATION

Use backend APIs as defined.

Do NOT modify backend contract.

---

# PART 33: STATE MANAGEMENT

Maintain:

- selected recipes
- meal plan
- grocery list
- feedback state

Keep simple.

---

# PART 34: UX RULES

1. No DSL exposure
2. No AI technical terms
3. No complex filters initially
4. No forced planning
5. Substitution must be explicit
6. Grocery list must be merged
7. Feedback must be minimal
8. Mobile-first
9. Clarity over complexity

---

# PART 35: MVP SCOPE

Build ONLY:

- Search screen
- Recipe detail screen
- Substitution modal
- Meal plan screen
- Grocery list screen
- Feedback interactions

DO NOT build:

- calendar
- ordering
- payments
- delivery
- social features

---

# PART 36: OUTPUT REQUIREMENTS

Generate:

1. UI layout
2. components
3. state management
4. API integration
5. mock data
6. responsive design

---

# FINAL GOAL

Build a system that:

- understands natural language
- models ingredients, nutrition, and taste
- adapts ranking dynamically
- produces explainable results

A **food intelligence system with a simple UI**

Backend:

- NLP
- ranking
- optimization
- learning

Frontend:

- simple
- intuitive
- action-driven

User should feel:

"I searched, picked recipes, got my shopping list, and started cooking."

NOT:

"I used a complex AI system."
