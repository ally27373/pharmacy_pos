<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Helpers/Csrf.php';

use PHPUnit\Framework\TestCase;

/**
 * Csrf guards every state-changing request. The role check answers "who is
 * asking"; this answers "did this browser mean to ask", which is the half a
 * signed-in cookie cannot speak for on its own.
 *
 * requireValid() calls exit() on refusal, so -- exactly as in
 * AuthMiddlewareTest -- the allow paths are exercised in process and the
 * deny paths in a spawned PHP subprocess where an exit() cannot take the
 * test run down with it.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    // --------------------------------------------------------------
    // Token minting
    // --------------------------------------------------------------

    public function test_token_is_minted_once_and_then_stable(): void
    {
        $first = Csrf::token();

        $this->assertNotSame('', $first);
        $this->assertSame($first, Csrf::token(), 'Token must not change between calls.');
        $this->assertSame($first, $_SESSION['csrf_token']);
    }

    public function test_token_is_long_enough_to_be_unguessable(): void
    {
        // 32 random bytes, hex encoded.
        $this->assertSame(64, strlen(Csrf::token()));
    }

    public function test_reset_discards_the_token_so_the_next_read_mints_a_new_one(): void
    {
        $before = Csrf::token();

        Csrf::reset();

        $this->assertArrayNotHasKey('csrf_token', $_SESSION);

        $after = Csrf::token();

        $this->assertNotSame($before, $after);
    }

    public function test_a_token_minted_before_reset_is_no_longer_accepted(): void
    {
        $stale = Csrf::token();

        Csrf::reset();

        $this->assertFalse(Csrf::isValid($stale));
    }

    // --------------------------------------------------------------
    // Validation
    // --------------------------------------------------------------

    public function test_is_valid_accepts_the_current_token(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::isValid($token));
    }

    /**
     * @dataProvider provideRejectedTokens
     */
    public function test_is_valid_rejects_anything_else(?string $candidate): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::isValid($candidate));
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function provideRejectedTokens(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'wrong token' => [str_repeat('a', 64)];
        yield 'right token with one char changed' => [null]; // filled in below
        yield 'truncated token' => [str_repeat('b', 10)];
    }

    public function test_a_near_miss_token_is_rejected(): void
    {
        $token = Csrf::token();

        $mutated = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');

        $this->assertFalse(Csrf::isValid($mutated));
    }

    public function test_is_valid_rejects_when_no_token_has_been_minted(): void
    {
        // No Csrf::token() call, so the session holds nothing to compare to.
        $this->assertFalse(Csrf::isValid(str_repeat('c', 64)));
    }

    // --------------------------------------------------------------
    // Reading the token off the request
    // --------------------------------------------------------------

    public function test_from_request_reads_the_posted_field(): void
    {
        $_POST['csrf_token'] = 'from-post';

        $this->assertSame('from-post', Csrf::fromRequest());
    }

    public function test_from_request_returns_null_when_nothing_was_sent(): void
    {
        $this->assertNull(Csrf::fromRequest());
    }

    public function test_from_request_reads_the_header_when_no_field_is_posted(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'from-header';

        $this->assertSame('from-header', Csrf::fromRequest());
    }

    public function test_posted_field_wins_over_the_header(): void
    {
        $_POST['csrf_token'] = 'from-post';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'from-header';

        $this->assertSame('from-post', Csrf::fromRequest());
    }

    // --------------------------------------------------------------
    // Rendering the hidden field
    // --------------------------------------------------------------

    public function test_field_renders_a_hidden_input_carrying_the_token(): void
    {
        $html = Csrf::field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString(Csrf::token(), $html);
    }

    // --------------------------------------------------------------
    // Deny path (out of process, because requireValid exits)
    // --------------------------------------------------------------

    public function test_require_valid_passes_through_for_a_valid_token(): void
    {
        $result = $this->runFixture(true);

        $this->assertStringContainsString('REACHED_END', $result['stdout']);
    }

    public function test_require_valid_refuses_a_request_with_no_token(): void
    {
        $result = $this->runFixture(false);

        $this->assertStringNotContainsString('REACHED_END', $result['stdout']);
        $this->assertStringContainsString('session token is missing', $result['stdout']);
    }

    /**
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    private function runFixture(bool $sendValidToken): array
    {
        $repoRoot = dirname(__DIR__, 3);

        $lines = [];
        $lines[] = 'require_once ' . var_export($repoRoot . '/app/Helpers/Csrf.php', true) . ';';
        $lines[] = 'session_start();';
        $lines[] = '$_SESSION = [];';
        $lines[] = 'session_write_close();';
        $lines[] = '$token = Csrf::token();';
        $lines[] = 'session_start();';

        if ($sendValidToken) {
            $lines[] = '$_POST["csrf_token"] = $token;';
        }

        $lines[] = 'Csrf::requireValid();';
        $lines[] = "echo 'REACHED_END';";

        $scriptPath = tempnam(sys_get_temp_dir(), 'csrf_') . '.php';
        file_put_contents($scriptPath, "<?php\n" . implode("\n", $lines) . "\n");

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            // See AuthMiddlewareTest::runFixtureScript(): a hardcoded Windows
            // path would fail everywhere else.
            [PHP_BINARY, $scriptPath],
            $descriptorSpec,
            $pipes
        );

        $this->assertIsResource($process, 'Failed to spawn PHP subprocess for Csrf fixture.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        @unlink($scriptPath);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exitCode];
    }
}