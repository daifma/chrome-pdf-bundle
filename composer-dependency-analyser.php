<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

$config = $config
    ->addPathToScan(__DIR__.'/bin', isDev: true)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/src/DataCollector', isDev: true)
    ->addPathToScan(__DIR__.'/src/Debug', isDev: true)
    ->addPathToScan(__DIR__.'/tests', isDev: true)

    ->ignoreErrorsOnPackage('async-aws/s3', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    // Not referenced by our code: required at runtime by chrome-php's websocket client, declared explicitly
    // so a missing extension fails at install time (notably on Windows where it is often disabled).
    ->ignoreErrorsOnExtension('ext-sockets', [
        ErrorType::UNUSED_DEPENDENCY,
    ])
    // Local adapter shipped with league/flysystem 3, only used by tests.
    ->ignoreErrorsOnPackage('league/flysystem-local', [
        ErrorType::SHADOW_DEPENDENCY,
    ])
    ->ignoreErrorsOnPackage('league/flysystem', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    // Optional: async generation is only registered when Messenger and HttpClient are installed.
    ->ignoreErrorsOnPackage('symfony/messenger', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    ->ignoreErrorsOnPackage('symfony/http-client', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    ->ignoreErrorsOnPackage('symfony/routing', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    ->ignoreErrorsOnPackage('twig/twig', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    ->ignoreErrorsOnPackage('symfony/asset', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
    ->ignoreErrorsOnPackage('symfony/asset-mapper', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ])
;

if (\PHP_VERSION_ID < 80200) {
    $config = $config
        ->ignoreUnknownClasses([
            SensitiveParameter::class,
        ])
    ;
}

return $config;
