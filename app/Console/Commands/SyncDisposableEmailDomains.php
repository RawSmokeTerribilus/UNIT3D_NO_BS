<?php

namespace App\Console\Commands;

use App\Models\DisposableEmailDomain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncDisposableEmailDomains extends Command
{
    protected $signature = 'email-blacklist:sync {--source=*} {--force} {--no-backup}';

    protected $description = 'Sincroniza dominios desechables desde múltiples fuentes remotas a la base de datos local';

    private int $totalAdded = 0;
    private int $totalSkipped = 0;
    private array $errors = [];

    public function handle(): int
    {
        if (!config('email-blacklist.enabled')) {
            $this->error('Email blacklist is disabled');
            return self::FAILURE;
        }

        $this->info('🌍 Sincronizando dominios de email desechables...');
        $this->newLine();

        try {
            // Respaldar datos actuales si es necesario
            if (!$this->option('no-backup')) {
                $this->backupCurrentData();
            }

            // Sincronizar fuentes remotas
            $this->syncRemoteSources();

            // Sincronizar dominios personalizados
            $this->syncCustomDomains();

            // Sincronizar servicios hardcodeados
            $this->syncHardcodedServices();

            // Mostrar estadísticas
            $this->showStatistics();

            $this->info('✅ Sincronización completada exitosamente');
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Email blacklist sync error: ' . $e->getMessage());
            $this->error('❌ Error durante la sincronización: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Sincroniza dominios desde fuentes remotas
     */
    private function syncRemoteSources(): void
    {
        $sources = config('email-blacklist.remote_sources', []);
        
        if (empty($sources)) {
            $this->warn('No remote sources configured');
            return;
        }

        $requestedSources = $this->option('source');

        foreach ($sources as $sourceName => $config) {
            // Saltar si no está habilitada
            if (!$config['enabled']) {
                $this->line("⊘ Saltando fuente deshabilitada: <fg=gray>$sourceName</>");
                continue;
            }

            // Filtrar por fuentes específicas si se solicitan
            if (!empty($requestedSources) && !in_array($sourceName, $requestedSources)) {
                continue;
            }

            $this->line("📥 Sincronizando desde: <fg=blue>$sourceName</>");

            try {
                $domains = $this->fetchDomainsFromRemoteSource($config);
                $this->insertDomainsIntoDB($domains, $sourceName);

                $this->line("   ✓ " . count($domains) . " dominios procesados");
            } catch (\Exception $e) {
                $this->errors[] = "$sourceName: " . $e->getMessage();
                $this->line("   ✗ Error: " . $e->getMessage(), 'error');

                if (!$config['fallback']) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Descarga dominios desde una fuente remota
     */
    private function fetchDomainsFromRemoteSource(array $config): array
    {
        $timeout = $config['timeout'] ?? 30;
        
        $this->line("   Descargando desde: " . $config['url']);

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['verify' => false]) // Para certificados SSL locales
                ->get($config['url']);

            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}");
            }

            return $this->parseDomainsFromResponse($response->body(), $config['type']);
        } catch (\Exception $e) {
            throw new \Exception("Error fetching from remote source: " . $e->getMessage());
        }
    }

    /**
     * Parsea dominios del contenido descargado
     */
    private function parseDomainsFromResponse(string $content, string $type): array
    {
        $domains = [];

        if ($type === 'json') {
            $data = json_decode($content, true);
            $domains = is_array($data) ? $data : [];
        } else {
            // Tipo 'list' - línea por línea
            $domains = array_filter(
                array_map('trim', explode("\n", $content)),
                fn($domain) => !empty($domain) && !str_starts_with($domain, '#')
            );
        }

        return array_map(fn($domain) => strtolower(trim($domain)), $domains);
    }

    /**
     * Inserta dominios en la BD de forma eficiente
     */
    private function insertDomainsIntoDB(array $domains, string $source): void
    {
        if (empty($domains)) {
            return;
        }

        // Obtener dominios existentes
        $existingDomains = DisposableEmailDomain::where('source', $source)
            ->pluck('domain')
            ->toArray();

        // Separar nuevos y existentes
        $newDomains = array_diff($domains, $existingDomains);
        $obsoleteDomains = array_diff($existingDomains, $domains);

        // Preparar datos para inserción masiva
        $insertData = array_map(
            fn($domain) => [
                'domain' => $domain,
                'source' => $source,
                'description' => "Imported from $source",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $newDomains
        );

        // Insertar nuevos en chunks para evitar memory overflow
        foreach (array_chunk($insertData, 1000) as $chunk) {
            try {
                DB::table('disposable_email_domains')->insertOrIgnore($chunk);
                $this->totalAdded += count($chunk);
            } catch (\Exception $e) {
                $this->line("   Warning: Batch insert error - " . $e->getMessage());
            }
        }

        // Eliminar dominios obsoletos (opcional)
        if (!empty($obsoleteDomains)) {
            DisposableEmailDomain::where('source', $source)
                ->whereIn('domain', $obsoleteDomains)
                ->delete();
        }

        $this->totalSkipped += count(array_intersect($domains, $existingDomains));
    }

    /**
     * Sincroniza dominios personalizados desde configuración
     */
    private function syncCustomDomains(): void
    {
        $customDomainsString = config('email-blacklist.custom_domains');

        if (empty($customDomainsString)) {
            return;
        }

        $this->line("📝 Sincronizando dominios personalizados");

        $domains = array_filter(
            array_map('trim', explode('|', $customDomainsString)),
            fn($domain) => !empty($domain)
        );

        $this->insertDomainsIntoDB($domains, 'custom');
        $this->line("   ✓ " . count($domains) . " dominios personalizados sincronizados");
    }

    /**
     * Sincroniza servicios hardcodeados
     */
    private function syncHardcodedServices(): void
    {
        $this->line("🔒 Sincronizando servicios conocidos (hardcoded)");

        $services = config('email-blacklist.hardcoded_services', []);
        $domains = array_map('strtolower', $services);

        $this->insertDomainsIntoDB($domains, 'hardcoded');
        $this->line("   ✓ " . count($domains) . " servicios conocidos sincronizados");
    }

    /**
     * Respalda datos actuales
     */
    private function backupCurrentData(): void
    {
        try {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $count = DisposableEmailDomain::count();

            if ($count > 0) {
                DB::table('disposable_email_domains_backup_' . $timestamp)
                    ->insertUsing(
                        ['domain', 'source', 'description', 'created_at', 'updated_at'],
                        DisposableEmailDomain::query()
                    );

                $this->line("💾 Respaldo creado: disposable_email_domains_backup_$timestamp ($count registros)");
            }
        } catch (\Exception $e) {
            $this->warn("No backup created: " . $e->getMessage());
        }
    }

    /**
     * Muestra estadísticas de la sincronización
     */
    private function showStatistics(): void
    {
        $this->newLine();
        $this->info('📊 Estadísticas de Sincronización:');

        $stats = DisposableEmailDomain::countBySource();
        foreach ($stats as $source => $count) {
            $this->line("   • <fg=cyan>$source</>: <fg=green>$count</> dominios");
        }

        $totalDomains = DisposableEmailDomain::count();
        $this->line("   • <fg=yellow>Total</>: <fg=green>$totalDomains</> dominios únicos");

        if (!empty($this->errors)) {
            $this->newLine();
            $this->warn('⚠️  Errores durante la sincronización:');
            foreach ($this->errors as $error) {
                $this->line("   • $error", 'error');
            }
        }
    }
}
