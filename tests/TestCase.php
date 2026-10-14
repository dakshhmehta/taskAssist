<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Read a protected server property (laravel/mcp made $tools/$version protected).
     *
     * @return array<int, class-string>|string
     */
    protected function mcpServerProperty(string $server, string $property): mixed
    {
        $instance = (new \ReflectionClass($server))->newInstanceWithoutConstructor();
        $ref = new \ReflectionProperty($server, $property);
        $ref->setAccessible(true);

        return $ref->getValue($instance);
    }

    /**
     * @return array<int, class-string>
     */
    protected function mcpServerToolClasses(string $server): array
    {
        return $this->mcpServerProperty($server, 'tools');
    }
}
