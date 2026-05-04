<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zaika</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|instrument-sans:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fff7ed] text-[#3f2415]">

{{-- ─── Top Nav ─────────────────────────────────────────────────── --}}
<nav class="sticky top-0 z-40 border-b border-[#f1c39a]/40 bg-[#fff7ed]/95 backdrop-blur-sm">
    <div class="mx-auto flex max-w-7xl items-center px-6 py-3">
        <span class="font-['Space_Grotesk'] text-xl font-bold tracking-tight text-[#4a2b1d]">Zaika</span>
    </div>
</nav>

<div class="relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(252,175,69,0.34),_transparent_32%),radial-gradient(circle_at_82%_18%,_rgba(247,119,55,0.26),_transparent_24%),linear-gradient(180deg,_#fff7ed_0%,_#ffe4c7_48%,_#ffd2ad_100%)]"></div>
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#f77737]/50 to-transparent"></div>

    <main class="relative mx-auto max-w-7xl px-6 py-10 sm:px-10 lg:px-16">

        {{-- Search form + suggestion chips --}}
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

        {{-- Results --}}
        @if ($results->isNotEmpty())
        <section class="mt-12 mx-auto w-full max-w-4xl">
            <div class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#a15a33]">Results</p>
                <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-[#5a2414]">
                    {{ $results->count() }} matches for "{{ $query }}"
                </h2>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($results as $recipe)
                    @php
                        $cookdId     = $recipe->raw_json['id'] ?? $recipe->id;
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

                    <a href="https://cookdtv.com/recipes/{{ $cookdId }}" target="_blank" rel="noreferrer"
                       class="group flex flex-col rounded-[1.5rem] border border-[#f1c39a]/80 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#f77737]/50 hover:shadow-[0_8px_24px_rgba(247,119,55,0.10)]">

                        <div class="flex-1 p-4">
                            @if ($recipe->cooking_time)
                            <p class="mb-1.5 text-[11px] font-medium text-[#9b3d16]">⏱ {{ $recipe->cooking_time }} min</p>
                            @endif
                            <h3 class="font-['Space_Grotesk'] text-[15px] font-semibold leading-snug text-[#2d1810]">{{ $recipe->title }}</h3>

                            @if (!empty($cuisineTags) || $recipe->dietary)
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach ($cuisineTags as $c)
                                <span class="rounded-full bg-[#fff3e8] px-2.5 py-0.5 text-[11px] font-medium text-[#7b4a34]">{{ $c }}</span>
                                @endforeach
                                @if ($recipe->dietary)
                                <span class="rounded-full bg-[#eaf5ea] px-2.5 py-0.5 text-[11px] font-medium text-[#3b6b43]">{{ ucfirst(str_replace('_', ' ', $recipe->dietary)) }}</span>
                                @endif
                            </div>
                            @endif

                            @if (!empty($recipe->ingredients))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach (array_slice($recipe->ingredients ?? [], 0, 4) as $ingredient)
                                <span class="rounded-full bg-[#faf5f0] px-2 py-0.5 text-[11px] text-[#9a6a4c] ring-1 ring-inset ring-[#f1c39a]/60">{{ ucfirst($ingredient) }}</span>
                                @endforeach
                                @if (count($recipe->ingredients ?? []) > 4)
                                <span class="rounded-full bg-[#f5f0eb] px-2 py-0.5 text-[11px] text-[#9a7a6a]">+{{ count($recipe->ingredients) - 4 }}</span>
                                @endif
                            </div>
                            @endif

                            @if (!empty($explanations))
                            <p class="mt-2.5 text-[11px] font-medium text-[#9b5a30]">✓ {{ implode(' · ', array_slice($explanations, 0, 2)) }}</p>
                            @endif
                        </div>

                    </a>
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

    </main>
</div>

</body>
</html>
