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

    private function relative(string $file): string
    {
        $root = realpath(self::REPO_ROOT);

        return $root === false
            ? basename($file)
            : ltrim(str_replace($root, '', $file), '\\/');
    }
}