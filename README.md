# HookBridge

Polyglot webhook processing platform three specialized services, each written in the language best suited for its role: PHP receives and validates incoming webhooks, Go processes and dispatches events concurrently, and Python classifies payloads using keyword-based rules.

---

## Architecture

```
External Service → POST /api/v1/webhooks/{source}
                          ↓
             PHP (Slim 4) - HMAC validation + persist
                          ↓
                     PostgreSQL
                          ↓
          Go Processor - polls every 3s, dispatches
                          ↓
        Python Classifier - categorizes payload
                          ↓
             PostgreSQL - status: processed + category
```

---

## Services

| Service | Language | Role |
|---------|----------|------|
| `receiver` | PHP 8.2 + Slim 4 | Webhook ingestion, HMAC-SHA256 validation, REST API |
| `processor` | Go 1.23 + pgx | Concurrent event processing, classifier integration |
| `classifier` | Ruby 3.3 + Sinatra | Keyword-based payload categorization |
| `db` | PostgreSQL 16 | Shared event store with JSONB payloads |

---

## Tech Stack

- **PHP 8.2** + **Slim 4** - lightweight REST framework
- **Go 1.23** + **pgx/v5** + **zap** - concurrent processor with structured logging
- **Ruby 3.3** + **Sinatra** - classification microservice
- **PostgreSQL 16** - JSONB event storage
- **Docker** + **Docker Compose**

---

## Getting Started

```bash
git clone https://github.com/ElitonScos/hookbridge.git
cd hookbridge

cp .env.example .env

docker compose up -d
```

Receiver API available at `http://localhost:8002`

---

## Environment Variables

```env
DB_HOST=db
DB_NAME=hookbridge
DB_USER=hbuser
DB_PASS=hbpass
DATABASE_URL=postgresql://hbuser:hbpass@db:5432/hookbridge
WEBHOOK_SECRET=super-secret-key-change-in-production
CLASSIFIER_URL=http://classifier:5000
```

---

## API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check |
| POST | `/api/v1/webhooks/{source}` | Receive webhook (HMAC required) |
| GET | `/api/v1/events` | List events (filterable by status) |
| GET | `/api/v1/events/{id}` | Get event details |

---

## Sending a webhook

```bash
SECRET="super-secret-key-change-in-production"
PAYLOAD='{"event":"payment.completed","order_id":"abc123","amount":199.90}'
SIG="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)"

curl -X POST http://localhost:8002/api/v1/webhooks/stripe \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: $SIG" \
  -d "$PAYLOAD"
```

---

## Project Structure

```
hookbridge/
├── receiver/               - PHP Slim 4 API
│   ├── public/index.php    - entrypoint
│   ├── src/
│   │   ├── Controllers/
│   │   ├── Middleware/     - HMAC validation
│   │   ├── Models/
│   │   └── Database/
│   └── migrations/
├── processor/              - Go concurrent worker
│   └── cmd/processor/
│       └── main.go
├── classifier/             - Python Flask classifier
│   ├── app.py
│   └── requirements.txt
├── docker/
│   ├── Dockerfile.receiver
│   ├── Dockerfile.processor
│   └── Dockerfile.classifier
├── docker-compose.yml
└── .env.example
```
