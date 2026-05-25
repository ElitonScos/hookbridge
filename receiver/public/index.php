<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\WebhookController;
use App\Middleware\HmacMiddleware;
use Slim\Factory\AppFactory;

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/health', function ($req, $res) {
    $res->getBody()->write(json_encode(['status' => 'healthy', 'service' => 'receiver']));
    return $res->withHeader('Content-Type', 'application/json');
});

$app->get('/api/v1/events', [WebhookController::class, 'index']);
$app->get('/api/v1/events/{id}', [WebhookController::class, 'show']);

$app->post('/api/v1/webhooks/{source}', [WebhookController::class, 'receive'])
    ->add(HmacMiddleware::class);

$app->run();
