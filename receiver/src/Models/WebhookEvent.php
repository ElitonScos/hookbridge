<?php
declare(strict_types=1);
namespace App\Models;

use App\Database\Connection;
use PDO;

class WebhookEvent
{
    public static function create(string $source, string $eventType, array $payload, string $signature): array
    {
        $db = Connection::get();
        $stmt = $db->prepare(
            'INSERT INTO webhook_events (source, event_type, payload, signature, status)
             VALUES (:source, :event_type, :payload::jsonb, :signature, :status)
             RETURNING id, source, event_type, payload::text, status, received_at'
        );
        $stmt->execute([
            ':source'     => $source,
            ':event_type' => $eventType,
            ':payload'    => json_encode($payload),
            ':signature'  => $signature,
            ':status'     => 'pending',
        ]);
        $row = $stmt->fetch();
        $row['payload'] = json_decode($row['payload'], true);
        return $row;
    }

    public static function list(int $limit, int $offset, ?string $status): array
    {
        $db = Connection::get();
        $where  = $status ? 'WHERE status = :status' : '';
        $params = [];
        if ($status) $params[':status'] = $status;

        $total = $db->prepare("SELECT COUNT(*) FROM webhook_events $where");
        $total->execute($params);

        $stmt = $db->prepare(
            "SELECT id, source, event_type, status, error_msg, received_at, processed_at
             FROM webhook_events $where ORDER BY received_at DESC LIMIT :limit OFFSET :offset"
        );
        if ($status) $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'total' => (int) $total->fetchColumn(),
                'limit' => $limit, 'offset' => $offset];
    }

    public static function find(string $id): ?array
    {
        $db = Connection::get();
        $stmt = $db->prepare(
            'SELECT id, source, event_type, payload::text, status, error_msg, received_at, processed_at
             FROM webhook_events WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['payload'] = json_decode($row['payload'], true);
        return $row;
    }
}
