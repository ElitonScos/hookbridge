require 'sinatra'
require 'json'

set :bind, '0.0.0.0'
set :port, 5000

RULES = {
  'payment'  => %w[payment charge invoice refund transaction order],
  'user'     => %w[user signup login account profile password],
  'delivery' => %w[shipment delivery tracking dispatch shipped],
  'alert'    => %w[error failure warning critical alert down]
}.freeze

def classify(event_type, payload)
  text = "#{event_type} #{payload.values.join(' ')}".downcase
  scores = RULES.transform_values { |keywords| keywords.count { |kw| text.include?(kw) } }
  best = scores.max_by { |_, v| v }
  best[1] > 0 ? best[0] : 'general'
end

get '/health' do
  content_type :json
  { status: 'healthy', service: 'classifier' }.to_json
end

post '/classify' do
  content_type :json
  body = JSON.parse(request.body.read)
  event_type = body['event_type'] || ''
  payload    = body['payload'] || {}
  category   = classify(event_type, payload)
  { category: category, event_type: event_type }.to_json
end
