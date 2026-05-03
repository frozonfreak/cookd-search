<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rasa</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|instrument-sans:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fff7ed] text-[#3f2415]">

{{-- ─── Top Nav ─────────────────────────────────────────────────── --}}
<nav class="sticky top-0 z-40 border-b border-[#f1c39a]/50 bg-[#fff7ed]/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3">
        <span class="font-['Space_Grotesk'] text-xl font-bold text-[#4a2b1d]">Rasa</span>
        <div class="flex gap-1">
            <button onclick="showScreen('search')" id="tab-search"
                class="nav-tab rounded-full px-4 py-2 text-sm font-medium transition">
                Search
            </button>
            <button onclick="showScreen('meal-plan')" id="tab-meal-plan"
                class="nav-tab relative rounded-full px-4 py-2 text-sm font-medium transition">
                Meal Plan
                <span id="plan-badge"
                    class="hidden absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-[#f77737] text-[10px] font-bold text-white">
                    0
                </span>
            </button>
            <button onclick="showScreen('grocery')" id="tab-grocery"
                class="nav-tab rounded-full px-4 py-2 text-sm font-medium transition">
                Grocery List
            </button>
        </div>
    </div>
</nav>

<div class="relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(252,175,69,0.34),_transparent_32%),radial-gradient(circle_at_82%_18%,_rgba(247,119,55,0.26),_transparent_24%),linear-gradient(180deg,_#fff7ed_0%,_#ffe4c7_48%,_#ffd2ad_100%)]"></div>
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#f77737]/50 to-transparent"></div>

    <main class="relative mx-auto max-w-7xl px-6 py-10 sm:px-10 lg:px-16">

        {{-- ═══════════════════════════════════════════════════════════
             SCREEN: SEARCH
        ════════════════════════════════════════════════════════════ --}}
        <div id="screen-search">
            <section class="grid gap-10">
                <div class="mx-auto w-full max-w-4xl">

                    <form method="GET" action="{{ route('home') }}" class="mt-4">
                        <label for="q" class="sr-only">Search recipes</label>
                        <div class="rounded-[2rem] border border-[#f4b27f] bg-white/75 p-2 shadow-2xl shadow-[#f77737]/15 backdrop-blur">
                            <div class="flex flex-col gap-3 rounded-[1.6rem] border border-[#ffe7d1] bg-[#fffaf5]/90 p-3 sm:flex-row sm:items-center">
                                <input id="q" name="q" type="text"
                                    value="{{ $query }}"
                                    placeholder="chutney without coconut"
                                    class="w-full border-0 bg-transparent px-4 py-4 text-lg text-[#4a2b1d] outline-none placeholder:text-[#b48263]">
                                <button type="submit"
                                    class="inline-flex items-center justify-center rounded-[1.2rem] bg-[#f77737] px-6 py-4 font-medium text-white transition hover:bg-[#f56040]">
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Suggestion chips --}}
                    <div class="mt-5 flex flex-wrap gap-2 text-sm text-[#7b4a34]">
                        @foreach ([
                            ['Quick', 'quick breakfast under 15 min'],
                            ['Breakfast', 'healthy breakfast high protein'],
                            ['Low oil', 'low oil dinner vegetarian'],
                            ['High protein', 'high protein lunch with chicken or paneer'],
                            ['Spicy', 'spicy tangy curry'],
                            ['No garlic', 'comfort food no garlic no onion'],
                        ] as [$label, $q])
                            <a href="{{ route('home', ['q' => $q]) }}"
                               class="rounded-full border border-[#f3c49d] bg-white/70 px-4 py-2 transition hover:border-[#f77737]/50 hover:bg-[#fff1e4] hover:text-[#9b3d16]">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            @if ($results->isNotEmpty())
            <section class="mt-12 mx-auto w-full max-w-4xl">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Results</p>
                        <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-[#5a2414]">
                            {{ $results->count() }} matches for "{{ $query }}"
                        </h2>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($results as $recipe)
                        @php
                            $cardData = array_merge($recipe->raw_json ?? [], [
                                '_db_id'       => $recipe->id,
                                '_taste'       => $recipe->taste_profile,
                                '_dietary'     => $recipe->dietary,
                                '_cook_time'   => $recipe->cooking_time,
                                '_ingredients' => $recipe->ingredients ?? [],
                            ]);
                            $cuisineTags = array_slice((array) ($recipe->raw_json['cuisines_name'] ?? []), 0, 1);
                            $explanations = [];
                            if ($recipe->cooking_time && $recipe->cooking_time <= 20) $explanations[] = 'Quick (' . $recipe->cooking_time . ' min)';
                            if ($recipe->dietary) $explanations[] = ucfirst(str_replace('_', ' ', $recipe->dietary));
                            $tp = $recipe->taste_profile ?? [];
                            if (($tp['spicy'] ?? 0) >= 0.65) $explanations[] = 'Spicy';
                            elseif (($tp['sweet'] ?? 0) >= 0.65) $explanations[] = 'Sweet';
                            elseif (($tp['tangy'] ?? 0) >= 0.65) $explanations[] = 'Tangy';
                            if ($recipe->protein && $recipe->protein >= 20) $explanations[] = 'High protein';
                            elseif ($recipe->fat !== null && $recipe->fat <= 8) $explanations[] = 'Low oil';
                        @endphp

                        <div class="group flex flex-col overflow-hidden rounded-[1.75rem] border border-[#f1c39a] bg-white shadow-sm transition hover:-translate-y-1 hover:border-[#f77737]/40 hover:shadow-md hover:shadow-[#f77737]/10">

                            <div class="flex flex-1 flex-col p-4">

                                {{-- Title row: content + save side by side --}}
                                <div class="flex items-start gap-2">

                                    {{-- Click area → open detail --}}
                                    <button type="button"
                                        onclick="openRecipeModal({{ json_encode($cardData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }})"
                                        class="flex-1 min-w-0 text-left">

                                        {{-- Cook time + title --}}
                                        <div>
                                            @if ($recipe->cooking_time)
                                            <p class="mb-1 text-xs font-medium text-[#9b3d16]">⏱ {{ $recipe->cooking_time }} min</p>
                                            @endif
                                            <h3 class="font-['Space_Grotesk'] text-base font-semibold leading-snug text-[#4a2b1d] sm:text-[17px]">
                                                {{ $recipe->title }}
                                            </h3>
                                        </div>

                                    {{-- Cuisine + dietary tags --}}
                                    @if (!empty($cuisineTags) || $recipe->dietary)
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($cuisineTags as $c)
                                        <span class="rounded-full bg-[#fff3e8] px-2.5 py-0.5 text-[11px] font-medium text-[#7b4a34]">{{ $c }}</span>
                                        @endforeach
                                        @if ($recipe->dietary)
                                        <span class="rounded-full bg-[#eaf5ea] px-2.5 py-0.5 text-[11px] font-medium text-[#3b6b43]">{{ ucfirst(str_replace('_', ' ', $recipe->dietary)) }}</span>
                                        @endif
                                    </div>
                                    @endif

                                    {{-- Ingredient chips --}}
                                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                                        @foreach (array_slice($recipe->ingredients ?? [], 0, 5) as $ingredient)
                                        <span class="rounded-full bg-[#fff3e8] px-2.5 py-0.5 text-xs font-medium text-[#7b4a34] ring-1 ring-inset ring-[#f4c8a0]/60">
                                            {{ ucfirst($ingredient) }}
                                        </span>
                                        @endforeach
                                        @if (count($recipe->ingredients ?? []) > 5)
                                        <span class="rounded-full bg-[#f5f0eb] px-2.5 py-0.5 text-xs text-[#9a7a6a]">
                                            +{{ count($recipe->ingredients) - 5 }}
                                        </span>
                                        @endif
                                    </div>

                                    {{-- Good match --}}
                                    @if (!empty($explanations))
                                    <p class="mt-2.5 text-xs text-[#9b5a30]">✓ {{ implode(' · ', array_slice($explanations, 0, 2)) }}</p>
                                    @endif
                                    </button>{{-- end openRecipeModal button --}}

                                    {{-- Save button --}}
                                    <button type="button"
                                        onclick="toggleSave({{ $recipe->id }}, this)"
                                        data-id="{{ $recipe->id }}"
                                        class="save-btn mt-0.5 shrink-0 flex h-8 w-8 items-center justify-center rounded-full border border-[#f1c39a] bg-white/80 text-base transition hover:border-[#f77737]/50 hover:bg-[#fff1e4]"
                                        aria-label="Save recipe">
                                        <span class="save-heart">🤍</span>
                                    </button>

                                </div>{{-- end title flex row --}}

                                {{-- Add to Plan --}}
                                <button type="button"
                                    onclick="addToPlan({{ json_encode($cardData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }})"
                                    data-plan-id="{{ $recipe->id }}"
                                    class="plan-btn mt-3 flex w-full items-center justify-center gap-1 rounded-[0.9rem] bg-[#fff3e8] py-2.5 text-sm font-medium text-[#7b4a34] ring-1 ring-inset ring-[#f4c8a0]/60 transition hover:bg-[#ffe7d1] hover:ring-[#f77737]/40">
                                    + Add to Plan
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            @elseif ($query !== '')
            <section class="mt-16 mx-auto w-full max-w-4xl text-center">
                <p class="text-4xl">🍽️</p>
                <p class="mt-4 font-['Space_Grotesk'] text-xl font-semibold text-[#5a2414]">No results found</p>
                <p class="mt-2 text-[#9a6a4c]">Try a different search or use the suggestion chips above.</p>
            </section>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SCREEN: MEAL PLAN
        ════════════════════════════════════════════════════════════ --}}
        <div id="screen-meal-plan" class="hidden">
            <div class="mx-auto w-full max-w-2xl">
                <div class="mb-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Meal Plan</p>
                    <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-[#5a2414]">Your selected recipes</h2>
                </div>

                {{-- Empty state --}}
                <div id="meal-plan-empty" class="hidden rounded-[1.75rem] border border-dashed border-[#f1c39a] bg-white/50 py-16 text-center">
                    <p class="text-4xl">📋</p>
                    <p class="mt-4 font-['Space_Grotesk'] text-xl font-semibold text-[#5a2414]">Your plan is empty</p>
                    <p class="mt-2 text-[#9a6a4c]">Search for recipes and hit "+ Add to Plan"</p>
                    <button onclick="showScreen('search')"
                        class="mt-6 inline-flex items-center gap-2 rounded-full bg-[#f77737] px-6 py-3 text-sm font-medium text-white transition hover:bg-[#f56040]">
                        Search recipes
                    </button>
                </div>

                {{-- Plan list --}}
                <div id="meal-plan-list" class="space-y-4"></div>

                {{-- Actions --}}
                <div id="meal-plan-actions" class="hidden mt-8 flex flex-col gap-3 sm:flex-row">
                    <button onclick="generateGroceryList()"
                        class="flex-1 rounded-[1.2rem] bg-[#f77737] px-6 py-4 font-medium text-white transition hover:bg-[#f56040]">
                        Generate Grocery List
                    </button>
                    <button onclick="clearPlan()"
                        class="rounded-[1.2rem] border border-[#f1c39a] bg-white/70 px-6 py-4 font-medium text-[#7b4a34] transition hover:border-[#f77737]/50 hover:bg-[#fff1e4]">
                        Clear Plan
                    </button>
                </div>

                <p id="meal-plan-loading" class="hidden mt-4 text-center text-sm text-[#9a6a4c]">Generating your grocery list…</p>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SCREEN: GROCERY LIST
        ════════════════════════════════════════════════════════════ --}}
        <div id="screen-grocery" class="hidden">
            <div class="mx-auto w-full max-w-2xl">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Grocery List</p>
                        <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-[#5a2414]">What to buy</h2>
                    </div>
                    <div class="mt-1 flex shrink-0 gap-2">
                        <button onclick="showScreen('meal-plan')"
                            class="rounded-full border border-[#f1c39a] bg-white/70 px-4 py-2 text-sm text-[#7b4a34] transition hover:border-[#f77737]/50">
                            ← Edit Plan
                        </button>
                        <button onclick="clearGroceryList()"
                            class="rounded-full border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-500 transition hover:bg-red-100">
                            Clear List
                        </button>
                    </div>

                {{-- Empty state --}}
                <div id="grocery-empty" class="hidden rounded-[1.75rem] border border-dashed border-[#f1c39a] bg-white/50 py-16 text-center">
                    <p class="text-4xl">🛒</p>
                    <p class="mt-4 font-['Space_Grotesk'] text-xl font-semibold text-[#5a2414]">No grocery list yet</p>
                    <p class="mt-2 text-[#9a6a4c]">Add recipes to your plan and generate a list.</p>
                </div>

                {{-- Summary bar --}}
                <div id="grocery-summary" class="hidden rounded-[1.5rem] border border-[#f1c39a] bg-white/80 p-5">
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <p class="text-xs text-[#9a6a4c]">Total items</p>
                            <p id="grocery-total-items" class="mt-1 font-['Space_Grotesk'] text-2xl font-bold text-[#4a2b1d]">0</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#9a6a4c]">Estimated cost</p>
                            <p id="grocery-total-cost" class="mt-1 font-['Space_Grotesk'] text-2xl font-bold text-[#4a2b1d]">₹0</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#9a6a4c]">From pantry</p>
                            <p id="grocery-pantry-count" class="mt-1 font-['Space_Grotesk'] text-2xl font-bold text-[#3b6b43]">0</p>
                        </div>
                    </div>
                </div>

                {{-- Items list --}}
                <div id="grocery-items" class="mt-5 space-y-3"></div>
            </div>
        </div>

    </main>
</div>

{{-- ─── Recipe Detail Modal ────────────────────────────────────── --}}
<div id="recipe-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div id="modal-backdrop" class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRecipeModal()"></div>
    <div class="relative z-10 flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-[2rem] bg-[#fff7ed] shadow-2xl">

        {{-- Hero: thumbnail + title overlay --}}
        <div id="modal-media" class="relative flex-none bg-gradient-to-br from-[#f99142] to-[#e8521a]" style="aspect-ratio:16/9;min-height:180px">
            <img id="modal-thumbnail" class="absolute inset-0 h-full w-full object-cover" src="" alt="">
            {{-- Gradient overlay --}}
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>
            {{-- Close --}}
            <button type="button" onclick="closeRecipeModal()"
                class="absolute right-4 top-4 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-black/30 text-white backdrop-blur-sm transition hover:bg-black/50"
                aria-label="Close">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            {{-- Overlaid title + badges --}}
            <div class="pointer-events-none absolute bottom-0 left-0 right-0 p-5">
                <div id="modal-badges" class="mb-2 flex flex-wrap gap-1.5"></div>
                <h2 id="modal-title" class="font-['Space_Grotesk'] text-xl font-bold leading-snug text-white drop-shadow sm:text-2xl"></h2>
            </div>
        </div>

        {{-- Action strip --}}
        <div class="flex flex-none items-center gap-2 border-b border-[#f1c39a]/50 bg-[#fff7ed] px-5 py-3">
            <button id="modal-save-btn" onclick="modalFeedback('save')"
                class="flex items-center gap-1.5 rounded-full border border-[#f1c39a] bg-[#fff7ed] px-3 py-1.5 text-sm font-medium text-[#7b4a34] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600">
                <span id="modal-save-heart">🤍</span><span class="hidden sm:inline">Save</span>
            </button>
            <button onclick="modalFeedback('like')"
                class="flex items-center gap-1.5 rounded-full border border-[#f1c39a] bg-[#fff7ed] px-3 py-1.5 text-sm font-medium text-[#7b4a34] transition hover:border-green-200 hover:bg-green-50 hover:text-green-700">
                👍<span class="ml-1 hidden text-xs sm:inline">Like</span>
            </button>
            <button onclick="modalFeedback('dislike')"
                class="flex items-center gap-1.5 rounded-full border border-[#f1c39a] bg-[#fff7ed] px-3 py-1.5 text-sm text-[#7b4a34] transition hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600">
                👎
            </button>
            <div class="flex-1"></div>
            <button id="modal-plan-btn" onclick="addToPlanFromModal()"
                class="rounded-full bg-[#f77737] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#f56040] disabled:opacity-50">
                + Add to Plan
            </button>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-3">

            {{-- Stats row (horizontal scroll) --}}
            <div id="modal-stats-wrap" class="hidden rounded-2xl border border-[#f1c39a]/50 bg-white/70 px-4 py-3">
                <div id="modal-stats" class="no-scrollbar flex gap-3 overflow-x-auto"></div>
            </div>

            {{-- Taste badges --}}
            <div id="modal-taste-wrap" class="hidden rounded-2xl border border-[#f1c39a]/50 bg-white/70 p-4">
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.25em] text-[#a15a33]">Flavour</p>
                <div id="modal-taste" class="flex flex-wrap gap-2"></div>
            </div>

            {{-- Why this recipe --}}
            <div id="modal-why-wrap" class="hidden rounded-2xl border border-[#f1c39a]/50 bg-white/70 p-4">
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.25em] text-[#a15a33]">Why this recipe?</p>
                <ul id="modal-why" class="space-y-1 text-sm text-[#4a2b1d]"></ul>
            </div>

            {{-- Nutrition --}}
            <div id="modal-nutrition-wrap" class="hidden rounded-2xl border border-[#f1c39a]/50 bg-white/70 p-4">
                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.25em] text-[#a15a33]">Nutrition per serving</p>
                <div id="modal-nutrition" class="grid grid-cols-3 gap-2 sm:grid-cols-6"></div>
            </div>

            {{-- Ingredients --}}
            <div id="modal-ingredients-wrap" class="hidden rounded-2xl border border-[#f1c39a]/50 bg-white/70 p-4">
                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.25em] text-[#a15a33]">Ingredients</p>
                <ul id="modal-ingredients" class="space-y-2"></ul>
            </div>

            {{-- Instructions --}}
            <div id="modal-instructions-wrap" class="hidden rounded-2xl border border-[#f1c39a]/50 bg-white/70 p-4">
                <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.25em] text-[#a15a33]">Instructions</p>
                <ol id="modal-instructions" class="space-y-4"></ol>
            </div>

            {{-- Footer link --}}
            <a id="modal-link" href="#" target="_blank" rel="noreferrer"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border border-[#f1c39a] bg-[#fff7ed] py-3 text-sm font-medium text-[#7b4a34] transition hover:border-[#f77737]/50 hover:bg-[#fff1e4]">
                View full recipe on Cookd
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
        </div>
    </div>
</div>

{{-- ─── Substitution Modal ─────────────────────────────────────── --}}
<div id="sub-modal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeSubModal()"></div>
    <div class="relative z-10 w-full max-w-md rounded-[2rem] bg-[#fff7ed] p-6 shadow-2xl">
        <h3 class="font-['Space_Grotesk'] text-xl font-bold text-[#4a2b1d]">
            Substitute <span id="sub-modal-name" class="text-[#f77737]"></span>
        </h3>
        <p class="mt-1 text-sm text-[#9a6a4c]">Choose a replacement ingredient</p>

        <div id="sub-modal-loading" class="hidden py-8 text-center text-sm text-[#9a6a4c]">Looking for substitutes…</div>
        <div id="sub-modal-none" class="hidden py-8 text-center text-sm text-[#9a6a4c]">No safe substitutes found for this ingredient.</div>
        <ul id="sub-modal-list" class="mt-4 space-y-3"></ul>

        <button onclick="closeSubModal()"
            class="mt-5 w-full rounded-[1rem] border border-[#f1c39a] bg-white/70 py-3 text-sm font-medium text-[#7b4a34] transition hover:border-[#f77737]/50">
            Cancel
        </button>
    </div>
</div>

{{-- ─── JavaScript ─────────────────────────────────────────────── --}}
<script>
// ── Config ──────────────────────────────────────────────────────
const ROUTES = {
    feedback:     '{{ route('feedback.store') }}',
    substitution: '{{ route('substitution.suggest') }}',
    groceryList:  '{{ route('grocery-list.generate') }}',
};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const QUERY = @json($query);
const INTENT = @json($context?->intent ?? null);
const PARSED_INCLUDES = @json($parsed['include'] ?? []);

// ── State ────────────────────────────────────────────────────────
const state = {
    mealPlan:     JSON.parse(localStorage.getItem('cookd_meal_plan') || '[]'),
    savedRecipes: new Set(JSON.parse(localStorage.getItem('cookd_saved') || '[]')),
    pantryItems:  new Set(JSON.parse(localStorage.getItem('cookd_pantry') || '[]')),
    groceryList:  JSON.parse(localStorage.getItem('cookd_grocery') || 'null'),
    currentScreen: 'search',
    activeRecipe:  null,
    subContext:    null, // { ingredient_name, dish_type, listItemEl }
};

function persist() {
    localStorage.setItem('cookd_meal_plan', JSON.stringify(state.mealPlan));
    localStorage.setItem('cookd_saved',     JSON.stringify([...state.savedRecipes]));
    localStorage.setItem('cookd_pantry',    JSON.stringify([...state.pantryItems]));
    if (state.groceryList) localStorage.setItem('cookd_grocery', JSON.stringify(state.groceryList));
    else localStorage.removeItem('cookd_grocery');
}

// ── API ──────────────────────────────────────────────────────────
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(data),
    });
    if (!res.ok) throw new Error('Request failed: ' + res.status);
    return res.json();
}

