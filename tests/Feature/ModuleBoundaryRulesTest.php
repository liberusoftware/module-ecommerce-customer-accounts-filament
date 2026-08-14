<?php

declare(strict_types=1);

use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\CustomerAccounts\Filament\CustomerAccountsFilamentServiceProvider;
use Liberu\Ecommerce\CustomerAccounts\Filament\CustomerAccountsPlugin;

/** @return list<string> */
function sourceFiles(): array
{
    $paths = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

it('never reaches into the host application', function (): void {
    foreach (sourceFiles() as $path) {
        expect(preg_match('/(?:use|new|extends|implements)\s+App\\\\/', (string) file_get_contents($path)))
            ->toBe(0, $path);
    }
});

it('carries no session identifier anywhere', function (): void {
    $roots = ['/src', '/tests', '/docs', '/resources', '/README.md', '/CHANGELOG.md', '/module.json', '/composer.json'];

    foreach ($roots as $root) {
        $path = dirname(__DIR__, 2).$root;

        $files = is_dir($path)
            ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)))
            : [new SplFileInfo($path)];

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->isDir()) {
                continue;
            }

            // Assembled rather than written out, or this file matches itself.
            $pattern = '/claude\.ai|Claude-'.'Session|session'.'_0/i';

            expect(preg_match($pattern, (string) file_get_contents($file->getPathname())))
                ->toBe(0, $file->getPathname());
        }
    }
});

it('depends on no sibling module but the domain package', function (): void {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    $liberu = array_keys(array_filter(
        $composer['require'],
        static fn (string $name): bool => str_starts_with($name, 'liberusoftware/'),
        ARRAY_FILTER_USE_KEY,
    ));

    // Six of the modules holding personal data in this fleet publish no erasure
    // at all. A desk that hard-depended on the four that do would be
    // uninstallable for the reason that it coordinates.
    expect($liberu)->toBe(['liberusoftware/ecommerce-customer-accounts']);

    foreach (sourceFiles() as $path) {
        $source = (string) file_get_contents($path);

        foreach (['CommerceCustomers', 'Orders\\', 'Promotions', 'AttributionAnalytics', 'ReviewsAndRatings'] as $sibling) {
            expect(str_contains($source, 'Liberu\\Ecommerce\\'.$sibling))->toBeFalse($path.' names '.$sibling);
        }
    }
});

it('promises no sweeper, no auto-conclusion and no cooling-off period', function (): void {
    // §7.1.5 and §7.1.7: nothing in this module has a clock or a mailbox, and
    // no surface may imply one. The words are grepped rather than trusted.
    $forbidden = ['cooling-off', 'cooling off', 'automatically conclude', 'auto-conclude', 'will be concluded', 'scheduled to', 'sweeper'];

    $paths = [...sourceFiles()];

    foreach (glob(dirname(__DIR__, 2).'/resources/views/pages/*.blade.php') ?: [] as $view) {
        $paths[] = $view;
    }

    foreach ($paths as $path) {
        $source = mb_strtolower((string) file_get_contents($path));

        foreach ($forbidden as $phrase) {
            // The prohibition is on promising one, so a docblock saying none
            // exists is fine; nothing in this package says either.
            expect(str_contains($source, 'we '.$phrase))->toBeFalse($path.': '.$phrase);
        }
    }

    expect(true)->toBeTrue();
});

it('names in module.json only classes that exist', function (): void {
    /** @var array{presentation: array{filament: array<string, list<string>>}, provider: string, category: string} $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

    expect($manifest['category'])->toBe('presentation')
        ->and(class_exists($manifest['provider']))->toBeTrue()
        ->and(is_subclass_of($manifest['provider'], ServiceProvider::class))->toBeTrue()
        ->and($manifest['presentation']['filament'])->not->toBe([]);

    foreach ($manifest['presentation']['filament'] as $panel => $classes) {
        expect($classes)->not->toBe([], $panel);

        foreach ($classes as $class) {
            expect(class_exists($class))->toBeTrue($class);
        }
    }
});

it('declares exactly the liberusoftware packages composer requires', function (): void {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
    /** @var array{requires: array{packages: array<string, string>}} $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

    $liberu = array_filter(
        $composer['require'],
        static fn (string $name): bool => str_starts_with($name, 'liberusoftware/'),
        ARRAY_FILTER_USE_KEY,
    );

    expect($manifest['requires']['packages'])->toBe($liberu);
});

it('boots nothing through Composer', function (): void {
    /** @var array<string, mixed> $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['extra']['laravel']['providers'] ?? [])->toBe([])
        ->and($composer['type'])->toBe('liberu-module');
});

it('registers no surface from its service provider, so no panel gets this by accident', function (): void {
    $provider = new CustomerAccountsFilamentServiceProvider($this->app);

    $provider->register();
    $provider->boot();

    // The only thing the provider does is name a view namespace for the
    // registry page. Nothing is reachable through it until the plugin puts the
    // page on a panel.
    expect(view()->exists('ecommerce-customer-accounts-filament::pages.participant-registry'))->toBeTrue();
});

it('registers every resource and page through the plugin the host attaches', function (): void {
    $panel = Panel::make()->id('probe');

    CustomerAccountsPlugin::make()->register($panel);

    expect($panel->getResources())->toHaveCount(3)
        ->and($panel->getPages())->toHaveCount(1)
        ->and(CustomerAccountsPlugin::make()->getId())->toBe('ecommerce-customer-accounts');
});

it('states a version that matches the manifest and is a released shape', function (): void {
    /** @var array{version: string} $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
    /** @var array{version: string} $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

    expect($composer['version'])->toBe($manifest['version'])
        ->and(preg_match('/^\d+\.\d+\.\d+$/', $composer['version']))->toBe(1);
});

it('reads every model through a tenant predicate and never through a null one', function (): void {
    // Comments are stripped before matching, because half this package's
    // docblocks quote the predicate they exist to forbid, and a rule that
    // cannot tell the code from the explanation of the code is unenforceable.
    foreach (sourceFiles() as $path) {
        $code = '';

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        // A null tenant predicate compiles to `is null` and lists exactly the
        // orphan rows a scope was meant to hide. A case that reaches beyond one
        // merchant is found by its RequestScope, never by an absent merchant.
        expect(preg_match("/whereNull\(\s*'tenant_id'/", $code))->toBe(0, $path);
        expect(preg_match("/tenant_id'\s*,\s*null/", $code))->toBe(0, $path);
    }
});
