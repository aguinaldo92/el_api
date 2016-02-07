<?php

// Including global autoloader
require_once dirname(__FILE__) . '\vendor\autoload.php';

use el_api\UtilityClass;

// Init config data
$config = array();


if (empty($_ENV['SLIM_MODE'])) {
    $_ENV['SLIM_MODE'] = (getenv('SLIM_MODE')) ? getenv('SLIM_MODE') : 'development';
}


// Load config file
$configFile = dirname(__FILE__) . '\share\config\default.php';

if (is_readable($configFile)) {
    require_once $configFile;
}

// Basic config for Slim Application
$config['app'] = array(
    'db.dsn' => 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
    'db.username' => DB_USERNAME,
    'db.password' => DB_PASSWORD,
    'name' => 'Elearning',
    'log.enabled' => true,
    'log.level' => Slim\Log::INFO,
    'mode' => (!empty($_ENV['SLIM_MODE'])) ? $_ENV['SLIM_MODE'] : 'production'
);

// Create application instance with config
$app = new Slim\Slim($config['app']);


// Add dependencies
$app->container->singleton('PDO', function ($container) {
    try {
        $settings = $container['settings'];
        $pdo = new PDO($settings['db.dsn'], $settings['db.username'], $settings['db.password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        $response["status"] = "error";
        $response["message"] = 'Connection failed: ' . $e->getMessage();
        $response["data"] = null;
        $status_code = 503;
        UtilityClass::echoResponse($status_code, $response);
        exit('unable to connect to Database');
    }
});

$app->container->singleton('log', function () {
    $log = new \Monolog\Logger('el_api_logs');
    $log->pushHandler(new \Monolog\Handler\StreamHandler(dirname(__FILE__) . '/share/logs/' . date('Y-m-d') . '.log'));
    return $log;
});
$log = $app->container['log'];


// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
    $app->config(array(
        'log.enable' => TRUE,
        'log.level' => Slim\Log::WARN,
        'debug' => false
    ));
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
    $app->config(array(
        'log.enable' => true,
        'log.level' => Slim\Log::DEBUG,
        'log.writer' => $app->container['log'],
        'debug' => true
    ));
});

$app->add(new \Slim\Middleware\JwtAuthentication([
    "logger" => $log,
    "secret" => SECRETJWT,
    "rules" => [
        new \Slim\Middleware\JwtAuthentication\RequestPathRule([
            "path" => "/api/v1",
            "passthrough" => ["/api/v1/login", "/api/v1/signup"]
                ]),
        new \Slim\Middleware\JwtAuthentication\RequestMethodRule([
            "passthrough" => ["OPTIONS"]
                ])
    ],
    "callback" => function ($decoded, $app) {
        $app->jwt = $decoded;
        
    }
]));