// ── Screen management ────────────────────────────────────────────
function showScreen(name) {
    ['search', 'meal-plan', 'grocery'].forEach(s => {
        document.getElementById('screen-' + s).classList.toggle('hidden', s !== name);
        document.getElementById('tab-' + s).classList.toggle('bg-[#f77737]', s === name);
        document.getElementById('tab-' + s).classList.toggle('text-white', s === name);
        document.getElementById('tab-' + s).classList.toggle('text-[#7b4a34]', s !== name);
    });
    state.currentScreen = name;
    if (name === 'meal-plan') renderMealPlan();
    if (name === 'grocery') renderGroceryList();
}

// ── Save / Like / Dislike (cards) ────────────────────────────────
function toggleSave(recipeId, btn) {
    const isSaved = state.savedRecipes.has(recipeId);
    if (isSaved) {
        state.savedRecipes.delete(recipeId);
        btn.querySelector('.save-heart').textContent = '🤍';
    } else {
        state.savedRecipes.add(recipeId);
        btn.querySelector('.save-heart').textContent = '❤️';
        recordFeedback(recipeId, 'save');
    }
    persist();
}

// ── Plan management ──────────────────────────────────────────────
function addToPlan(recipe) {
    const id = recipe._db_id || recipe.id;
    if (state.mealPlan.find(r => (r._db_id || r.id) === id)) return; // already in plan
    state.mealPlan.push(recipe);
    persist();
    updatePlanBadge();
    // Visual feedback on the card
    document.querySelectorAll('[data-plan-id="' + id + '"]').forEach(btn => {
        btn.textContent = '✓ Added';
        btn.disabled = true;
        btn.classList.add('opacity-60');
    });
}

