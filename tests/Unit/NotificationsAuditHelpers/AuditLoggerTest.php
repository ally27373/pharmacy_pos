<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/Models/AuditLog.php';
require_once __DIR__ . '/../../../app/Services/AuditLogger.php';

final class AuditLoggerTest extends TestCase
{
    private ?array $originalSession = null;
    private ?array $originalServer = null;
    private string $originalErrorLog = '';
    private string $errorLogFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSession = $_SESSION ?? null;
        $this->originalServer = $_SERVER;

        $this->errorLogFile = sys_get_temp_dir() . '/audit_logger_test_' . uniqid() . '.log';
        $this->originalErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $this->errorLogFile);
    }

    protected function tearDown(): void
    {
        if ($this->originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->originalSession;
        }
        $_SERVER = $this->originalServer;

        ini_set('error_log', $this->originalErrorLog);
        if (is_file($this->errorLogFile)) {
            unlink($this->errorLogFile);
        }

        parent::tearDown();
    }

    public function test_log_returns_false_and_never_touches_audit_log_when_no_user_in_session(): void
    {
        $_SESSION = [];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->never())->method('create');

        $result = AuditLogger::log('CREATE', 'Products', 'Created a product', null, $auditLog);

        $this->assertFalse($result);
    }

    public function test_log_returns_false_when_session_user_id_is_zero(): void
    {
        $_SESSION = ['user_id' => 0];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->never())->method('create');

        $this->assertFalse(AuditLogger::log('CREATE', 'Products', 'desc', null, $auditLog));
    }

    public function test_log_returns_false_when_session_user_id_is_negative(): void
    {
        $_SESSION = ['user_id' => -3];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->never())->method('create');

        $this->assertFalse(AuditLogger::log('CREATE', 'Products', 'desc', null, $auditLog));
    }

    public function test_log_creates_audit_record_with_session_user_and_request_metadata(): void
    {
        $_SESSION = ['user_id' => 15];
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit-Agent/2.0';

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('create')
            ->with(15, 'UPDATE', 'Inventory', 'Updated stock', 99, '10.0.0.5', 'PHPUnit-Agent/2.0')
            ->willReturn(true);

        $result = AuditLogger::log('UPDATE', 'Inventory', 'Updated stock', 99, $auditLog);

        $this->assertTrue($result);
    }

    public function test_log_casts_a_numeric_string_session_user_id_to_int(): void
    {
        $_SESSION = ['user_id' => '22'];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('create')
            ->with(22, 'DELETE', 'Sales', 'Deleted a sale', null, $this->anything(), $this->anything())
            ->willReturn(true);

        AuditLogger::log('DELETE', 'Sales', 'Deleted a sale', null, $auditLog);
    }

    public function test_log_defaults_ip_and_user_agent_to_null_when_not_present_in_server(): void
    {
        $_SESSION = ['user_id' => 7];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->expects($this->once())
            ->method('create')
            ->with(7, 'EXPORT', 'Reports', 'Exported report', null, null, null)
            ->willReturn(true);

        AuditLogger::log('EXPORT', 'Reports', 'Exported report', null, $auditLog);
    }

    public function test_log_returns_the_underlying_create_result_when_it_is_false(): void
    {
        $_SESSION = ['user_id' => 3];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->method('create')->willReturn(false);

        $this->assertFalse(AuditLogger::log('CREATE', 'Products', 'desc', null, $auditLog));
    }

    public function test_log_swallows_exceptions_from_the_audit_log_and_returns_false(): void
    {
        $_SESSION = ['user_id' => 3];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->method('create')->willThrowException(new RuntimeException('DB write failed'));

        $result = AuditLogger::log('CREATE', 'Products', 'desc', null, $auditLog);

        $this->assertFalse($result);
    }

    public function test_log_writes_the_exception_message_to_the_error_log_on_failure(): void
    {
        $_SESSION = ['user_id' => 3];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->method('create')->willThrowException(new RuntimeException('DB write failed'));

        AuditLogger::log('CREATE', 'Products', 'desc', null, $auditLog);

        $this->assertFileExists($this->errorLogFile);
        $contents = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('AuditLogger error:', $contents);
        $this->assertStringContainsString('DB write failed', $contents);
    }

    public function test_log_with_invalid_action_type_is_caught_and_returns_false(): void
    {
        // create() throws InvalidArgumentException for an unknown action type;
        // AuditLogger must treat that the same as any other Throwable.
        $_SESSION = ['user_id' => 3];

        $auditLog = $this->createMock(AuditLog::class);
        $auditLog->method('create')->willThrowException(
            new InvalidArgumentException('Invalid audit action type.')
        );

        $this->assertFalse(AuditLogger::log('BOGUS', 'Products', 'desc', null, $auditLog));
    }
}
