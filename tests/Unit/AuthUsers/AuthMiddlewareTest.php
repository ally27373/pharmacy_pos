<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Models/User.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';

use PHPUnit\Framework\TestCase;

/**
 * AuthMiddleware::check()/admin()/dataManagement() call header()+exit() on
 * every "deny" branch. Calling that code path directly inside the main
 * PHPUnit process would terminate the whole test run. Two strategies are
 * used here instead:
 *
 * 1. In-process tests, via the DI seam, for the branches that return
 *    normally (no exit) - the "allow" paths.
 * 2. Out-of-process tests for the "deny" branches: a small fixture script
 *    is spawned as its own PHP CLI process (via proc_open), so if it calls
 *    exit(), only that short-lived child process dies. We assert on the
 *    child's stdout and exit code: a REACHED_END marker echoed right after
 *    the call proves the method returned normally; its absence proves the
 *    method exited before reaching it.
 */
final class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // --------------------------------------------------------------
    // In-process: "allow" branches (no exit)
    // --------------------------------------------------------------

    public function test_check_refreshes_session_for_active_user(): void
    {
        $_SESSION['user_id'] = 7;

        $userModel = $this->createMock(User::class);
        $userModel->method('getById')->with(7)->willReturn([
            'user_id' => 7,
            'username' => 'bob',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);

        AuthMiddleware::check($userModel);

        $this->assertSame('bob', $_SESSION['username']);
        $this->assertSame(2, $_SESSION['role_id']);
    }

    public function test_admin_allows_active_administrator_without_exiting(): void
    {
        $_SESSION['user_id'] = 1;

        $userModel = $this->createMock(User::class);
        $userModel->method('getById')->with(1)->willReturn([
            'user_id' => 1,
            'username' => 'admin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);

        $this->expectOutputString('');
        AuthMiddleware::admin($userModel);

        $this->assertSame(1, $_SESSION['role_id']);
    }

    public function test_dataManagement_allows_active_administrator_without_exiting(): void
    {
        $_SESSION['user_id'] = 1;

        $userModel = $this->createMock(User::class);
        $userModel->method('getById')->with(1)->willReturn([
            'user_id' => 1,
            'username' => 'admin',
            'role_id' => 1,
            'account_status' => 'Active',
        ]);

        $this->expectOutputString('');
        AuthMiddleware::dataManagement($userModel);

        $this->assertSame(1, $_SESSION['role_id']);
    }

    // --------------------------------------------------------------
    // Out-of-process: "deny" branches (exit)
    // --------------------------------------------------------------

    /**
     * @param array<string, mixed> $session   $_SESSION contents before the call.
     * @param array<string, mixed>|false $getByIdReturn What the injected User::getById() stub returns.
     */
    private function buildFixtureScript(string $method, array $session, array|false $getByIdReturn): string
    {
        $repoRoot = dirname(__DIR__, 3);
        $userPath = $repoRoot . '/app/Models/User.php';
        $middlewarePath = $repoRoot . '/app/Middleware/AuthMiddleware.php';

        $lines = [];
        $lines[] = 'require_once ' . var_export($userPath, true) . ';';
        $lines[] = 'require_once ' . var_export($middlewarePath, true) . ';';
        $lines[] = 'class FakeUserForTest extends User {';
        $lines[] = '    public function __construct() {}';
        $lines[] = '    public function getById(int $userId): array|false {';
        $lines[] = '        return ' . var_export($getByIdReturn, true) . ';';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = 'session_start();';
        $lines[] = '$_SESSION = ' . var_export($session, true) . ';';
        $lines[] = 'AuthMiddleware::' . $method . '(new FakeUserForTest());';
        $lines[] = "echo 'REACHED_END';";

        return implode("\n", $lines);
    }

    /**
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    private function runFixtureScript(string $body): array
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'authmw_') . '.php';
        file_put_contents($scriptPath, "<?php\n" . $body . "\n");

        $phpBinary = 'C:\\xampp\\php\\php.exe';
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([$phpBinary, $scriptPath], $descriptorSpec, $pipes);
        $this->assertIsResource($process, 'Failed to spawn PHP subprocess for AuthMiddleware fixture.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        @unlink($scriptPath);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exitCode];
    }

    public function test_check_redirects_when_no_session_user_id(): void
    {
        $body = $this->buildFixtureScript('check', [], false);
        $result = $this->runFixtureScript($body);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringNotContainsString('REACHED_END', $result['stdout']);
    }

    public function test_check_destroys_session_and_redirects_when_user_no_longer_exists(): void
    {
        $body = $this->buildFixtureScript('check', ['user_id' => 999], false);
        $result = $this->runFixtureScript($body);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringNotContainsString('REACHED_END', $result['stdout']);
    }

    public function test_check_redirects_when_account_is_inactive(): void
    {
        $body = $this->buildFixtureScript('check', ['user_id' => 5], [
            'user_id' => 5,
            'username' => 'bob',
            'role_id' => 2,
            'account_status' => 'Inactive',
        ]);
        $result = $this->runFixtureScript($body);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringNotContainsString('REACHED_END', $result['stdout']);
    }

    public function test_check_allows_active_user_in_subprocess_too(): void
    {
        $body = $this->buildFixtureScript('check', ['user_id' => 5], [
            'user_id' => 5,
            'username' => 'bob',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);
        $result = $this->runFixtureScript($body);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('REACHED_END', $result['stdout']);
    }

    public function test_admin_denies_cashier_with_403(): void
    {
        $body = $this->buildFixtureScript('admin', ['user_id' => 2], [
            'user_id' => 2,
            'username' => 'cashier1',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);
        $result = $this->runFixtureScript($body);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringNotContainsString('REACHED_END', $result['stdout']);
        $this->assertStringContainsString('Access Denied', $result['stdout']);
    }

    /**
     * Documents the current (buggy) behavior described in DEFECT-2: the
     * dataManagement() docblock explicitly states "Administrators and
     * Cashiers may access Data Management", but the implementation denies
     * every role except Administrator (role_id 1). This test asserts the
     * behavior the method's own comment promises, so it is expected to FAIL
     * against the current implementation - see tests/Reports/DEFECTS_auth-users.md.
     */
    public function test_dataManagement_allows_cashier_per_documented_intent(): void
    {
        $body = $this->buildFixtureScript('dataManagement', ['user_id' => 2], [
            'user_id' => 2,
            'username' => 'cashier1',
            'role_id' => 2,
            'account_status' => 'Active',
        ]);
        $result = $this->runFixtureScript($body);

        $this->assertStringContainsString(
            'REACHED_END',
            $result['stdout'],
            'dataManagement() should allow Cashiers through per its own documented intent, but it denies them (DEFECT-2).'
        );
    }
}