function addToPlanFromModal() {
    if (!state.activeRecipe) return;
    addToPlan(state.activeRecipe);
    document.getElementById('modal-plan-btn').textContent = '✓ Added to Plan';
    document.getElementById('modal-plan-btn').disabled = true;
}

function removeFromPlan(id) {
    state.mealPlan = state.mealPlan.filter(r => (r._db_id || r.id) !== id);
    persist();
    updatePlanBadge();
    renderMealPlan();
}

function clearPlan() {
    state.mealPlan = [];
    state.groceryList = null;
    persist();
    updatePlanBadge();
    renderMealPlan();
}

function clearGroceryList() {
    state.groceryList = null;
    state.pantryItems = new Set();
    persist();
    renderGroceryList();
}

function updatePlanBadge() {
    const count = state.mealPlan.length;
    const badge = document.getElementById('plan-badge');
    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
        badge.classList.add('flex');
    } else {
        badge.classList.add('hidden');
        badge.classList.remove('flex');
    }
}

function renderMealPlan() {
    const listEl    = document.getElementById('meal-plan-list');
    const emptyEl   = document.getElementById('meal-plan-empty');
    const actionsEl = document.getElementById('meal-plan-actions');

    listEl.innerHTML = '';

    if (state.mealPlan.length === 0) {
        emptyEl.classList.remove('hidden');
        actionsEl.classList.add('hidden');
        return;
    }

    emptyEl.classList.add('hidden');
    actionsEl.classList.remove('hidden');

    state.mealPlan.forEach(recipe => {
        const id    = recipe._db_id || recipe.id;
        const title = recipe.title || 'Recipe';
        const time  = recipe._cook_time || recipe.cooking_time;
        const ings  = (recipe._ingredients || []).slice(0, 4);
        const div   = document.createElement('div');
        div.className = 'flex items-center gap-4 rounded-[1.5rem] border border-[#f1c39a] bg-white/78 p-4';
        div.innerHTML =
            '<div class="flex-1 min-w-0">' +
                '<p class="font-semibold text-[#4a2b1d] truncate">' + escHtml(title) + '</p>' +
                (time ? '<p class="mt-0.5 text-xs text-[#9a6a4c]">' + time + ' min</p>' : '') +
                (ings.length ? '<p class="mt-1 text-xs text-[#b48263] truncate">' + ings.map(escHtml).join(', ') + '</p>' : '') +
            '</div>' +
            '<button onclick="removeFromPlan(' + id + ')" ' +
                'class="shrink-0 rounded-full border border-[#f1c39a] bg-white/70 px-3 py-1.5 text-xs font-medium text-[#9a6a4c] transition hover:border-red-200 hover:bg-red-50 hover:text-red-500">' +
                'Remove' +
            '</button>';
        listEl.appendChild(div);
    });

    // Re-sync "Added" state on search card buttons
    syncPlanButtons();
}

