<?php

namespace App\Libraries;

/**
 * ModelRegistry
 *
 * Singleton model instance manager for CodeIgniter 4.
 * Solves cyclic constructor dependency issues that arise when migrating
 * from CI3 (where $this->load->model() was called anywhere) to CI4
 * (where models are injected in the constructor, causing circular chains).
 *
 * Usage:
 *   ModelRegistry::getModel('UserModel')->findById(1);
 *
 * Each model is instantiated once per request lifecycle and reused.
 */
class ModelRegistry
{
    /** @var array<string, object> */
    private static array $instances = [];

    private static string $namespace = 'App\\Models\\';

    /**
     * Get (or lazily create) a model instance.
     *
     * @param  string $modelName  Short class name, e.g. 'UserModel'
     * @return object
     * @throws \RuntimeException  If the class does not exist
     */
    public static function getModel(string $modelName): object
    {
        if (isset(self::$instances[$modelName])) {
            return self::$instances[$modelName];
        }

        $fqcn = self::$namespace . $modelName;

        if (! class_exists($fqcn)) {
            throw new \RuntimeException(
                "ModelRegistry: class [{$fqcn}] not found. "
                . "Check the class name and namespace."
            );
        }

        self::$instances[$modelName] = new $fqcn();

        return self::$instances[$modelName];
    }

    /**
     * Register a pre-built instance — useful for dependency injection in tests.
     */
    public static function register(string $modelName, object $instance): void
    {
        self::$instances[$modelName] = $instance;
    }

    /**
     * Release one model instance (or all if $modelName is null).
     * Call in tests to ensure a fresh instance per test.
     */
    public static function reset(?string $modelName = null): void
    {
        if ($modelName !== null) {
            unset(self::$instances[$modelName]);
        } else {
            self::$instances = [];
        }
    }

    /**
     * Check whether a model has been initialized.
     */
    public static function has(string $modelName): bool
    {
        return isset(self::$instances[$modelName]);
    }

    /**
     * Override the default App\Models\ namespace prefix.
     * Call this in app/Config/Boot.php if your models live elsewhere.
     */
    public static function setNamespace(string $namespace): void
    {
        self::$namespace = rtrim($namespace, '\\') . '\\';
    }
}
