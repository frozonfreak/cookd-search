<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Cookd Search</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#fff7ed] text-[#3f2415]">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(252,175,69,0.34),_transparent_32%),radial-gradient(circle_at_82%_18%,_rgba(247,119,55,0.26),_transparent_24%),linear-gradient(180deg,_#fff7ed_0%,_#ffe4c7_48%,_#ffd2ad_100%)]"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#f77737]/50 to-transparent"></div>

            <main class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-10 sm:px-10 lg:px-16">
                <div class="mb-6 flex justify-end">
                    <div class="inline-flex rounded-full border border-[#f1c39a] bg-white/70 p-1 shadow-lg shadow-[#f77737]/10">
                        <a
                            href="{{ route('home', array_filter(['q' => $query !== '' ? $query : null, 'view' => 'detailed'])) }}"
                            class="rounded-full px-4 py-2 text-sm font-medium transition {{ $viewMode === 'detailed' ? 'bg-[#f77737] text-white' : 'text-[#9a6a4c] hover:bg-[#fff1e4]' }}"
                        >
                            Detailed UI
                        </a>
                        <a
                            href="{{ route('home', array_filter(['q' => $query !== '' ? $query : null, 'view' => 'minimal'])) }}"
                            class="rounded-full px-4 py-2 text-sm font-medium transition {{ $viewMode === 'minimal' ? 'bg-[#f77737] text-white' : 'text-[#9a6a4c] hover:bg-[#fff1e4]' }}"
                        >
                            Minimal UI
                        </a>
                    </div>
                </div>

                <section class="grid gap-10 {{ $viewMode === 'detailed' ? 'lg:grid-cols-[1.1fr_0.9fr] lg:items-start' : '' }}">
                    <div class="{{ $viewMode === 'minimal' ? 'mx-auto w-full max-w-4xl' : 'max-w-3xl' }}">
                        <p class="mb-4 inline-flex rounded-full border border-[#f77737]/20 bg-white/60 px-3 py-1 text-xs font-medium uppercase tracking-[0.28em] text-[#b84a1b]">
                            Natural Language Recipe Search
                        </p>
                        <h1 class="font-['Space_Grotesk'] text-5xl font-bold tracking-tight text-[#5a2414] sm:text-6xl">
                            Search recipes the way people actually ask.
                        </h1>
                        <p class="mt-5 max-w-2xl text-base leading-7 text-[#7b4a34] sm:text-lg">
                            Try ingredient constraints, exclusions, pantry prompts, or meal hints. The search runs on PostgreSQL-backed recipe matching in the backend.
                        </p>

                        <form method="GET" action="{{ route('home') }}" class="mt-8">
                            <input type="hidden" name="view" value="{{ $viewMode }}">
                            <label for="q" class="sr-only">Search recipes</label>
                            <div class="rounded-[2rem] border border-[#f4b27f] bg-white/75 p-2 shadow-2xl shadow-[#f77737]/15 backdrop-blur">
                                <div class="flex flex-col gap-3 rounded-[1.6rem] border border-[#ffe7d1] bg-[#fffaf5]/90 p-3 sm:flex-row sm:items-center">
                                    <input
                                        id="q"
                                        name="q"
                                        type="text"
                                        value="{{ $query }}"
                                        placeholder="chutney without coconut"
                                        class="w-full border-0 bg-transparent px-4 py-4 text-lg text-[#4a2b1d] outline-none placeholder:text-[#b48263]"
                                    >
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-[1.2rem] bg-[#f77737] px-6 py-4 font-medium text-white transition hover:bg-[#f56040]"
                                    >
                                        Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="mt-5 flex flex-wrap gap-3 text-sm text-[#7b4a34]">
                            @foreach ([
                                'spicy tangy curry with extra onion but no garlic',
                                'high protein vegetarian breakfast under 20 min',
                                'comfort food dinner with tomato or paneer',
                                'light refreshing lunch low sodium with little oil',
                            ] as $example)
                                <a
                                    href="{{ route('home', ['q' => $example, 'view' => $viewMode]) }}"
                                    class="rounded-full border border-[#f3c49d] bg-white/70 px-4 py-2 transition hover:border-[#f77737]/50 hover:bg-[#fff1e4] hover:text-[#9b3d16]"
                                >
                                    {{ $example }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @if ($viewMode === 'detailed')
                    <aside class="rounded-[2rem] border border-[#f1c39a] bg-white/75 p-6 shadow-2xl shadow-[#f77737]/12 backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Search Signals</p>
                        @if ($parsed)
                            <div class="mt-6 space-y-4">
                                <div class="rounded-[1.25rem] border border-[#f4d1b2] bg-[#fff7f0] p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#a15a33]">Breakdown</p>
                                    <dl class="mt-3 space-y-3 text-sm">
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Original query</dt>
                                            <dd class="max-w-[70%] text-right text-[#4a2b1d]">{{ $query }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Rewritten query</dt>
                                            <dd class="max-w-[70%] text-right text-[#4a2b1d]">{{ $context?->rewrittenQuery ?? $context?->cleanedQuery ?? 'none' }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Dish intent</dt>
                                            <dd class="text-right text-[#4a2b1d]">{{ $parsed['dish_type'] ?? 'none' }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Meal intent</dt>
                                            <dd class="text-right text-[#4a2b1d]">{{ $parsed['meal_type'] ?? 'none' }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Time filter</dt>
                                            <dd class="text-right text-[#4a2b1d]">
                                                {{ isset($parsed['max_cooking_time']) && $parsed['max_cooking_time'] !== null ? '<= '.$parsed['max_cooking_time'].' min' : 'none' }}
                                            </dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Match mode</dt>
                                            <dd class="text-right text-[#4a2b1d]">{{ $parsed['strict'] ? 'strict subset' : 'flexible' }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Intent</dt>
                                            <dd class="text-right text-[#4a2b1d]">
                                                {{ $context?->intent ?? 'none' }}
                                                @if ($context)
                                                    ({{ number_format($context->intentConfidence, 2) }})
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-[#9a6a4c]">Personalization</dt>
                                            <dd class="text-right text-[#4a2b1d]">{{ ($tasteProfile['weight'] ?? 0) > 0 ? 'active' : 'inactive' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                                @if (($context?->rewrite['corrections'] ?? []) !== [])
                                    <div class="rounded-[1.25rem] border border-[#f4d1b2] bg-[#fff7f0] p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#a15a33]">Query Rewrites</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($context->rewrite['corrections'] as $rewrite)
                                                <span class="rounded-full bg-[#fcaf45]/18 px-3 py-1 text-sm text-[#9b4d09]">
                                                    {{ $rewrite['from'] }} → {{ $rewrite['to'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if ($dsl)
                                    <details class="rounded-[1.25rem] border border-[#f4d1b2] bg-[#fff7f0] p-4">
                                        <summary class="cursor-pointer list-none text-xs font-semibold uppercase tracking-[0.24em] text-[#a15a33]">
                                            DSL
                                        </summary>
                                        <pre class="mt-3 overflow-x-auto text-xs leading-6 text-[#7b4a34]">{{ json_encode($dsl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @endif
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Dish</p>
                                    <p class="mt-1 text-lg text-[#4a2b1d]">{{ $parsed['dish_type'] ?? 'Any' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Meal</p>
                                    <p class="mt-1 text-lg text-[#4a2b1d]">{{ $parsed['meal_type'] ?? 'Any' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Time</p>
                                    <p class="mt-1 text-lg text-[#4a2b1d]">
                                        {{ isset($parsed['max_cooking_time']) && $parsed['max_cooking_time'] !== null ? 'Up to '.$parsed['max_cooking_time'].' min' : 'Any' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Include</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($parsed['include'] as $item)
                                            <span class="rounded-full bg-[#f77737]/12 px-3 py-1 text-sm text-[#9b3d16]">{{ $item }}</span>
                                        @empty
                                            <span class="text-[#b48263]">None</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Exclude</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($parsed['exclude'] as $item)
                                            <span class="rounded-full bg-[#f56040]/12 px-3 py-1 text-sm text-[#b53d28]">{{ $item }}</span>
                                        @empty
                                            <span class="text-[#b48263]">None</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Quantity Constraints</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($parsed['quantity_constraints'] as $constraint)
                                            <span class="rounded-full bg-[#f3ead7] px-3 py-1 text-sm text-[#7b4a34]">
                                                {{ $constraint['ingredient'] }}
                                                @if (($constraint['quantity']['target'] ?? null) !== null)
                                                    target {{ number_format((float) $constraint['quantity']['target'], 1) }}
                                                @elseif (($constraint['quantity']['max'] ?? null) !== null)
                                                    max {{ number_format((float) $constraint['quantity']['max'], 1) }}
                                                @elseif (($constraint['quantity']['min'] ?? null) !== null)
                                                    min {{ number_format((float) $constraint['quantity']['min'], 1) }}
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-[#b48263]">None</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Nutrition Constraints</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($parsed['nutrition'] as $metric => $constraint)
                                            <span class="rounded-full bg-[#eaf5ea] px-3 py-1 text-sm text-[#3b6b43]">
                                                {{ $metric }}
                                                @if (isset($constraint['max']))
                                                    <= {{ $constraint['max'] }}
                                                @elseif (isset($constraint['min']))
                                                    >= {{ $constraint['min'] }}
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-[#b48263]">None</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Taste Intent</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($parsed['taste_preferences'] as $taste => $weight)
                                            <span class="rounded-full bg-[#ffe7d1] px-3 py-1 text-sm text-[#9b3d16]">
                                                {{ $taste }} {{ number_format((float) $weight, 1) }}
                                            </span>
                                        @empty
                                            <span class="text-[#b48263]">None</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Inventory Ranking Terms</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($parsed['inventory'] as $item)
                                            <span class="rounded-full bg-[#fcaf45]/18 px-3 py-1 text-sm text-[#9b4d09]">{{ $item }}</span>
                                        @empty
                                            <span class="text-[#b48263]">None</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm text-[#9a6a4c]">Mode</p>
                                    <p class="mt-1 text-lg text-[#4a2b1d]">{{ $parsed['strict'] ? 'Strict subset match' : 'Flexible match' }}</p>
                                </div>
                            </div>
                        @else
                            <div class="mt-6 rounded-[1.5rem] border border-dashed border-[#e6b990] px-5 py-8 text-sm leading-7 text-[#9a6a4c]">
                                Enter a search prompt to see how the backend parser interprets dish type, include terms, exclusions, meal hints, and pantry inventory.
                            </div>
                        @endif
                    </aside>
                    @endif
                </section>

                <section class="mt-12 {{ $viewMode === 'minimal' ? 'mx-auto w-full max-w-4xl' : '' }}">
                    <div class="mb-6 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Results</p>
                            <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-[#5a2414]">
                                @if ($query !== '')
                                    {{ $results->count() }} matches for "{{ $query }}"
                                @else
                                    Start with a recipe question
                                @endif
                            </h2>
                        </div>
                    </div>

                    @if ($query === '')
                        <div class="rounded-[2rem] border border-[#f1c39a] bg-white/70 p-8 text-[#9a6a4c]">
                            Search by ingredients, exclusions, or pantry inventory to see recipes here.
                        </div>
                    @elseif ($results->isEmpty())
                        <div class="rounded-[2rem] border border-[#f1c39a] bg-white/70 p-8 text-[#7b4a34]">
                            No recipes matched that prompt. Try a broader ingredient list or remove the strict constraint.
                        </div>
                    @else
                        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($results as $recipe)
                                <a
                                    href="https://cookdtv.com/recipes/{{ $recipe->id }}"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="group block rounded-[1.75rem] border border-[#f1c39a] bg-white/78 p-5 transition hover:-translate-y-1 hover:border-[#f77737]/45 hover:bg-[#fff7ef]"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.26em] text-[#a15a33]">Recipe #{{ $recipe->id }}</p>
                                            <h3 class="mt-2 font-['Space_Grotesk'] text-2xl font-semibold text-[#4a2b1d]">
                                                {{ $recipe->title }}
                                            </h3>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs uppercase tracking-[0.2em] text-[#a15a33]">Hybrid score</p>
                                            <p class="mt-2 text-xl font-semibold text-[#9b3d16]">{{ $recipe->display_score }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach (array_slice($recipe->ingredients ?? [], 0, 8) as $ingredient)
                                            <span class="rounded-full border border-[#f3c49d] bg-[#fff5ea] px-3 py-1 text-sm text-[#7b4a34]">
                                                {{ $ingredient }}
                                            </span>
                                        @endforeach
                                        @if (count($recipe->ingredients ?? []) > 8)
                                            <span class="rounded-full border border-[#f3c49d] bg-[#fff5ea] px-3 py-1 text-sm text-[#b48263]">
                                                +{{ count($recipe->ingredients) - 8 }} more
                                            </span>
                                        @endif
                                    </div>

                                    @if ($viewMode === 'detailed')
                                        <div class="mt-4 grid grid-cols-3 gap-2 text-xs text-[#9a6a4c]">
                                            <div class="rounded-2xl bg-[#fff5ea] px-3 py-2">
                                                Lexical<br>{{ number_format((float) ($recipe->total_score ?? 0), 2) }}
                                            </div>
                                            <div class="rounded-2xl bg-[#fff5ea] px-3 py-2">
                                                Semantic<br>{{ number_format((float) ($recipe->semantic_score ?? 0), 2) }}
                                            </div>
                                            <div class="rounded-2xl bg-[#fff5ea] px-3 py-2">
                                                Taste<br>{{ number_format((float) ($recipe->personalization_score ?? 0), 2) }}
                                            </div>
                                        </div>

                                        @if (is_array($recipe->score_breakdown ?? null))
                                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-[#9a6a4c]">
                                                <div class="rounded-2xl bg-[#fff5ea] px-3 py-2">
                                                    Qty<br>{{ number_format((float) ($recipe->score_breakdown['quantity'] ?? 0), 2) }}
                                                </div>
                                                <div class="rounded-2xl bg-[#fff5ea] px-3 py-2">
                                                    Nutrition<br>{{ number_format((float) ($recipe->score_breakdown['nutrition'] ?? 0), 2) }}
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if ($viewMode === 'detailed' && $context && $context->debug !== [])
                    <section class="mt-12">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Pipeline Debug</p>
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            @foreach ($context->debug as $entry)
                                <article class="rounded-[1.5rem] border border-[#f1c39a] bg-white/75 p-4">
                                    <p class="text-sm font-medium uppercase tracking-[0.22em] text-[#b84a1b]">{{ $entry['stage'] }}</p>
                                    <pre class="mt-3 overflow-x-auto text-xs leading-6 text-[#7b4a34]">{{ json_encode($entry['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </main>
        </div>
    </body>
</html>
