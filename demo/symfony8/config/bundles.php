<?php

declare(strict_types=1);
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Nowo\MigrationsKitBundle\NowoMigrationsKitBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class          => ['all' => true],
    TwigBundle::class               => ['all' => true],
    DebugBundle::class              => ['dev' => true, 'test' => true],
    WebProfilerBundle::class        => ['dev' => true, 'test' => true],
    DoctrineBundle::class           => ['all' => true],
    DoctrineMigrationsBundle::class => ['all' => true],
    NowoMigrationsKitBundle::class  => ['all' => true],
    NowoHotReloadBundle::class      => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class  => ['dev' => true, 'test' => true],
    TwigExtraBundle::class          => ['all' => true],
];
