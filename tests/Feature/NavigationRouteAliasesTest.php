<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class NavigationRouteAliasesTest extends TestCase
{
    public function test_every_explicit_navigation_alias_names_an_existing_get_page_and_menu_route(): void
    {
        $routes = app('router')->getRoutes();
        foreach (config('navigation.route_aliases') as $name => $destination) {
            $page = $routes->getByName($name);
            $this->assertNotNull($page, "Missing page route {$name}");
            $this->assertContains('GET', $page->methods(), "{$name} is not a GET page");
            $this->assertStringNotContainsString('/ajax/', '/'.$page->uri());
            $this->assertFalse(str_contains($destination, '{'), "{$name} points to a parameterized menu URL");
            $menuRoute = $routes->match(Request::create($destination, 'GET'));
            $this->assertContains('GET', $menuRoute->methods(), "Missing menu route for {$name}");
        }
    }

    public function test_alias_hint_does_not_add_a_menu_item_that_was_not_provided(): void
    {
        $route = app('router')->getRoutes()->getByName('discipline.tracker.show');
        request()->setRouteResolver(fn () => $route);

        $html = view('layouts.focused-navigation', [
            'superModuleMenus' => [],
            'menus' => [],
        ])->render();

        $this->assertStringContainsString('data-current-destination="/hr/discipline/tracker"', $html);
        $this->assertStringNotContainsString('href="/hr/discipline/tracker"', $html);
    }
}
