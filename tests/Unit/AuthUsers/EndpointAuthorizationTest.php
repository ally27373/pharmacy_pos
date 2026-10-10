<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Guardrail against the failure mode behind the "cashier can reach admin
 * inventory" report.
 *
 * Authorization in this codebase is enforced by the page that renders a
 * control, which stops a user clicking it -- but not a user POSTing to the
 * endpoint behind it. Every ajax/ endpoint therefore has to carry its own
 * guard, because that endpoint is reachable by URL regardless of which page
 * links to it.
 *
 * These tests are deliberately static rather than behavioural: they read the
 * files so that a new endpoint which forgets a guard fails CI, instead of
 * waiting for someone to notice in production.
 */
final class EndpointAuthorizationTest extends TestCase
{
    private const REPO_ROOT = __DIR__ . '/../../..';

    /**
     * Every accepted way an endpoint may declare its access rule.
     */
    private const GUARDS = [
        'AuthMiddleware::requireAdminApi',
        'AuthMiddleware::requireAuthenticatedApi',
        'AuthMiddleware::admin',
        'AuthMiddleware::check',
        'AuthMiddleware::dataManagement',
    ];

    /**
     * @return list<string>
     */
    private function ajaxEndpointFiles(): array
    {
        $root = realpath(self::REPO_ROOT);

        if ($root === false) {
            $this->fail('Could not resolve the repository root.');
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (strpos(str_replace('\\', '/', $file->getPathname()), '/ajax/') === false) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    public function test_the_scan_actually_finds_the_ajax_endpoints(): void
    {
        // Guards against the scanner silently matching nothing, which would
        // make every assertion below pass for the wrong reason.
        $files = $this->ajaxEndpointFiles();

        $this->assertGreaterThan(
            10,
            count($files),
            'Expected to discover the ajax/ endpoints. If this fails the '
            . 'scanner no longer matches the directory layout and the guard '
            . 'tests below would be vacuous.'
        );
    }

    /**
     * @dataProvider provideAjaxEndpoints
     */
    public function test_every_ajax_endpoint_declares_an_access_guard(string $file): void
    {
        $source = (string) file_get_contents($file);

        foreach (self::GUARDS as $guard) {
            if (strpos($source, $guard) !== false) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail(
            sprintf(
                '%s is reachable directly by URL but declares no access guard.'
                . PHP_EOL
                . 'Add one of: %s'
                . PHP_EOL
                . 'A guarded page is not enough -- the endpoint can be called'
                . ' without ever rendering that page.',
                $this->relative($file),
                implode(', ', self::GUARDS)
            )
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideAjaxEndpoints(): iterable
    {
        // The data provider runs before the instance exists, so this mirrors
        // ajaxEndpointFiles() rather than calling it.
        $root = realpath(__DIR__ . '/../../..');

        if ($root === false) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (strpos(str_replace('\\', '/', $file->getPathname()), '/ajax/') === false) {
                continue;
            }

            yield $file->getPathname() => [$file->getPathname()];
        }
    }

    /**
     * app/Controllers/ is a class directory. Two loose scripts once lived
     * there -- process_sale.php and get_notifications.php -- both without a
     * guard, while guarded twins sat elsewhere and the JavaScript called the
     * unguarded pair. Nothing about that was visible from the dashboard code.
     */
    public function test_controllers_directory_contains_only_classes(): void
    {
        $dir = self::REPO_ROOT . '/app/Controllers';

        $offenders = [];

        foreach ((array) glob($dir . '/*.php') as $path) {
            $name = basename((string) $path);

            if ($name === 'AuthController.php' || str_ends_with($name, 'Controller.php')) {
                continue;
            }

            $offenders[] = $name;
        }

        $this->assertSame(
            [],
            $offenders,
            'app/Controllers/ should hold only *Controller.php classes. Web '
            . 'endpoints belong under app/dashboard/, and leaving them here '
            . 'is how the unguarded duplicates got there in the first place.'
        );
    }

    /**
     * Pins the two duplicate endpoints as deleted. They answered with real
     * data and had no guard, and their paths are memorable enough to be
     * re-created by accident.
     */
    public function test_unguarded_duplicate_endpoints_are_gone(): void
    {
        foreach ([
            '/app/Controllers/process_sale.php',
            '/app/Controllers/get_notifications.php',
        ] as $removed) {
            $this->assertFileDoesNotExist(
                self::REPO_ROOT . $removed,
                $removed . ' was an unguarded duplicate of a guarded endpoint.'
            );
        }
    }

    /**
     * The JavaScript has to point at the guarded endpoint. Repointing PHP
     * without repointing the caller would silently resurrect the open path.
     */
    public function test_callers_point_at_the_guarded_endpoints(): void
    {
        $posJs = (string) file_get_contents(self::REPO_ROOT . '/assets/js/pos.js');
        $notificationsJs = (string) file_get_contents(self::REPO_ROOT . '/assets/js/notifications.js');

        $this->assertStringContainsString(
            '/app/dashboard/pos/process_sale.php',
            $posJs,
            'pos.js must POST to the guarded checkout endpoint.'
        );

        $this->assertStringContainsString(
            '/app/dashboard/notifications/ajax/get_notifications.php',
            $notificationsJs,
            'notifications.js must read from the guarded notifications endpoint.'
        );

        foreach (['pos.js' => $posJs, 'notifications.js' => $notificationsJs] as $name => $source) {
            $this->assertStringNotContainsString(
                '/app/Controllers/process_sale.php',
                $source,
                $name . ' still calls the removed duplicate.'
            );

            $this->assertStringNotContainsString(
                '/app/Controllers/get_notifications.php',
                $source,
                $name . ' still calls the removed duplicate.'
            );
        }
    }

    /**
     * Web-reachable entry points that live outside an ajax/ directory.
     * The scanner above cannot find these, so they are pinned explicitly.
     *
     * Every one of them was reachable with no access check before this
     * change; export_sales_history.php in particular wrote a CSV to disk.
     */
    private const NON_AJAX_ENTRY_POINTS = [
        '/app/dashboard/pos/process_sale.php',
        '/app/dashboard/pos/receipt.php',
        '/app/export/export_sales_history.php',
    ];

    /**
     * @dataProvider provideNonAjaxEntryPoints
     */
    public function test_non_ajax_entry_points_declare_an_access_guard(string $relativePath): void
    {
        $file = self::REPO_ROOT . $relativePath;

        $this->assertFileExists($file, $relativePath . ' is expected to exist.');

        $source = (string) file_get_contents($file);

        foreach (self::GUARDS as $guard) {
            if (strpos($source, $guard) !== false) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail(
            sprintf(
                '%s is reachable directly by URL but declares no access guard.'
                . PHP_EOL
                . 'Add one of: %s',
                $relativePath,
                implode(', ', self::GUARDS)
            )
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideNonAjaxEntryPoints(): iterable
    {
        foreach (self::NON_AJAX_ENTRY_POINTS as $path) {
            yield $path => [$path];
        }
    }

    /**
     * Inventory History sits inside the sidebar's ADMIN-ONLY MODULES block.
     * It used to call check(), which let any Cashier read product movements,
     * batch lots and supplier adjustments by typing the URL.
     */
    public function test_inventory_history_is_administrator_only(): void
    {
        $source = (string) file_get_contents(
            self::REPO_ROOT . '/app/dashboard/inventory_management/history/index.php'
        );

        $this->assertStringContainsString(
            'AuthMiddleware::admin()',
            $source,
            'Inventory History must be Administrator-only.'
        );
    }

    /**
     * The CLI diagnostics under app/tests/ sit inside the document root.
     * One of them performs a live SARIMA refresh, so serving it over HTTP
     * let any visitor kick off a compute-heavy job on demand.
     *
     * The guard has to sit ahead of the require_once lines, otherwise the
     * work still starts before the refusal.
     */
    public function test_cli_diagnostics_refuse_to_run_over_http(): void
    {
        $dir = self::REPO_ROOT . '/app/tests';

        $checked = 0;

        foreach ((array) glob($dir . '/*.php') as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringContainsString(
                "PHP_SAPI !== 'cli'",
                $source,
                basename((string) $path) . ' must refuse non-CLI execution.'
            );

            $guardAt = strpos($source, "PHP_SAPI !== 'cli'");

            /*
             * Ordering only matters if the script loads something. These
             * files mix require and require_once, so look for both, and skip
             * the comparison when the script loads nothing.
             */
            $loads = [];
            foreach (['require_once', 'require ', 'include_once', 'include '] as $keyword) {
                $at = strpos($source, $keyword);
                if ($at !== false) {
                    $loads[] = $at;
                }
            }

            if ($loads !== []) {
                $this->assertLessThan(
                    min($loads),
                    $guardAt,
                    basename((string) $path) . ': the CLI guard must come before '
                    . 'the first require, otherwise the work runs anyway.'
                );
            }

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'Expected to find CLI diagnostics to check.');
    }

    private function relative(string $file): string
    {
        $root = realpath(self::REPO_ROOT);

        return $root === false
            ? basename($file)
            : ltrim(str_replace($root, '', $file), '\\/');
    }
}