<?php

declare(strict_types=1);

/**
 * Test-only stand-in for the global exec() call made by
 * App\Services\ForecastRefreshService::refresh().
 *
 * PHP resolves an unqualified function call inside a namespace by first
 * checking for a function of that name declared in the *current*
 * namespace, and only falling back to the global function if none exists.
 * Declaring App\Services\exec() below intercepts every call to exec()
 * made from code inside the App\Services namespace (i.e. from
 * ForecastRefreshService) without touching that production file at all.
 * This lets tests exercise refresh()'s branching (success, non-zero exit,
 * annual-forecast failure not blocking the main result, etc.) without
 * ever shelling out to a real Python interpreter.
 *
 * The ForecastExecStub class below (global namespace) is the queue/spy
 * backing that stub function.
 */

namespace {
    class ForecastExecStub
    {
        /** @var array<int, array{0:int,1:string[]}> */
        private static array $queue = [];

        /** @var string[] */
        private static array $calls = [];

        public static function reset(): void
        {
            self::$queue = [];
            self::$calls = [];
        }

        /**
         * @param string[] $output
         */
        public static function queueResult(int $exitCode, array $output = []): void
        {
            self::$queue[] = [$exitCode, $output];
        }

        /**
         * @return string[]
         */
        public static function calls(): array
        {
            return self::$calls;
        }

        /**
         * @param mixed $output
         * @param mixed $return_var
         */
        public static function handle(string $command, &$output, &$return_var): string
        {
            self::$calls[] = $command;

            [$exitCode, $lines] = self::$queue !== []
                ? array_shift(self::$queue)
                : [0, []];

            $output = $lines;
            $return_var = $exitCode;

            return $lines[count($lines) - 1] ?? '';
        }
    }
}

namespace App\Services {
    if (!function_exists(__NAMESPACE__ . '\\exec')) {
        function exec(string $command, &$output = null, &$return_var = null)
        {
            return \ForecastExecStub::handle($command, $output, $return_var);
        }
    }
}