function syncPlanButtons() {
    const planIds = new Set(state.mealPlan.map(r => r._db_id || r.id));
    document.querySelectorAll('[data-plan-id]').forEach(btn => {
        const id = parseInt(btn.dataset.planId);
        if (planIds.has(id)) {
            btn.textContent = '✓ Added';
            btn.disabled = true;
            btn.classList.add('opacity-60');
        } else {
            btn.textContent = '+ Add to Plan';
            btn.disabled = false;
            btn.classList.remove('opacity-60');
        }
    });
}

// ── Grocery list ─────────────────────────────────────────────────
async function generateGroceryList() {
    if (state.mealPlan.length === 0) return;
    const loadingEl = document.getElementById('meal-plan-loading');
    loadingEl.classList.remove('hidden');

    const recipeIds = state.mealPlan.map(r => r._db_id || r.id);
    try {
        const data = await apiPost(ROUTES.groceryList, { recipe_ids: recipeIds });
        state.groceryList = data;
        persist();
        showScreen('grocery');
    } catch (e) {
        alert('Could not generate grocery list. Please try again.');
    } finally {
        loadingEl.classList.add('hidden');
    }
}

function renderGroceryList() {
    const emptyEl   = document.getElementById('grocery-empty');
    const summaryEl = document.getElementById('grocery-summary');
    const itemsEl   = document.getElementById('grocery-items');

    if (!state.groceryList || !state.groceryList.items || state.groceryList.items.length === 0) {
        emptyEl.classList.remove('hidden');
        summaryEl.classList.add('hidden');
        itemsEl.innerHTML = '';
        return;
    }

    emptyEl.classList.add('hidden');
    summaryEl.classList.remove('hidden');

    const items = state.groceryList.items;
    const toBuyItems = items.filter(i => i.quantity_to_buy > 0 && !state.pantryItems.has(i.ingredient_id));
    const haveItems  = items.filter(i => i.quantity_to_buy <= 0 || state.pantryItems.has(i.ingredient_id));

    document.getElementById('grocery-total-items').textContent = toBuyItems.length;
    document.getElementById('grocery-pantry-count').textContent = haveItems.length;
    const totalCost = toBuyItems.reduce((sum, i) => sum + (i.estimated_cost || 0), 0);
    document.getElementById('grocery-total-cost').textContent =
        totalCost > 0 ? '₹' + totalCost.toFixed(2) : '—';

    itemsEl.innerHTML = '';

    // Items to buy
    if (toBuyItems.length) {
        const header = document.createElement('p');
        header.className = 'text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33] mb-2';
        header.textContent = 'To Buy';
        itemsEl.appendChild(header);
        toBuyItems.forEach(item => itemsEl.appendChild(groceryItemEl(item, false)));
    }

    // Already have
    if (haveItems.length) {
        const header = document.createElement('p');
        header.className = 'text-xs font-semibold uppercase tracking-[0.3em] text-[#3b6b43] mt-6 mb-2';
        header.textContent = 'Already Have';
        itemsEl.appendChild(header);
        haveItems.forEach(item => itemsEl.appendChild(groceryItemEl(item, true)));
    }
}

