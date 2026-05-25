<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\WebhookEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WebhookController
{
    public function receive(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $source    = $args['source'] ?? 'unknown';
        $body      = (array) $request->getParsedBody();
        $eventType = $body['event'] ?? $body['type'] ?? 'generic';
        $signature = $request->getHeaderLine('X-Hub-Signature-256');

        $event = WebhookEvent::create($source, $eventType, $body, $signature);

        $response->getBody()->write(json_encode(['status' => 'accepted', 'event_id' => $event['id']]));
        return $response->withStatus(202)->withHeader('Content-Type', 'application/json');
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit  = min((int) ($params['limit'] ?? 20), 100);
        $offset = (int) ($params['offset'] ?? 0);
        $status = $params['status'] ?? null;

        $result = WebhookEvent::list($limit, $offset, $status);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $event = WebhookEvent::find($args['id']);
        if (!$event) {
            $response->getBody()->write(json_encode(['error' => 'not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(json_encode($event));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
