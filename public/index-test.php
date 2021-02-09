<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Config\Config;
use Yiisoft\Di\Container;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\HtmlRenderer;
use Yiisoft\ErrorHandler\ThrowableRendererInterface;
use Yiisoft\Http\Method;
use Yiisoft\Yii\Web\Application;
use Yiisoft\Yii\Web\SapiEmitter;
use Yiisoft\Yii\Web\ServerRequestFactory;

$c3 = dirname(__DIR__) . '/c3.php';

if (is_file($c3)) {
    require_once $c3;
}

// PHP built-in server routing.
if (PHP_SAPI === 'cli-server') {
    // Serve static files as is.
    if (is_file(__DIR__ . $_SERVER["REQUEST_URI"])) {
        return false;
    }

    // Explicitly set for URLs with dot.
    $_SERVER['SCRIPT_NAME'] = '/index-test.php';
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$config = new Config(dirname(__DIR__));

$startTime = microtime(true);

/**
 * Register temporary error handler to catch error while container is building.
 */
$errorHandler = new ErrorHandler(new NullLogger(), new HtmlRenderer());
/**
 * Production mode
 * $errorHandler = $errorHandler->withoutExposedDetails();
 */
$errorHandler->register();

$container = new Container(
    $config->get('web'),
    $config->get('providers-web'),
);

/**
 * Configure error handler with real container-configured dependencies
 */
$errorHandler->setLogger($container->get(LoggerInterface::class));
$errorHandler->setRenderer($container->get(ThrowableRendererInterface::class));

$container = $container->get(ContainerInterface::class);
$application = $container->get(Application::class);

$request = $container->get(ServerRequestFactory::class)->createFromGlobals();
$request = $request->withAttribute('applicationStartTime', $startTime);

try {
    $application->start();
    $response = $application->handle($request);
    $emitter = new SapiEmitter();
    $emitter->emit($response, $request->getMethod() === Method::HEAD);
} finally {
    $application->afterEmit($response ?? null);
    $application->shutdown();
}