function groceryItemEl(item, inPantry) {
    const div = document.createElement('div');
    div.id = 'gitem-' + item.ingredient_id;
    div.className = 'rounded-[1.25rem] border border-[#f1c39a] bg-white/78 p-4 ' +
        (inPantry ? 'opacity-60' : '');

    const name  = item.ingredient_name || ('Ingredient #' + item.ingredient_id);
    const qty   = item.quantity_to_buy > 0 ? item.quantity_to_buy.toFixed(2) : item.required_quantity.toFixed(2);
    const unit  = item.unit || '';
    const cost  = item.estimated_cost > 0 ? '₹' + item.estimated_cost.toFixed(2) : '';
    const sub   = item.substitution_used;
    const notes = item.notes;

    div.innerHTML =
        '<div class="flex items-start justify-between gap-3">' +
            '<div class="flex-1 min-w-0">' +
                '<p class="font-medium text-[#4a2b1d] capitalize">' + escHtml(name) + '</p>' +
                '<p class="mt-0.5 text-xs text-[#9a6a4c]">' +
                    'Need: ' + qty + (unit ? ' ' + unit : '') +
                    (item.pantry_quantity_used > 0 ? ' · Have: ' + item.pantry_quantity_used.toFixed(2) : '') +
                    (cost ? ' · ' + cost : '') +
                '</p>' +
                (sub ? '<p class="mt-1 text-xs text-[#7b4a34]">→ Substitute: <strong>' + escHtml(sub) + '</strong></p>' : '') +
                (notes ? '<p class="mt-0.5 text-xs text-[#b48263]">' + escHtml(notes) + '</p>' : '') +
            '</div>' +
            '<div class="flex shrink-0 flex-col gap-2 items-end">' +
                (!inPantry
                    ? '<button onclick="markHaveItem(' + item.ingredient_id + ')" ' +
                        'class="rounded-full border border-[#d1e7d8] bg-[#eaf5ea] px-3 py-1 text-xs font-medium text-[#3b6b43] transition hover:bg-[#d1e7d8]">' +
                        'I have this</button>'
                    : '<button onclick="unmarkHaveItem(' + item.ingredient_id + ')" ' +
                        'class="rounded-full border border-[#f1c39a] bg-white/70 px-3 py-1 text-xs font-medium text-[#9a6a4c] transition hover:bg-[#fff1e4]">' +
                        'Undo</button>') +
                '<button onclick="openSubModal(\'' + escAttr(name) + '\', null, document.getElementById(\'gitem-' + item.ingredient_id + '\'))" ' +
                    'class="rounded-full border border-[#f1c39a] bg-white/70 px-3 py-1 text-xs font-medium text-[#7b4a34] transition hover:border-[#f77737]/50 hover:bg-[#fff1e4]">' +
                    'Substitute</button>' +
            '</div>' +
        '</div>';

    return div;
}

