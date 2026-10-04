@php $contextId = \App\Support\ProjectContext::id(); @endphp
<nav aria-label="Module" class="ui-modules">
    @foreach(config('ui.navigation') as $group => $items)
        @php $active = collect($items)->contains(fn($item) => request()->routeIs($item[0])) || ($group === 'Baustellen' && request()->routeIs('projects.show')); @endphp
        <details class="ui-nav-group" @if($active) open @endif>
            <summary>{{ $group }} <x-ui-icon name="chevron" class="ui-nav-chevron" /></summary>
            <div class="ui-nav-items">
                @foreach($items as [$route, $label, $icon])
                    @php $selected = request()->routeIs($route) || ($route === 'dashboard' && request()->routeIs('projects.show')); @endphp
                    <a href="{{ \App\Support\ProjectContext::url($route, $contextId) }}" wire:navigate @if($selected) aria-current="page" @endif class="ui-nav-item">
                        <x-ui-icon :name="$icon" /> <span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </details>
    @endforeach
</nav>
