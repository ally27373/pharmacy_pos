<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Controllers/POSController.php';

use PHPUnit\Framework\TestCase;

/**
 * A minimal userland stream wrapper that stands in for the built-in "php"
 * wrapper for the duration of a single test, so tests can control what
 * file_get_contents('php://input') returns without any production-code
 * change. PHP's stream_wrapper_unregister()/register()/restore() API is
 * specifically designed for this temporary-override-then-restore pattern
 * (restore() puts the real built-in wrapper back, not a hand-rolled
 * substitute), so this doesn't risk leaking broken php:// behavior
 * (php://memory, php://output, php://stdin, etc.) to other tests.
 */
final class FakePhpInputStreamForPosControllerTest
{
    public static string $body = '';
    private int $pos = 0;

    /** @var resource|null Declared because PHP's streams engine sets this on wrapper instances. */
    public $context;

    public function stream_open(): bool
    {
        $this->pos = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->pos, $count);
        $this->pos += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->pos >= strlen(self::$body);
    }

    public function stream_stat(): array
    {
        return [];
    }
}

/**
 * Unit tests for app/Controllers/POSController.php.
 *
 * POS itself is mocked out via the constructor-injection seam, so these
 * tests verify the controller's own logic: $_GET parsing/normalizing,
 * delegation to the model, and — via the scoped php://input override
 * above — processSale()'s request parsing (the invalid-JSON 400 branch)
 * and its $_SESSION['user_id'] -> cashier_id injection.
 */
final class POSControllerTest extends TestCase
{
    use MocksPdo;

    private array $originalGet;
    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SESSION = $this->originalSession;
    }

    /**
     * Invokes $controller->processSale() with php://input made to return
     * $body, capturing whatever it echoes. The override is unregistered
     * again in a finally block so it can never leak past this one call,
     * even if an assertion inside $fn throws.
     */
    private function callProcessSaleWithBody(POSController $controller, string $body): string
    {
        FakePhpInputStreamForPosControllerTest::$body = $body;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', FakePhpInputStreamForPosControllerTest::class);

        try {
            ob_start();
            $controller->processSale();
            return ob_get_clean();
        } finally {
            stream_wrapper_restore('php');
        }
    }

    public function testGetProductsUsesDefaultsWhenNoQueryParamsGiven(): void
    {
        $_GET = [];

        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getAvailableProducts')
            ->with(1, 20, '', '', '')
            ->willReturn(['products' => [], 'pagination' => []]);

        $controller = new POSController($pos);
        $result = $controller->getProducts();

        $this->assertSame(['products' => [], 'pagination' => []], $result);
    }

    public function testGetProductsNormalizesAndTrimsQueryParams(): void
    {
        $_GET = [
            'page' => '0',
            'limit' => '500',
            'search' => '  amox  ',
            'category' => '  Antibiotics  ',
            'type' => '  Tablet  ',
        ];

        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getAvailableProducts')
            ->with(1, 100, 'amox', 'Antibiotics', 'Tablet')
            ->willReturn(['products' => [], 'pagination' => []]);

        $controller = new POSController($pos);
        $controller->getProducts();
    }

    public function testGetProductsPassesThroughValidPageAndLimit(): void
    {
        $_GET = ['page' => '3', 'limit' => '15'];

        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getAvailableProducts')
            ->with(3, 15, '', '', '')
            ->willReturn(['products' => [], 'pagination' => []]);

        $controller = new POSController($pos);
        $controller->getProducts();
    }

    public function testGetCategoriesDelegatesToModel(): void
    {
        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getCategories')
            ->willReturn([['category_id' => 1, 'category_name' => 'Antibiotics']]);

        $controller = new POSController($pos);
        $result = $controller->getCategories();

        $this->assertSame([['category_id' => 1, 'category_name' => 'Antibiotics']], $result);
    }

    public function testGetProductTypesDelegatesToModel(): void
    {
        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getProductTypes')
            ->willReturn([['type_id' => 1, 'type_name' => 'Tablet']]);

        $controller = new POSController($pos);
        $result = $controller->getProductTypes();

        $this->assertSame([['type_id' => 1, 'type_name' => 'Tablet']], $result);
    }

    // -----------------------------------------------------------------
    // processSale()
    // -----------------------------------------------------------------

    public function testProcessSaleReturns400AndDoesNotCallModelWhenBodyIsEmpty(): void
    {
        $pos = $this->createMock(POS::class);
        $pos->expects($this->never())->method('processSale');

        $controller = new POSController($pos);
        // An empty request body is what file_get_contents('php://input')
        // naturally returns for a request with no body at all.
        $output = $this->callProcessSaleWithBody($controller, '');

        $this->assertSame(400, http_response_code());
        $this->assertSame(
            ['success' => false, 'message' => 'Invalid request.'],
            json_decode($output, true)
        );
    }

    public function testProcessSaleReturns400AndDoesNotCallModelWhenBodyIsMalformedJson(): void
    {
        $pos = $this->createMock(POS::class);
        $pos->expects($this->never())->method('processSale');

        $controller = new POSController($pos);
        $output = $this->callProcessSaleWithBody($controller, '{not valid json');

        $this->assertSame(400, http_response_code());
        $this->assertSame(
            ['success' => false, 'message' => 'Invalid request.'],
            json_decode($output, true)
        );
    }

    public function testProcessSaleInjectsCashierIdFromSessionUserIdAndReturnsModelResult(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user_id'] = 42;

        $capturedData = null;
        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('processSale')
            ->with($this->callback(function ($data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }))
            ->willReturn(['success' => true, 'message' => 'Sale completed successfully.']);

        $controller = new POSController($pos);
        $output = $this->callProcessSaleWithBody($controller, json_encode([
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
            'cash' => 100,
        ]));

        // The controller must overwrite/inject cashier_id from the
        // session, not trust any cashier_id present in the request body.
        $this->assertSame(42, $capturedData['cashier_id']);
        $this->assertSame(1, $capturedData['cart'][0]['id']);
        $this->assertSame(
            ['success' => true, 'message' => 'Sale completed successfully.'],
            json_decode($output, true)
        );
    }

    public function testProcessSaleIgnoresClientSuppliedCashierIdAndUsesSessionInstead(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user_id'] = 7;

        $capturedData = null;
        $pos = $this->createMock(POS::class);
        $pos->method('processSale')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return ['success' => true, 'message' => 'ok'];
        });

        $controller = new POSController($pos);
        // The request body tries to claim cashier_id 999 — the session's
        // user_id (7) must win.
        $this->callProcessSaleWithBody($controller, json_encode([
            'cashier_id' => 999,
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
        ]));

        $this->assertSame(7, $capturedData['cashier_id']);
    }

    public function testProcessSaleDefaultsCashierIdToZeroWhenSessionUserIdMissing(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['user_id']);

        $capturedData = null;
        $pos = $this->createMock(POS::class);
        $pos->method('processSale')->willReturnCallback(function ($data) use (&$capturedData) {
            $capturedData = $data;
            return ['success' => false, 'message' => 'Your session has expired. Please log in again.'];
        });

        $controller = new POSController($pos);
        $output = $this->callProcessSaleWithBody($controller, json_encode([
            'cart' => [['id' => 1, 'qty' => 1]],
            'paymentMethod' => 'Cash',
        ]));

        $this->assertSame(0, $capturedData['cashier_id']);
        $this->assertSame(
            ['success' => false, 'message' => 'Your session has expired. Please log in again.'],
            json_decode($output, true)
        );
    }
}
