<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook
                            {--force : Force webhook registration even if already set}
                            {--test : Test webhook without registering}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register Telegram webhook endpoint with Telegram API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $botToken = config('services.telegram.token');

        if (empty($botToken)) {
            $this->error('❌ TELEGRAM_BOT_TOKEN no está configurado en .env');
            Log::error('Telegram: SetWebhook command failed - missing bot token');
            return self::FAILURE;
        }

        $webhookUrl = $this->resolveWebhookUrl();

        if (empty($webhookUrl)) {
            $this->error('❌ APP_URL no está configurado en .env');
            Log::error('Telegram: SetWebhook command failed - missing APP_URL');
            return self::FAILURE;
        }

        $webhookEndpoint = "{$webhookUrl}/api/telegram/webhook";

        // Mode: Test only (check current webhook without modifying)
        if ($this->option('test')) {
            return $this->testWebhookStatus($botToken);
        }

        // Check if webhook is already set
        $currentWebhook = $this->getCurrentWebhook($botToken);

        if ($currentWebhook && !$this->option('force')) {
            $this->warn('⚠️  Webhook ya está registrado:');
            $this->line("  URL: {$currentWebhook}");
            $this->info('Usa --force para re-registrar');
            return self::SUCCESS;
        }

        // Register webhook
        $this->registerWebhook($botToken, $webhookEndpoint);

        // Verify registration
        $verified = $this->getCurrentWebhook($botToken);

        if ($verified && $verified === $webhookEndpoint) {
            $this->info('✅ Webhook registrado correctamente:');
            $this->line("  URL: {$verified}");
            Log::info('Telegram: Webhook registered successfully', ['url' => $verified]);
            return self::SUCCESS;
        } else {
            $this->error('❌ Webhook no pudo ser verificado tras registro');
            Log::error('Telegram: Webhook registration failed verification', [
                'requested_url' => $webhookEndpoint,
                'verified_url'  => $verified,
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Resolve the webhook URL from APP_URL configuration.
     */
    private function resolveWebhookUrl(): string
    {
        $appUrl = config('app.url');

        if (empty($appUrl) || $appUrl === 'http://localhost') {
            $this->warn('⚠️  APP_URL es localhost - webhook solo puede ser HTTPS en producción');
            return '';
        }

        return rtrim($appUrl, '/');
    }

    /**
     * Get current webhook URL from Telegram API.
     */
    private function getCurrentWebhook(string $botToken): ?string
    {
        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getWebhookInfo");

            if (!$response->successful()) {
                $this->warn("⚠️  Telegram API error: {$response->status()}");
                Log::warning('Telegram: getWebhookInfo failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();

            if (!isset($data['ok']) || !$data['ok']) {
                $this->warn('⚠️  Telegram API error: ' . ($data['description'] ?? 'unknown error'));
                Log::warning('Telegram: getWebhookInfo returned error', ['response' => $data]);
                return null;
            }

            return $data['result']['url'] ?: null;
        } catch (\Exception $e) {
            $this->error("❌ Error conectando con Telegram API: {$e->getMessage()}");
            Log::error('Telegram: getWebhookInfo exception', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return null;
        }
    }

    /**
     * Register webhook with Telegram API.
     */
    private function registerWebhook(string $botToken, string $webhookUrl): void
    {
        $this->line("Registrando webhook: {$webhookUrl}");

        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['message'],  // Solo mensajes
            ]);

            if (!$response->successful()) {
                $this->error("❌ Telegram API HTTP {$response->status()}: {$response->body()}");
                Log::error('Telegram: setWebhook HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return;
            }

            $data = $response->json();

            if (!isset($data['ok']) || !$data['ok']) {
                $this->error('❌ Telegram API error: ' . ($data['description'] ?? 'unknown'));
                Log::error('Telegram: setWebhook API error', ['response' => $data]);
                return;
            }

            $this->info('✓ Webhook registrado en Telegram API');
        } catch (\Exception $e) {
            $this->error("❌ Exception calling Telegram API: {$e->getMessage()}");
            Log::error('Telegram: setWebhook exception', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        }
    }

    /**
     * Test webhook status without modifying.
     */
    private function testWebhookStatus(string $botToken): int
    {
        $this->info('🔍 Checking current webhook status...');

        $currentWebhook = $this->getCurrentWebhook($botToken);

        if ($currentWebhook) {
            $this->info("✓ Webhook registrado: {$currentWebhook}");
            return self::SUCCESS;
        } else {
            $this->warn('⚠️  No hay webhook registrado');
            return self::FAILURE;
        }
    }
}
