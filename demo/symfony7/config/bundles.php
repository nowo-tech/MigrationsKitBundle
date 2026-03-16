<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class            => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                      => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                    => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class        => ['dev' => true, 'test' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class             => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Nowo\MigrationsKitBundle\NowoMigrationsKitBundle::class          => ['all' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class          => ['dev' => true, 'test' => true],
];
