<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

interface CacheInterface
{
}

final class FileCache implements CacheInterface
{
}

final class RedisCache implements CacheInterface
{
}

final class ApcuCache implements CacheInterface
{
}

final class Translator
{
    public function __construct(
        public readonly CacheInterface $cache,
    ) {
    }
}

final class SessionManager
{
    public function __construct(
        public readonly CacheInterface $cache,
    ) {
    }
}

final class MultiDep
{
    public function __construct(
        public readonly CacheInterface $cache,
        public readonly \Psr\Log\LoggerInterface $logger,
    ) {
    }
}

final class NullLogger extends \Psr\Log\AbstractLogger
{
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
    }
}

final class CustomLogger extends \Psr\Log\AbstractLogger
{
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
    }
}

final class ContextualBindingTest extends TestCase
{
    #[Test]
    public function scoped_consumer_gets_bound_implementation(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $container = $builder->build();
        $translator = $container->get(Translator::class);

        self::assertInstanceOf(RedisCache::class, $translator->cache);
    }

    #[Test]
    public function scoped_consumer_with_closure_binding(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(static function (ContainerInterface $c): CacheInterface {
                return new RedisCache();
            });

        $container = $builder->build();
        $translator = $container->get(Translator::class);

        self::assertInstanceOf(RedisCache::class, $translator->cache);
    }

    #[Test]
    public function scoped_consumer_without_binding_gets_default(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $container = $builder->build();
        $translator = $container->get(Translator::class);

        self::assertInstanceOf(FileCache::class, $translator->cache);
    }

    #[Test]
    public function singleton_consumer_gets_bound_implementation(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            Translator::class => new ScopedDefinition(Scope::SINGLETON, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $container = $builder->build();
        $translator = $container->get(Translator::class);

        self::assertInstanceOf(RedisCache::class, $translator->cache);
    }

    #[Test]
    public function transient_consumer_gets_bound_implementation(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            Translator::class => new ScopedDefinition(Scope::TRANSIENT, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $container = $builder->build();
        $translator = $container->get(Translator::class);

        self::assertInstanceOf(RedisCache::class, $translator->cache);
    }

    #[Test]
    public function two_consumers_same_interface_different_implementations(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            ApcuCache::class => new ScopedDefinition(Scope::SINGLETON, ApcuCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
            SessionManager::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): SessionManager {
                return new SessionManager($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $builder->when(SessionManager::class)
            ->needs(CacheInterface::class)
            ->provide(ApcuCache::class);

        $container = $builder->build();

        $translator = $container->get(Translator::class);
        $session = $container->get(SessionManager::class);

        self::assertInstanceOf(RedisCache::class, $translator->cache);
        self::assertInstanceOf(ApcuCache::class, $session->cache);
    }

    #[Test]
    public function same_consumer_multiple_interfaces_bound(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            \Psr\Log\LoggerInterface::class => new ScopedDefinition(Scope::SINGLETON, NullLogger::class),
            CustomLogger::class => new ScopedDefinition(Scope::SINGLETON, CustomLogger::class),
            MultiDep::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): MultiDep {
                return new MultiDep(
                    $c->get(CacheInterface::class),
                    $c->get(\Psr\Log\LoggerInterface::class),
                );
            }),
        ]);

        $builder->when(MultiDep::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $builder->when(MultiDep::class)
            ->needs(\Psr\Log\LoggerInterface::class)
            ->provide(CustomLogger::class);

        $container = $builder->build();
        $multi = $container->get(MultiDep::class);

        self::assertInstanceOf(RedisCache::class, $multi->cache);
        self::assertInstanceOf(CustomLogger::class, $multi->logger);
    }

    #[Test]
    public function binding_does_not_leak_to_other_consumers(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
            SessionManager::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): SessionManager {
                return new SessionManager($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $container = $builder->build();

        $translator = $container->get(Translator::class);
        $session = $container->get(SessionManager::class);

        self::assertInstanceOf(RedisCache::class, $translator->cache);
        self::assertInstanceOf(FileCache::class, $session->cache);
    }

    #[Test]
    public function direct_get_unaffected_by_contextual_binding(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $container = $builder->build();

        $directCache = $container->get(CacheInterface::class);
        self::assertInstanceOf(FileCache::class, $directCache);
    }

    #[Test]
    public function all_existing_tests_still_pass(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $container = $builder->build();
        $translator = $container->get(Translator::class);

        self::assertInstanceOf(FileCache::class, $translator->cache);
    }

    #[Test]
    public function scoped_binding_persists_across_context_switch(): void
    {
        $provider = new \PHPdot\Container\Testing\TestContextProvider();
        $builder = new ContainerBuilder();
        $builder->withContextProvider($provider);

        $builder->addDefinitions([
            CacheInterface::class => new ScopedDefinition(Scope::SINGLETON, FileCache::class),
            RedisCache::class => new ScopedDefinition(Scope::SINGLETON, RedisCache::class),
            Translator::class => new ScopedDefinition(Scope::SCOPED, factory: static function (ContainerInterface $c): Translator {
                return new Translator($c->get(CacheInterface::class));
            }),
        ]);

        $builder->when(Translator::class)
            ->needs(CacheInterface::class)
            ->provide(RedisCache::class);

        $container = $builder->build();

        $t1 = $container->get(Translator::class);
        self::assertInstanceOf(RedisCache::class, $t1->cache);

        $provider->newContext();
        $t2 = $container->get(Translator::class);
        self::assertInstanceOf(RedisCache::class, $t2->cache);

        self::assertNotSame($t1, $t2);
    }

    #[Test]
    public function provide_without_needs_throws(): void
    {
        $builder = new ContainerBuilder();

        $this->expectException(\LogicException::class);

        $builder->when(Translator::class)
            ->provide(RedisCache::class);
    }
}
