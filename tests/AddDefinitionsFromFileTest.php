<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Testing\TestContextProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class AddDefinitionsFromFileTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/phpdot_defs_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testLoadsDefinitionsFromFile(): void
    {
        file_put_contents($this->tmpFile, <<<'PHP'
            <?php
            use function PHPdot\Container\singleton;
            return [
                'svc' => singleton(fn () => new \stdClass()),
            ];
            PHP);

        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitionsFromFile($this->tmpFile)
            ->build();

        $instance = $container->get('svc');

        self::assertInstanceOf(stdClass::class, $instance);
    }

    public function testReturnsBuilderForChaining(): void
    {
        file_put_contents($this->tmpFile, '<?php return [];');

        $builder = new ContainerBuilder();
        $result = $builder->addDefinitionsFromFile($this->tmpFile);

        self::assertSame($builder, $result);
    }

    public function testThrowsWhenFileMissing(): void
    {
        $missing = '/nonexistent/path/' . uniqid() . '.php';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Definitions file not found');

        (new ContainerBuilder())->addDefinitionsFromFile($missing);
    }

    public function testThrowsWhenFileDoesNotReturnArray(): void
    {
        file_put_contents($this->tmpFile, '<?php return "not an array";');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must return an array');

        (new ContainerBuilder())->addDefinitionsFromFile($this->tmpFile);
    }

    public function testLaterCallsOverrideEarlierEntries(): void
    {
        $first = sys_get_temp_dir() . '/phpdot_defs_first_' . uniqid() . '.php';
        $second = sys_get_temp_dir() . '/phpdot_defs_second_' . uniqid() . '.php';

        file_put_contents($first, <<<'PHP'
            <?php
            use function PHPdot\Container\singleton;
            return [
                'value' => singleton(fn () => 'first'),
            ];
            PHP);

        file_put_contents($second, <<<'PHP'
            <?php
            use function PHPdot\Container\singleton;
            return [
                'value' => singleton(fn () => 'second'),
            ];
            PHP);

        try {
            $container = (new ContainerBuilder())
                ->withContextProvider(new TestContextProvider())
                ->addDefinitionsFromFile($first)
                ->addDefinitionsFromFile($second)
                ->build();

            self::assertSame('second', $container->get('value'));
        } finally {
            unlink($first);
            unlink($second);
        }
    }
}
