@props(['items' => []])

@if (count($items))
    <nav aria-label="Breadcrumb" class="mb-4 text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            @foreach ($items as $i => $item)
                @if ($i > 0)<li aria-hidden="true" class="text-slate-300">/</li>@endif
                <li>
                    @if (! empty($item['url']) && ! $loop->last)
                        <a href="{{ $item['url'] }}" class="hover:text-slate-800 hover:underline">{{ $item['label'] }}</a>
                    @else
                        <span class="font-medium text-slate-800">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