function markHaveItem(ingredientId) {
    state.pantryItems.add(ingredientId);
    persist();
    renderGroceryList();
}

function unmarkHaveItem(ingredientId) {
    state.pantryItems.delete(ingredientId);
    persist();
    renderGroceryList();
}

// ── Substitution modal ───────────────────────────────────────────
function openSubModal(ingredientName, dishType, contextEl) {
    state.subContext = { ingredientName, dishType, contextEl };
    document.getElementById('sub-modal-name').textContent = ingredientName;
    document.getElementById('sub-modal-list').innerHTML = '';
    document.getElementById('sub-modal-none').classList.add('hidden');
    document.getElementById('sub-modal-loading').classList.remove('hidden');
    const modal = document.getElementById('sub-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    apiPost(ROUTES.substitution, { ingredient: ingredientName, dish_type: dishType || null })
        .then(data => {
            document.getElementById('sub-modal-loading').classList.add('hidden');
            if (!data.suggestions || data.suggestions.length === 0) {
                document.getElementById('sub-modal-none').classList.remove('hidden');
                return;
            }
            renderSubOptions(data.suggestions);
        })
        .catch(() => {
            document.getElementById('sub-modal-loading').classList.add('hidden');
            document.getElementById('sub-modal-none').classList.remove('hidden');
        });
}

function renderSubOptions(suggestions) {
    const list = document.getElementById('sub-modal-list');
    suggestions.forEach((s, i) => {
        const isWeak  = s.relation === 'weak_substitute';
        const label   = i === 0 ? 'Best match' : (isWeak ? 'Possible' : 'Good match');
        const li = document.createElement('li');
        li.className = 'rounded-[1.25rem] border ' +
            (isWeak ? 'border-[#f4dba0] bg-[#fffbf0]' : 'border-[#f1c39a] bg-white/78') +
            ' p-4';
        li.innerHTML =
            '<div class="flex items-start justify-between gap-3">' +
                '<div>' +
                    '<div class="flex items-center gap-2">' +
                        '<p class="font-medium capitalize text-[#4a2b1d]">' + escHtml(s.ingredient) + '</p>' +
                        '<span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ' +
                            (isWeak ? 'bg-[#f4dba0] text-[#7b5a10]' : 'bg-[#d1e7d8] text-[#3b6b43]') + '">' +
                            escHtml(label) + '</span>' +
                    '</div>' +
                    '<p class="mt-1 text-xs text-[#9a6a4c]">' + escHtml(s.taste_impact) + '</p>' +
                    '<p class="text-xs text-[#b48263]">Use ' + (s.quantity_adjustment * 100).toFixed(0) + '% of original quantity</p>' +
                '</div>' +
                '<button onclick="chooseSubstitute(\'' + escAttr(s.ingredient) + '\')" ' +
                    'class="shrink-0 rounded-full bg-[#f77737] px-4 py-2 text-xs font-medium text-white transition hover:bg-[#f56040]">' +
                    'Use this</button>' +
            '</div>';
        list.appendChild(li);
    });
}

function chooseSubstitute(ingredientName) {
    closeSubModal();
    // If substituting in modal ingredient list, update the display
    if (state.subContext?.contextEl) {
        const name = state.subContext.contextEl.querySelector('.font-medium');
        if (name) name.textContent = ingredientName + ' (substituted)';
    }
}

function closeSubModal() {
    const modal = document.getElementById('sub-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// ── Recipe detail modal ──────────────────────────────────────────
function openRecipeModal(r) {
    state.activeRecipe = r;
    const modal = document.getElementById('recipe-modal');
    const dbId  = r._db_id || r.id;

    // Title
    document.getElementById('modal-title').textContent = r.title || '';

    // External link
    document.getElementById('modal-link').href = 'https://cookdtv.com/recipes/' + (r.id || dbId);

    // Badges
    var badges = document.getElementById('modal-badges');
    badges.innerHTML = '';
    [
        { value: r.dietary_restriction, style: 'bg-[#eaf5ea] text-[#3b6b43]' },
        { value: (r.cuisines_name || []).join(', '), style: 'bg-[#fff3e8] text-[#7b4a34]' },
        { value: (r.meal_courses_name || []).join(', '), style: 'bg-[#ffe7d1] text-[#9b3d16]' },
    ].forEach(b => {
        if (!b.value) return;
        var span = document.createElement('span');
        span.className = 'rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset ring-current/20 ' + b.style;
        span.textContent = b.value.replace(/_/g, ' ');
        badges.appendChild(span);
    });

    // Taste badges
    var taste = r._taste || r.taste_profile;
    var tasteWrap = document.getElementById('modal-taste-wrap');
    var tasteEl   = document.getElementById('modal-taste');
    tasteEl.innerHTML = '';
    var tasteDefs = [
        { key: 'spicy',  emoji: '🌶️', label: 'Spicy',  color: 'bg-red-50 text-red-600 ring-red-200' },
        { key: 'tangy',  emoji: '🍋', label: 'Tangy',  color: 'bg-yellow-50 text-yellow-700 ring-yellow-200' },
        { key: 'sweet',  emoji: '🍯', label: 'Sweet',  color: 'bg-pink-50 text-pink-600 ring-pink-200' },
        { key: 'rich',   emoji: '🧈', label: 'Rich',   color: 'bg-amber-50 text-amber-700 ring-amber-200' },
        { key: 'savory', emoji: '🧂', label: 'Savory', color: 'bg-orange-50 text-orange-700 ring-orange-200' },
    ];
    var hasTaste = false;
    if (taste) {
        tasteDefs.forEach(t => {
            var val = taste[t.key];
            if (!val || val < 0.5) return;
            hasTaste = true;
            var span = document.createElement('span');
            span.className = 'flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset ' + t.color;
            span.innerHTML = t.emoji + ' ' + t.label;
            if (val >= 0.8) {
                var dot = document.createElement('span');
                dot.className = 'h-1.5 w-1.5 rounded-full bg-current opacity-60';
                span.appendChild(dot);
            }
            tasteEl.appendChild(span);
        });
    }
    tasteWrap.classList.toggle('hidden', !hasTaste);

    // Stats
    var stats = document.getElementById('modal-stats');
    stats.innerHTML = '';
    [
        { label: 'Cook time', value: r.cooking_time ? r.cooking_time + ' min' : null },
        { label: 'Prep time', value: r.preperation_time ? r.preperation_time + ' min' : null },
        { label: 'Servings',  value: r.no_of_servings || null },
        { label: 'Spice',     value: r.spice_level != null ? '🌶'.repeat(Math.min(r.spice_level, 5)) : null },
        { label: 'Method',    value: (r.cooking_methods || []).join(', ') || null },
    ].forEach(s => {
        if (!s.value) return;
        var div = document.createElement('div');
        div.className = 'flex-none rounded-2xl border border-[#f1c39a] bg-white/80 px-4 py-3 text-center min-w-[72px]';
        div.innerHTML = '<p class="text-[10px] text-[#9a6a4c]">' + s.label + '</p><p class="mt-1 text-sm font-semibold text-[#4a2b1d]">' + s.value + '</p>';
        stats.appendChild(div);
    });
    document.getElementById('modal-stats-wrap').classList.toggle('hidden', stats.children.length === 0);

    // Save/plan button state
    const saved = state.savedRecipes.has(dbId);
    document.getElementById('modal-save-heart').textContent = saved ? '❤️' : '🤍';
    const inPlan = state.mealPlan.find(r2 => (r2._db_id || r2.id) === dbId);
    var planBtn = document.getElementById('modal-plan-btn');
    planBtn.textContent = inPlan ? '✓ Added to Plan' : '+ Add to Plan';
    planBtn.disabled = !!inPlan;

    // Why this recipe
    var whyWrap = document.getElementById('modal-why-wrap');
    var whyList = document.getElementById('modal-why');
    whyList.innerHTML = '';
    var reasons = buildWhyReasons(r);
    if (reasons.length) {
        reasons.forEach(txt => {
            var li = document.createElement('li');
            li.textContent = '✓ ' + txt;
            whyList.appendChild(li);
        });
        whyWrap.classList.remove('hidden');
    } else {
        whyWrap.classList.add('hidden');
    }

    // Nutrition
    var nv = r.nutritional_values || {};
    var nutritionWrap = document.getElementById('modal-nutrition-wrap');
    var nutritionEl   = document.getElementById('modal-nutrition');
    nutritionEl.innerHTML = '';
    var nutritionKeys = ['calories', 'protein', 'fat', 'carbs', 'fiber', 'sugar'];
    var hasNutrition = nutritionKeys.some(k => nv[k] != null);
    if (hasNutrition) {
        nutritionKeys.forEach(k => {
            if (nv[k] == null) return;
            var div = document.createElement('div');
            div.className = 'rounded-2xl border border-[#f1c39a] bg-white/70 px-3 py-3 text-center';
            div.innerHTML = '<p class="text-xs text-[#9a6a4c] capitalize">' + k + '</p><p class="mt-1 text-sm font-semibold text-[#4a2b1d]">' + nv[k] + (k === 'calories' ? ' kcal' : 'g') + '</p>';
            nutritionEl.appendChild(div);
        });
        nutritionWrap.classList.remove('hidden');
    } else {
        nutritionWrap.classList.add('hidden');
    }

    // Ingredients with Substitute button
    var ingredientsWrap = document.getElementById('modal-ingredients-wrap');
    var ingredientsList = document.getElementById('modal-ingredients');
    ingredientsList.innerHTML = '';
    var ingRows = r.recipe_ingredients || [];
    if (ingRows.length) {
        ingRows.forEach(ing => {
            var li = document.createElement('li');
            li.className = 'flex items-center gap-3 rounded-xl border border-[#f1c39a]/50 bg-white/70 px-3 py-2.5 text-sm';
            var qty = [ing.quantity, ing.unit].filter(Boolean).join(' ');
            li.innerHTML =
                (ing.image_url
                    ? '<img src="' + ing.image_url + '" class="h-8 w-8 rounded-full object-cover shrink-0" alt="">'
                    : '<span class="h-8 w-8 rounded-full bg-[#fff3e8] shrink-0"></span>') +
                '<span class="flex-1 font-medium text-[#4a2b1d]">' + escHtml(ing.ingredient_name || '') + '</span>' +
                (qty ? '<span class="shrink-0 text-[#9a6a4c]">' + escHtml(qty) + '</span>' : '') +
                (ing.preparation ? '<span class="text-[#b48263]">(' + escHtml(ing.preparation) + ')</span>' : '') +
                '<button onclick="openSubModal(\'' + escAttr(ing.ingredient_name || '') + '\', ' +
                    '\'' + escAttr(r.dish_type || '') + '\', this.closest(\'li\'))" ' +
                    'class="shrink-0 rounded-full border border-[#f1c39a] bg-white/70 px-2 py-1 text-[10px] font-medium text-[#9a6a4c] transition hover:border-[#f77737]/50 hover:text-[#7b4a34]">' +
                    'Substitute</button>';
            ingredientsList.appendChild(li);
        });
        ingredientsWrap.classList.remove('hidden');
    } else {
        ingredientsWrap.classList.add('hidden');
    }

    // Instructions
    var instructionsWrap = document.getElementById('modal-instructions-wrap');
    var instructionsList = document.getElementById('modal-instructions');
    instructionsList.innerHTML = '';
    var steps = r.cooking_instructions || [];
    if (steps.length) {
        steps.forEach((step, i) => {
            var li = document.createElement('li');
            li.className = 'flex gap-4 text-sm leading-relaxed text-[#4a2b1d]';
            li.innerHTML = '<span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#f77737] text-xs font-bold text-white">' + (i + 1) + '</span><span class="flex-1">' + escHtml(step.trim()) + '</span>';
            instructionsList.appendChild(li);
        });
        instructionsWrap.classList.remove('hidden');
    } else {
        instructionsWrap.classList.add('hidden');
    }

    // Thumbnail
    var thumbnail = document.getElementById('modal-thumbnail');
    var thumbUrl  = r.rectangle_thumbail_url || r.square_thumbnail_url || '';
    if (thumbUrl) {
        thumbnail.src = thumbUrl; thumbnail.alt = r.title || '';
        thumbnail.style.display = 'block';
    } else {
        thumbnail.style.display = 'none';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    // Record view
    recordFeedback(dbId, 'view');
}

function closeRecipeModal() {
    var modal = document.getElementById('recipe-modal');
    modal.classList.add('hidden'); modal.classList.remove('flex');
    document.body.style.overflow = '';
    state.activeRecipe = null;
}

function modalFeedback(action) {
    if (!state.activeRecipe) return;
    const id = state.activeRecipe._db_id || state.activeRecipe.id;
    if (action === 'save') {
        const nowSaved = !state.savedRecipes.has(id);
        if (nowSaved) state.savedRecipes.add(id); else state.savedRecipes.delete(id);
        document.getElementById('modal-save-heart').textContent = nowSaved ? '❤️' : '🤍';
        persist();
        // Sync card button
        document.querySelectorAll('[data-id="' + id + '"] .save-heart').forEach(el => {
            el.textContent = nowSaved ? '❤️' : '🤍';
        });
        if (nowSaved) recordFeedback(id, 'save');
    } else {
        recordFeedback(id, action);
    }
}

// ── Why this recipe ──────────────────────────────────────────────
function buildWhyReasons(r) {
    var reasons = [];
    var taste   = r._taste || r.taste_profile;

    if (INTENT === 'quick_search' && r.cooking_time && r.cooking_time <= 25) {
        reasons.push('Quick to make (' + r.cooking_time + ' min)');
    }
    if (INTENT === 'healthy') {
        if (r.nutritional_values?.fat != null && r.nutritional_values.fat <= 10) reasons.push('Low fat');
        if (r.nutritional_values?.protein != null && r.nutritional_values.protein >= 20) reasons.push('High protein');
    }
    if (INTENT === 'flavor_focused' && taste) {
        var topFlavor = Object.entries(taste).sort((a, b) => b[1] - a[1])[0];
        if (topFlavor && topFlavor[1] >= 0.6) reasons.push('Matches flavor: ' + topFlavor[0]);
    }
    if (r.dietary_restriction) reasons.push('Matches diet: ' + r.dietary_restriction.replace(/_/g, ' '));
    if (PARSED_INCLUDES && PARSED_INCLUDES.length > 0 && r._ingredients) {
        var matched = PARSED_INCLUDES.filter(ing =>
            r._ingredients.some(ri => ri.toLowerCase().includes(ing.toLowerCase()))
        );
        if (matched.length > 0) reasons.push('Contains: ' + matched.slice(0, 2).join(', '));
    }
    return reasons.slice(0, 3);
}

// ── Feedback API ─────────────────────────────────────────────────
function recordFeedback(recipeId, action) {
    apiPost(ROUTES.feedback, {
        recipe_id: recipeId,
        action:    action,
        query_text: QUERY || null,
    }).catch(() => {}); // fire and forget
}

// ── Helpers ──────────────────────────────────────────────────────
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(str) {
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// ── Keyboard ─────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (!document.getElementById('sub-modal').classList.contains('hidden')) { closeSubModal(); return; }
        if (!document.getElementById('recipe-modal').classList.contains('hidden')) { closeRecipeModal(); return; }
    }
});

// ── Boot ─────────────────────────────────────────────────────────
(function init() {
    updatePlanBadge();
    syncPlanButtons();

    // Sync saved hearts on page load
    state.savedRecipes.forEach(id => {
        document.querySelectorAll('[data-id="' + id + '"] .save-heart').forEach(el => {
            el.textContent = '❤️';
        });
    });

    // Set active tab style for search on load
    showScreen('search');
})();
</script>

<style>
    .nav-tab { color: #7b4a34; }
    .nav-tab:hover { background: #fff1e4; color: #9b3d16; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

</body>
</html>
