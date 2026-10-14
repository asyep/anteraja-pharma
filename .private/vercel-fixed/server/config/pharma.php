<?php

return [
    'demo_enabled' => env('PHARMA_DEMO_ENABLED', false),
    'excursion_seconds' => 180,
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3170'),
    'telegram_token' => env('TELEGRAM_BOT_TOKEN'),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'telegram_webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'automation_secret' => env('AUTOMATION_WEBHOOK_SECRET'),
    'demo_password' => env('DEMO_PASSWORD'),
    'cron_secret' => env('CRON_SECRET'),
    'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
    'courier_chat_id' => env('TELEGRAM_COURIER_CHAT_ID'),
    'buyer_chat_id' => env('TELEGRAM_BUYER_CHAT_ID'),
    'checker_chat_id' => env('TELEGRAM_CHECKER_CHAT_ID'),
    'sender_chat_id' => env('TELEGRAM_SENDER_CHAT_ID'),
];
