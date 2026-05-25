package main

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/joho/godotenv"
	"go.uber.org/zap"
)

var logger *zap.Logger

func main() {
	godotenv.Load()
	logger, _ = zap.NewProduction()
	defer logger.Sync()

	pool, err := pgxpool.New(context.Background(), os.Getenv("DATABASE_URL"))
	if err != nil {
		logger.Fatal("failed to connect to database", zap.Error(err))
	}
	defer pool.Close()

	classifierURL := os.Getenv("CLASSIFIER_URL")
	if classifierURL == "" {
		classifierURL = "http://classifier:5000"
	}

	logger.Info("processor started", zap.String("classifier", classifierURL))

	ctx, cancel := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer cancel()

	ticker := time.NewTicker(3 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			logger.Info("processor shutting down")
			return
		case <-ticker.C:
			if err := processPending(ctx, pool, classifierURL); err != nil {
				logger.Error("processing error", zap.Error(err))
			}
		}
	}
}

type event struct {
	ID        string
	Source    string
	EventType string
	Payload   []byte
}

func processPending(ctx context.Context, pool *pgxpool.Pool, classifierURL string) error {
	rows, err := pool.Query(ctx,
		`UPDATE webhook_events SET status='processing'
		 WHERE id IN (SELECT id FROM webhook_events WHERE status='pending' LIMIT 5)
		 RETURNING id, source, event_type, payload`,
	)
	if err != nil {
		return err
	}
	defer rows.Close()

	var events []event
	for rows.Next() {
		var e event
		if err := rows.Scan(&e.ID, &e.Source, &e.EventType, &e.Payload); err != nil {
			return err
		}
		events = append(events, e)
	}

	for _, e := range events {
		category := classify(classifierURL, e.EventType, e.Payload)

		_, err = pool.Exec(ctx,
			`UPDATE webhook_events SET status='processed', category=$1, processed_at=now() WHERE id=$2`,
			category, e.ID,
		)
		if err != nil {
			logger.Error("failed to update event", zap.String("id", e.ID), zap.Error(err))
			continue
		}
		logger.Info("event processed",
			zap.String("id", e.ID),
			zap.String("source", e.Source),
			zap.String("category", category),
		)
	}
	return nil
}

func classify(baseURL, eventType string, payload []byte) string {
	reqBody, _ := json.Marshal(map[string]interface{}{
		"event_type": eventType,
		"payload":    json.RawMessage(payload),
	})

	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Post(baseURL+"/classify", "application/json", bytes.NewReader(reqBody))
	if err != nil {
		logger.Warn("classifier unavailable, defaulting to 'general'", zap.Error(err))
		return "general"
	}
	defer resp.Body.Close()

	var result struct {
		Category string `json:"category"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return "general"
	}
	return result.Category
}
