<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictConstantReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/demo',
        // DBAL 3/4 FK stub overrides must stay untyped to match both parent signatures.
        __DIR__ . '/tests/Schema/Definition/SchemaDefinitionParserTest.php',
        // A stream wrapper's url_stat() takes $flags whether or not this one reads it: the signature is the API.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__ . '/tests/Versions/MonotonicVersionsFixFailureTest.php'],
        // Test doubles of a seam that returns resource|false must stay untyped, like the method they override.
        BoolReturnTypeFromBooleanConstReturnsRector::class => [__DIR__ . '/tests/Versions/MonotonicVersionsGitTest.php'],
        ReturnTypeFromStrictConstantReturnRector::class    => [__DIR__ . '/tests/Versions/MonotonicVersionsGitTest.php'],
        // Gate fixtures keep the return type their parent declares, so they read as the override they are.
        NarrowObjectReturnTypeRector::class => [__DIR__ . '/tests/Test/Fixture'],
    ])
    // Machine-wide cap: at most 2 worker processes; generous timeout for slow (WSL2 / CI) hosts.
    ->withParallel(300, 2, 10)
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
