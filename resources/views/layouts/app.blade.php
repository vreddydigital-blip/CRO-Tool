<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'CRO Intelligence' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body class="h-full bg-slate-50 font-sans antialiased">
<div x-data="{ open: false }" class="min-h-full">

    <div class="lg:hidden sticky top-0 z-40 flex items-center justify-between bg-[#0B1220] px-4 py-3">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-400 text-sm font-bold text-[#0B1220]">CI</span>
            <span class="text-sm font-semibold text-white">CRO Intelligence</span>
        </a>
        <button @click="open = !open" class="rounded-md p-2 text-slate-300 hover:bg-white/10" aria-label="Toggle navigation">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <aside x-cloak x-show="open || window.innerWidth >= 1024"
           class="fixed inset-y-0 left-0 z-50 w-64 transform bg-[#0B1220] transition-transform lg:translate-x-0"
           :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-full flex-col">
            <div class="hidden items-center gap-2.5 px-5 py-5 lg:flex">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-teal-400 text-sm font-bold text-[#0B1220]">CI</span>
                <div>
                    <div class="text-sm font-semibold leading-tight text-white">CRO Intelligence</div>
                    <div class="text-[11px] text-slate-400">Conversion analysis</div>
                </div>
            </div>

            <nav class="mt-2 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
                @php
                    $nav = [
                        ['dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
                        ['projects.index', 'Projects', 'M3 7h18M3 12h18M3 17h18'],
                        ['findings.index', 'CRO Findings', 'M12 9v4m0 4h.01M10.3 3.9L2.4 17a2 2 0 001.7 3h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
                        ['recommendations.index', 'Recommendations', 'M9 12l2 2 4-4M12 3l9 4v6c0 5-4 8-9 9-5-1-9-4-9-9V7l9-4z'],
                        ['history.index', 'Scan History', 'M12 8v4l3 2M3 12a9 9 0 1018 0 9 9 0 00-18 0z'],
                        ['reports.index', 'Reports', 'M9 12h6m-6 4h6M7 3h7l5 5v13H7V3z'],
                        ['settings.index', 'Settings', 'M10.3 4.3a2 2 0 013.4 0l.6 1a2 2 0 002.3 1l1.1-.3a2 2 0 012.4 2.4l-.3 1.1a2 2 0 001 2.3l1 .6a2 2 0 010 3.4l-1 .6a2 2 0 00-1 2.3l.3 1.1a2 2 0 01-2.4 2.4l-1.1-.3a2 2 0 00-2.3 1l-.6 1a2 2 0 01-3.4 0l-.6-1a2 2 0 00-2.3-1l-1.1.3a2 2 0 01-2.4-2.4l.3-1.1a2 2 0 00-1-2.3l-1-.6a2 2 0 010-3.4l1-.6a2 2 0 001-2.3l-.3-1.1a2 2 0 012.4-2.4l1.1.3a2 2 0 002.3-1l.6-1z'],
                    ];
                @endphp

                @foreach ($nav as [$route, $label, $path])
                    @php $active = request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)); @endphp
                    <a href="{{ route($route) }}"
                       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                              {{ $active ? 'bg-teal-400/10 font-medium text-teal-300' : 'text-slate-400 hover:bg-white/5 hover:text-slate-100' }}">
                        <svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/>
                        </svg>
                        {{ $label }}
                    </a>
                @endforeach

                <div class="!mt-4 border-t border-white/10 pt-3">
                    <a href="{{ route('projects.create') }}"
                       class="flex items-center justify-center gap-2 rounded-lg bg-teal-400 px-3 py-2.5 text-sm font-semibold text-[#0B1220] transition hover:bg-teal-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                        </svg>
                        New Project
                    </a>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-700 text-xs font-semibold text-slate-200">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-xs font-medium text-slate-200">{{ auth()->user()->name }}</div>
                        <div class="truncate text-[11px] text-slate-500">{{ auth()->user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded p-1.5 text-slate-500 hover:bg-white/10 hover:text-slate-200" title="Log out">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div x-cloak x-show="open" @click="open = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

    <main class="lg:pl-64">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @if (session('status'))
                <div class="mb-5 flex items-start gap-3 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
