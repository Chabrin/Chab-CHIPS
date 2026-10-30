@php
    $navigationGroups = $superModuleMenus;
    if (!empty($menus)) {
        $navigationGroups['other'] = ['name' => 'Other applications', 'icon' => 'apps', 'modules' => $menus];
    }
    $navigationRouteName = request()->route()?->getName();
    $navigationDestination = config('navigation.route_aliases', [])[$navigationRouteName] ?? '';
@endphp
<nav id="focused-navigation" aria-label="Main navigation" data-current-destination="{{ $navigationDestination }}">
    <a class="fn-row fn-dashboard" href="{{ route('dashboard') }}"><span class="material-symbols-outlined" aria-hidden="true">dashboard</span>Dashboard</a>
    <label class="fn-search"><span class="material-symbols-outlined" aria-hidden="true">search</span><input type="search" placeholder="Find a module or page…" aria-label="Find a module or page" autocomplete="off"></label>
    <div class="fn-results" hidden aria-live="polite"></div>
    <div class="fn-browse">
        <div class="fn-categories"><p class="fn-caption">APPLICATIONS</p>
        @foreach($navigationGroups as $groupId => $group)
            <button type="button" class="fn-row" data-group="{{ $groupId }}" aria-expanded="false" aria-controls="fn-group-{{ $groupId }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $group['icon'] ?: 'folder' }}</span><span>{{ $group['name'] }}</span><span class="material-symbols-outlined fn-chevron" aria-hidden="true">chevron_right</span></button>
        @endforeach
        </div>
        @foreach($navigationGroups as $groupId => $group)
        <section id="fn-group-{{ $groupId }}" class="fn-group" data-name="{{ $group['name'] }}" hidden>
            <button type="button" class="fn-row fn-back"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to menu</button>
            <h2>{{ $group['name'] }}</h2>
            @foreach($group['modules'] as $moduleId => $module)
            <div class="fn-module">
                <button type="button" class="fn-row fn-module-toggle" aria-expanded="false" aria-controls="fn-module-{{ $groupId }}-{{ $moduleId }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $module['icon'] ?: 'folder' }}</span><span>{{ $module['module_name'] }}</span><span class="material-symbols-outlined fn-chevron" aria-hidden="true">expand_more</span></button>
                <div class="fn-pages" id="fn-module-{{ $groupId }}-{{ $moduleId }}" hidden>
                @foreach($module['submenus'] as $submenu)
                    @if (filled($submenu->sub_module_url) && !str_starts_with(trim($submenu->sub_module_url), '#'))
                        <a class="fn-page" href="{{ $submenu->sub_module_url }}" data-navigation-key="{{ $groupId }}:{{ $moduleId }}:{{ $submenu->sub_module_name }}" data-path="{{ $group['name'] }} › {{ $module['module_name'] }}">{{ $submenu->sub_module_name }}</a>
                    @else
                        <span class="fn-page fn-unavailable" aria-disabled="true">{{ $submenu->sub_module_name }}</span>
                    @endif
                @endforeach
                </div>
            </div>
            @endforeach
        </section>
        @endforeach
    </div>
</nav>
