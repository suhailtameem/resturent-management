<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f5ef">
    <title>قائمة الطعام | {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#f8f5ef] font-sans text-stone-900 antialiased">
    <header class="relative overflow-hidden bg-[#211b17] text-white">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-amber-700/30 via-transparent to-transparent"></div>
        <div class="relative mx-auto flex max-w-7xl flex-col gap-10 px-5 py-10 sm:px-8 sm:py-14">
            <a href="{{ url('/') }}" class="w-fit text-sm font-semibold tracking-wide text-amber-300">
                {{ config('app.name', 'مطعمنا') }}
            </a>
            <div class="max-w-2xl">
                <p class="mb-3 text-sm font-bold text-amber-300">طازج ومحضّر بعناية</p>
                <h1 class="text-4xl font-black leading-tight sm:text-6xl">قائمة الطعام</h1>
                <p class="mt-4 max-w-xl text-base leading-8 text-stone-300 sm:text-lg">
                    اكتشف أطباقنا واختر ما يناسبك من قائمتنا اليومية.
                </p>
            </div>
            @if ($categories->isNotEmpty())
                <nav aria-label="أقسام قائمة الطعام" class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($categories as $category)
                        <a href="#category-{{ $category->id }}"
                            class="shrink-0 rounded-full border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-amber-300 hover:bg-amber-300 hover:text-stone-950">
                            {{ $category->name_ar }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
        @forelse ($categories as $category)
            <section id="category-{{ $category->id }}" class="mb-14 scroll-mt-8 last:mb-0">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-700">من قائمتنا</p>
                        <h2 class="text-2xl font-black sm:text-3xl">{{ $category->name_ar }}</h2>
                        @if ($category->name_en)
                            <p class="mt-1 text-sm text-stone-500" dir="ltr">{{ $category->name_en }}</p>
                        @endif
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-500 shadow-sm">
                        {{ $category->products->count() }} أصناف
                    </span>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($category->products as $product)
                        <button type="button" data-product-details-target="product-details-{{ $product->id }}"
                            aria-haspopup="dialog"
                            class="group block w-full overflow-hidden rounded-3xl border border-stone-200/80 bg-white text-right shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-400/50">
                            <div class="relative aspect-[16/10] overflow-hidden bg-stone-100">
                                @if ($product->menu_image_url)
                                    <img src="{{ $product->menu_image_url }}" alt="{{ $product->name_ar }}"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                                @else
                                    <div class="flex h-full items-center justify-center bg-gradient-to-br from-amber-100 via-orange-50 to-stone-100">
                                        <svg class="h-16 w-16 text-amber-700/50" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 11.5a8 8 0 0 1 16 0H4Z" stroke="currentColor" stroke-width="1.5"/>
                                            <path d="M3 12h18M5 15h14M7 18h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <path d="M8 7.5c.5-1 1.3-1.5 2.3-1.5M14 6c.8.3 1.4.8 1.8 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                @endif

                                @if ($product->is_featured || $product->is_best_seller)
                                    <span class="absolute right-4 top-4 rounded-full bg-amber-300 px-3 py-1 text-xs font-bold text-stone-950">
                                        {{ $product->badge_text_ar ?: ($product->is_best_seller ? 'الأكثر طلبًا' : 'مميز') }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex h-full flex-col gap-4 p-5">
                                <div>
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <h3 class="text-xl font-extrabold">{{ $product->name_ar }}</h3>
                                        @if ($product->name_en)
                                            <span class="text-sm text-stone-400" dir="ltr">{{ $product->name_en }}</span>
                                        @endif
                                    </div>
                                    @if ($product->description_ar)
                                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-stone-500">{{ $product->description_ar }}</p>
                                    @endif
                                </div>

                                <div class="mt-auto flex items-end justify-between gap-3 border-t border-stone-100 pt-4">
                                    <div>
                                        @if ($product->variants->isNotEmpty())
                                            <p class="mb-1 text-xs text-stone-400">يبدأ من</p>
                                        @endif
                                        <p class="text-lg font-black text-amber-800">
                                            {{ number_format($product->menu_starting_price, 2) }}
                                            <span class="text-xs font-bold">ر.س</span>
                                        </p>
                                    </div>
                                    @if ($product->variants->isNotEmpty())
                                        <span class="rounded-full bg-stone-100 px-3 py-1.5 text-xs font-semibold text-stone-600">
                                            {{ $product->variants->count() }} أحجام
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </button>

                        @php
                            $optionGroups = $product->optionGroups
                                ->map(fn ($relation) => $relation->optionGroup)
                                ->filter();
                        @endphp
                        <template id="product-details-{{ $product->id }}">
                            <div class="overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                                <div class="relative">
                                    @if ($product->menu_image_url)
                                        <img src="{{ $product->menu_image_url }}" alt="{{ $product->name_ar }}"
                                            class="h-56 w-full object-cover sm:h-72">
                                    @else
                                        <div class="flex h-56 items-center justify-center bg-gradient-to-br from-amber-100 via-orange-50 to-stone-100 sm:h-72">
                                            <span class="text-6xl" aria-hidden="true">🍽</span>
                                        </div>
                                    @endif
                                    <button type="button" data-product-details-close aria-label="إغلاق التفاصيل"
                                        class="absolute left-4 top-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/90 text-2xl text-stone-700 shadow-lg backdrop-blur transition hover:bg-white focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-400/60">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                    @if ($product->is_featured || $product->is_best_seller)
                                        <span class="absolute right-4 top-4 rounded-full bg-amber-300 px-4 py-2 text-sm font-bold text-stone-950 shadow">
                                            {{ $product->badge_text_ar ?: ($product->is_best_seller ? 'الأكثر طلبًا' : 'مميز') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="max-h-[55dvh] overflow-y-auto p-5 sm:p-7">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h2 class="text-2xl font-black sm:text-3xl">{{ $product->name_ar }}</h2>
                                            @if ($product->name_en)
                                                <p class="mt-1 text-sm text-stone-500" dir="ltr">{{ $product->name_en }}</p>
                                            @endif
                                        </div>
                                        <p class="rounded-2xl bg-amber-50 px-4 py-2 text-lg font-black text-amber-800">
                                            {{ number_format($product->menu_starting_price, 2) }}
                                            <span class="text-xs">ر.س</span>
                                        </p>
                                    </div>

                                    @if ($product->description_ar || $product->description_en)
                                        <div class="mt-5 space-y-2 text-sm leading-7 text-stone-600">
                                            @if ($product->description_ar)
                                                <p>{{ $product->description_ar }}</p>
                                            @endif
                                            @if ($product->description_en)
                                                <p dir="ltr" class="text-left">{{ $product->description_en }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($product->variants->isNotEmpty())
                                        <section class="mt-7">
                                            <h3 class="mb-3 text-lg font-extrabold">الأحجام والأسعار</h3>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                @foreach ($product->variants as $variant)
                                                    <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <p class="font-bold">{{ $variant->name_ar }}</p>
                                                                @if ($variant->name_en)
                                                                    <p class="mt-0.5 text-xs text-stone-500" dir="ltr">{{ $variant->name_en }}</p>
                                                                @endif
                                                            </div>
                                                            <p class="shrink-0 font-black text-amber-800">
                                                                {{ number_format($variant->menu_price, 2) }} <span class="text-xs">ر.س</span>
                                                            </p>
                                                        </div>
                                                        <p class="mt-2 text-xs text-stone-500">{{ $variant->piece_count }} قطع</p>
                                                        @if ($variant->portion_description_ar)
                                                            <p class="mt-1 text-xs leading-5 text-stone-500">{{ $variant->portion_description_ar }}</p>
                                                        @endif
                                                        @if ($variant->portion_description_en)
                                                            <p class="mt-1 text-xs leading-5 text-stone-500" dir="ltr">{{ $variant->portion_description_en }}</p>
                                                        @endif
                                                        @if ($variant->is_default)
                                                            <span class="mt-3 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">الخيار الموصى به</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endif

                                    @if ($optionGroups->isNotEmpty())
                                        <section class="mt-7 space-y-4">
                                            <h3 class="text-lg font-extrabold">الخيارات والإضافات</h3>
                                            @foreach ($optionGroups as $group)
                                                <div class="rounded-2xl border border-stone-200 p-4">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h4 class="font-bold">{{ $group->name_ar }}</h4>
                                                        @if ($group->name_en)
                                                            <span class="text-xs text-stone-500" dir="ltr">{{ $group->name_en }}</span>
                                                        @endif
                                                        <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs text-stone-600">
                                                            {{ $group->type === 'single' ? 'اختيار واحد' : 'اختيارات متعددة' }}
                                                        </span>
                                                        @if ($group->is_required)
                                                            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">مطلوب</span>
                                                        @endif
                                                    </div>
                                                    <div class="mt-3 divide-y divide-stone-100">
                                                        @foreach ($group->options as $option)
                                                            <div class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                                                <div>
                                                                    <p class="font-semibold {{ $option->is_available ? 'text-stone-800' : 'text-stone-400' }}">
                                                                        {{ $option->name_ar }}
                                                                        @if (! $option->is_available)
                                                                            <span class="text-xs font-normal">(غير متاح)</span>
                                                                        @endif
                                                                    </p>
                                                                    @if ($option->name_en)
                                                                        <p class="mt-0.5 text-xs text-stone-500" dir="ltr">{{ $option->name_en }}</p>
                                                                    @endif
                                                                    @if ($option->description_ar)
                                                                        <p class="mt-1 text-xs leading-5 text-stone-500">{{ $option->description_ar }}</p>
                                                                    @endif
                                                                    @if ($option->description_en)
                                                                        <p class="mt-1 text-xs leading-5 text-stone-500" dir="ltr">{{ $option->description_en }}</p>
                                                                    @endif
                                                                </div>
                                                                <span class="shrink-0 text-sm font-bold text-stone-700">
                                                                    @if ((float) $option->price > 0)
                                                                        +{{ number_format((float) $option->price, 2) }} ر.س
                                                                    @else
                                                                        مجاني
                                                                    @endif
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </section>
                                    @endif
                                </div>
                            </div>
                        </template>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="rounded-3xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-2xl" aria-hidden="true">🍽</div>
                <h2 class="mt-5 text-2xl font-black">القائمة غير متاحة حاليًا</h2>
                <p class="mt-2 text-stone-500">يرجى العودة قريبًا للاطلاع على أطباقنا.</p>
            </section>
        @endforelse
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <span class="font-semibold text-stone-700">{{ config('app.name', 'مطعمنا') }}</span>
            <span>جميع الأسعار بالريال السعودي</span>
        </div>
    </footer>

    <dialog id="product-details-modal" aria-label="تفاصيل المنتج"
        class="m-auto max-h-[92dvh] w-[calc(100%-1.5rem)] max-w-2xl overflow-y-auto rounded-[2rem] border-0 bg-transparent p-0 text-stone-900 shadow-none backdrop:bg-stone-950/55 backdrop:backdrop-blur-sm">
        <div class="px-0.5 pb-0.5 pt-2">
            <div class="mx-auto mb-2 h-1.5 w-12 rounded-full bg-white/70" aria-hidden="true"></div>
            <div id="product-details-content"></div>
        </div>
    </dialog>

    <script>
        const productDetailsModal = document.getElementById('product-details-modal');
        const productDetailsContent = document.getElementById('product-details-content');

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-product-details-target]');

            if (trigger) {
                const template = document.getElementById(trigger.dataset.productDetailsTarget);

                if (template) {
                    productDetailsContent.replaceChildren(template.content.cloneNode(true));
                    productDetailsModal.showModal();
                }
            }

            if (event.target.closest('[data-product-details-close]')) {
                productDetailsModal.close();
            }
        });

        productDetailsModal.addEventListener('click', (event) => {
            if (event.target === productDetailsModal) {
                productDetailsModal.close();
            }
        });

        productDetailsModal.addEventListener('close', () => {
            productDetailsContent.replaceChildren();
        });
    </script>
</body>
</html>
