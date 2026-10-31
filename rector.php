<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/config/bundles.php',
        __DIR__.'/config/reference.php',
        // Static or instance assertion calls are a project choice: keep the one in place.
        PreferPHPUnitThisCallRector::class,
    ])
    ->withCache(__DIR__.'/var/cache/rector')
    ->withPhpSets()
    ->withComposerBased(phpunit: true, symfony: true)
    ->withAttributesSets(symfony: true, phpunit: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        instanceOf: true,
        phpunitCodeQuality: true,
    )
    ->withSets([
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withParallel()
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml')
;
