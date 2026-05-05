<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-KPCQDR35');</script>
    <!-- End Google Tag Manager -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $query ? e($query) . ' – Zaika' : 'Zaika — Recipe Search for Real Kitchens' }}</title>
    <meta name="description" content="{{ $query ? 'Found ' . $results->count() . ' recipes for "' . e($query) . '" on Zaika. Search by ingredients, cravings, time, and taste.' : 'Find meals that fit your ingredients, cravings, time, and taste. Zaika is recipe search for real kitchens — no ads, no clutter.' }}">
    <meta name="robots" content="{{ $query ? 'noindex, follow' : 'index, follow' }}">
    <link rel="canonical" href="{{ $query ? route('home', ['q' => $query]) : route('home') }}">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Zaika">
    <meta property="og:url" content="{{ $query ? route('home', ['q' => $query]) : route('home') }}">
    <meta property="og:title" content="{{ $query ? e($query) . ' – Zaika' : 'Zaika — Recipe Search for Real Kitchens' }}">
    <meta property="og:description" content="Find meals that fit your ingredients, cravings, time, and taste. Zaika is recipe search for real kitchens — no ads, no clutter.">
    <meta property="og:image" content="{{ asset('images/landing-hero.png') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $query ? e($query) . ' – Zaika' : 'Zaika — Recipe Search for Real Kitchens' }}">
    <meta name="twitter:description" content="Find meals that fit your ingredients, cravings, time, and taste. Zaika is recipe search for real kitchens.">
    <meta name="twitter:image" content="{{ asset('images/landing-hero.png') }}">

    <!-- Structured data: WebSite + Sitelinks Searchbox -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Zaika",
        "url": "{{ route('home') }}",
        "description": "Recipe search for real kitchens — search by ingredients, craving, time, and taste.",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "{{ route('home') }}?q={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>

    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f4ef] text-[#17201b] antialiased">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KPCQDR35"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@php
    $suggestions = [
        ['Quick', 'quick breakfast under 15 min'],
        ['High protein', 'high protein lunch with chicken or paneer'],
        ['Low oil', 'low oil dinner vegetarian'],
        ['No garlic', 'comfort food no garlic no onion'],
        ['Tangy', 'spicy tangy curry'],
        ['Pantry', 'dinner with rice chickpeas onion tomato'],
    ];
@endphp

<header class="sticky top-0 z-50 border-b border-[#d7ddd3]/70 bg-[#f7f4ef]/90 backdrop-blur-xl">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8 lg:px-10">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-full bg-[#25352d] font-['Space_Grotesk'] text-sm font-bold text-white shadow-sm shadow-[#25352d]/15">Z</span>
            <span class="font-['Space_Grotesk'] text-xl font-semibold tracking-tight text-[#25352d]">Zaika</span>
        </a>
        <a href="{{ route('home', ['q' => 'simple dinner under 30 min']) }}"
           class="hidden rounded-full border border-[#cbd6cc] bg-white/65 px-4 py-2 text-sm font-medium text-[#52675a] transition hover:border-[#8ea596] hover:text-[#25352d] sm:inline-flex">
            Dinner under 30
        </a>
    </nav>
</header>

<main>
    <section class="relative isolate overflow-hidden border-b border-[#dde3da]">
        <div class="absolute inset-0 -z-20 bg-cover bg-[center_right_28%]"
             style="background-image: url('{{ asset('images/landing-hero.png') }}');"></div>
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(90deg,_rgba(247,244,239,0.98)_0%,_rgba(247,244,239,0.94)_34%,_rgba(247,244,239,0.70)_58%,_rgba(247,244,239,0.20)_100%)]"></div>
        <div class="absolute inset-x-0 bottom-0 -z-10 h-32 bg-gradient-to-t from-[#f7f4ef] to-transparent"></div>

        <div class="mx-auto flex min-h-[calc(100svh-12rem)] max-w-7xl items-center px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
            <div class="w-full max-w-3xl">
                <p class="mb-4 inline-flex rounded-full border border-[#cbd6cc] bg-white/60 px-4 py-2 text-sm font-semibold text-[#5e7667] shadow-sm shadow-[#25352d]/5 backdrop-blur">
                    Recipe search for real kitchens
                </p>
                <h1 class="font-['Space_Grotesk'] text-5xl font-bold leading-[0.95] tracking-tight text-[#1f2c25] sm:text-6xl lg:text-7xl">
                    Zaika
                </h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-[#526156] sm:text-xl">
                    Find meals that fit your ingredients, cravings, time, and taste without fighting a noisy recipe page.
                </p>

                <form method="GET" action="{{ route('home') }}" class="mt-8 w-full max-w-2xl">
                    <label for="q" class="sr-only">Search recipes</label>
                    <div class="flex flex-col gap-3 rounded-[1.5rem] border border-white/80 bg-white/80 p-2 shadow-[0_24px_70px_rgba(31,44,37,0.12)] backdrop-blur md:flex-row md:items-center">
                        <input id="q" name="q" type="search"
                               value="{{ $query }}"
                               placeholder="Try: chutney without coconut"
                               class="min-h-14 flex-1 rounded-[1.1rem] border border-transparent bg-transparent px-4 text-base font-medium text-[#1f2c25] outline-none transition placeholder:text-[#8b968c] focus:border-[#b8c7ba] focus:bg-white/65 md:text-lg">
                        <button type="submit"
                                class="min-h-14 rounded-[1.1rem] bg-[#25352d] px-7 text-base font-semibold text-white shadow-sm shadow-[#25352d]/20 transition hover:bg-[#33463c] focus:outline-none focus:ring-4 focus:ring-[#91aa99]/35">
                            Search
                        </button>
                    </div>
                </form>

                <div class="mt-5 flex max-w-3xl flex-wrap gap-2">
                    @foreach ($suggestions as [$label, $q])
                        <a href="{{ route('home', ['q' => $q]) }}"
                           class="rounded-full border border-[#d7ddd3] bg-[#fbfaf7]/75 px-4 py-2 text-sm font-semibold text-[#52675a] shadow-sm shadow-[#25352d]/5 backdrop-blur transition hover:border-[#9eb4a6] hover:bg-white hover:text-[#25352d]">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f7f4ef] px-5 py-8 sm:px-8 lg:px-10">
        <div class="mx-auto grid max-w-7xl gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-[#dbe2d8] bg-white/55 p-5">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#7e927f]">Pantry</p>
                <p class="mt-2 text-lg font-semibold text-[#25352d]">Rice, chickpeas, onion, tomato</p>
            </div>
            <div class="rounded-lg border border-[#dbe2d8] bg-white/55 p-5">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#b06c5a]">Craving</p>
                <p class="mt-2 text-lg font-semibold text-[#25352d]">Spicy, tangy, comforting</p>
            </div>
            <div class="rounded-lg border border-[#dbe2d8] bg-white/55 p-5">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#8d7b48]">Time</p>
                <p class="mt-2 text-lg font-semibold text-[#25352d]">Dinner under 30 minutes</p>
            </div>
        </div>
    </section>

    @if ($results->isNotEmpty())
        <section class="bg-[#eef2ec] px-5 py-12 sm:px-8 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <div class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#6f8375]">Results</p>
                        <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold tracking-tight text-[#1f2c25] sm:text-4xl">
                            {{ $results->count() }} matches for "{{ $query }}"
                        </h2>
                    </div>
                    <a href="{{ route('home') }}" class="text-sm font-semibold text-[#596e60] transition hover:text-[#25352d]">
                        Clear search
                    </a>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($results as $recipe)
                        @php
                            $cookdId = $recipe->raw_json['id'] ?? $recipe->id;
                            $cuisineTags = array_slice((array) ($recipe->raw_json['cuisines_name'] ?? []), 0, 1);
                            $ingredients = array_values((array) ($recipe->ingredients ?? []));
                            $explanations = [];

                            if ($recipe->cooking_time && $recipe->cooking_time <= 20) {
                                $explanations[] = 'Quick ' . $recipe->cooking_time . ' min';
                            }

                            if ($recipe->dietary) {
                                $explanations[] = ucfirst(str_replace('_', ' ', $recipe->dietary));
                            }

                            $tp = $recipe->taste_profile ?? [];
                            if (($tp['spicy'] ?? 0) >= 0.65) {
                                $explanations[] = 'Spicy';
                            } elseif (($tp['sweet'] ?? 0) >= 0.65) {
                                $explanations[] = 'Sweet';
                            } elseif (($tp['tangy'] ?? 0) >= 0.65) {
                                $explanations[] = 'Tangy';
                            }

                            if ($recipe->protein && $recipe->protein >= 20) {
                                $explanations[] = 'High protein';
                            } elseif ($recipe->fat !== null && $recipe->fat <= 8) {
                                $explanations[] = 'Low oil';
                            }
                        @endphp

                        <a href="https://cookdtv.com/recipes/{{ $cookdId }}" target="_blank" rel="noreferrer"
                           class="group flex min-h-64 flex-col rounded-lg border border-[#d4ddd2] bg-[#fbfaf7] p-5 shadow-sm shadow-[#25352d]/5 transition duration-200 hover:-translate-y-0.5 hover:border-[#9fb49f] hover:shadow-[0_18px_40px_rgba(37,53,45,0.10)]">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    @if ($recipe->cooking_time)
                                        <p class="mb-2 text-sm font-semibold text-[#b06c5a]">{{ $recipe->cooking_time }} min</p>
                                    @endif
                                    <h3 class="font-['Space_Grotesk'] text-xl font-semibold leading-snug text-[#1f2c25] transition group-hover:text-[#31483d]">
                                        {{ $recipe->title }}
                                    </h3>
                                </div>
                                <span class="mt-1 shrink-0 rounded-full border border-[#d7ddd3] px-3 py-1 text-xs font-bold text-[#6b7f71] transition group-hover:border-[#aebfaf]">
                                    Open
                                </span>
                            </div>

                            @if (!empty($cuisineTags) || $recipe->dietary)
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach ($cuisineTags as $c)
                                        <span class="rounded-full bg-[#eef4ed] px-3 py-1 text-xs font-semibold text-[#52675a]">{{ $c }}</span>
                                    @endforeach
                                    @if ($recipe->dietary)
                                        <span class="rounded-full bg-[#f6e9e3] px-3 py-1 text-xs font-semibold text-[#9a5a4c]">{{ ucfirst(str_replace('_', ' ', $recipe->dietary)) }}</span>
                                    @endif
                                </div>
                            @endif

                            @if (!empty($ingredients))
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach (array_slice($ingredients, 0, 4) as $ingredient)
                                        <span class="rounded-full border border-[#e1e2da] bg-white/70 px-3 py-1 text-xs font-medium text-[#687568]">{{ ucfirst($ingredient) }}</span>
                                    @endforeach
                                    @if (count($ingredients) > 4)
                                        <span class="rounded-full border border-[#e1e2da] bg-[#f3f1ea] px-3 py-1 text-xs font-semibold text-[#7b7668]">+{{ count($ingredients) - 4 }}</span>
                                    @endif
                                </div>
                            @endif

                            @if (!empty($explanations))
                                <p class="mt-auto pt-5 text-sm font-semibold text-[#52675a]">
                                    {{ implode(' / ', array_slice($explanations, 0, 3)) }}
                                </p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif ($query !== '')
        <section class="bg-[#eef2ec] px-5 py-16 text-center sm:px-8 lg:px-10">
            <div class="mx-auto max-w-2xl rounded-lg border border-[#d4ddd2] bg-[#fbfaf7] p-8 shadow-sm shadow-[#25352d]/5">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#8d7b48]">No matches</p>
                <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-semibold tracking-tight text-[#1f2c25]">Try another flavor trail</h2>
                <p class="mt-3 text-base leading-7 text-[#647166]">A shorter query or one of the quick chips usually gets Zaika moving again.</p>
            </div>
        </section>
    @endif
</main>

</body>
</html>
