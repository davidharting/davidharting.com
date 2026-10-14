<?php

namespace Tests\Support;

/**
 * Temporarily overrides process environment variables for code that reads
 * env() at request time, such as App\Support\DeploymentInfo.
 *
 * Laravel's env() checks $_SERVER, then $_ENV, then getenv(), so all three are
 * set. Call restore() in afterEach so phpunit.xml's values come back for the
 * next test.
 */
class ProcessEnvironment
{
    /** @var array<string, array{server: mixed, env: mixed, getenv: string|false}> */
    private static array $original = [];

    /**
     * @param  array<string, string>  $variables
     */
    public static function set(array $variables): void
    {
        foreach ($variables as $name => $value) {
            self::$original[$name] ??= [
                'server' => $_SERVER[$name] ?? null,
                'env' => $_ENV[$name] ?? null,
                'getenv' => getenv($name),
            ];

            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    public static function restore(): void
    {
        foreach (self::$original as $name => $original) {
            if ($original['server'] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $original['server'];
            }

            if ($original['env'] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $original['env'];
            }

            putenv($original['getenv'] === false ? $name : "{$name}={$original['getenv']}");
        }

        self::$original = [];
    }
}
