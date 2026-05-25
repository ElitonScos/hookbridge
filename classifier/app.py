from flask import Flask, request, jsonify

app = Flask(__name__)

RULES: dict[str, list[str]] = {
    "payment":  ["payment", "charge", "invoice", "refund", "transaction", "order"],
    "user":     ["user", "signup", "login", "account", "profile", "password"],
    "delivery": ["shipment", "delivery", "tracking", "dispatch", "shipped"],
    "alert":    ["error", "failure", "warning", "critical", "alert", "down"],
}


def classify(event_type: str, payload: dict) -> str:
    text = (event_type + " " + " ".join(str(v) for v in payload.values())).lower()
    scores: dict[str, int] = {cat: 0 for cat in RULES}
    for cat, keywords in RULES.items():
        for kw in keywords:
            if kw in text:
                scores[cat] += 1
    best = max(scores, key=lambda c: scores[c])
    return best if scores[best] > 0 else "general"


@app.get("/health")
def health():
    return jsonify({"status": "healthy", "service": "classifier"})


@app.post("/classify")
def classify_endpoint():
    data = request.get_json(force=True) or {}
    event_type = data.get("event_type", "")
    payload = data.get("payload", {})
    category = classify(event_type, payload)
    return jsonify({"category": category, "event_type": event_type})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
