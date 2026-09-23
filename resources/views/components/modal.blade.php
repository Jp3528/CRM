@props(['title' => 'Diálogo', 'name' => 'modal'])

<div
    x-data="{ open: false }"
    x-on:open-{{ $name }}.window="open = true"
    x-on:close-{{ $name }}.window="open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    <div class="absolute inset-0 bg-slate-900/50" x-on:click="open = false"></div>
    <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
        <div class="mt-3 text-sm text-slate-600">{{ $slot }}</div>
        @isset($footer)
            <div class="mt-5 flex justify-end gap-2">{{ $footer }}</div>
        @endisset
    </div>
</div>
