<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BladeRouteLinksTest extends TestCase
{
    public function test_guest_can_find_the_public_product_page_and_request_access(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('marketing.dentiflow'));

        $this->get(route('marketing.dentiflow'))
            ->assertOk()
            ->assertSee('Solicitar acceso')
            ->assertSee(route('register'));
    }

    public function test_all_named_routes_referenced_by_blade_views_exist(): void
    {
        $missing = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all("/route\\(\\s*['\"]([^'\"]+)['\"]/", $contents ?: '', $matches);

            foreach (array_unique($matches[1]) as $routeName) {
                if (! Route::has($routeName)) {
                    $missing[] = $file->getPathname().': '.$routeName;
                }
            }
        }

        $this->assertSame([], $missing, "Se encontraron enlaces a rutas inexistentes:\n".implode("\n", $missing));
    }
}
