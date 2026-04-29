<?php

namespace App\Console\Commands;

use App\Models\DisposableEmailDomain;
use Illuminate\Console\Command;

class EmailBlacklistStatus extends Command
{
    protected $signature = 'email-blacklist:status';

    protected $description = 'Muestra el estado actual de la lista de dominios desechables';

    public function handle(): int
    {
        $this->info('📊 Estado de la Lista de Dominios Desechables');
        $this->newLine();

        // Contar total
        $total = DisposableEmailDomain::count();
        
        if ($total === 0) {
            $this->warn('❌ No hay dominios en la base de datos.');
            $this->line('Ejecuta: <fg=blue>php artisan email-blacklist:sync</>');
            return self::FAILURE;
        }

        // Estadísticas por fuente
        $stats = DisposableEmailDomain::countBySource();

        $this->line("Total de dominios: <fg=green>$total</>");
        $this->newLine();

        $this->line('Desglose por fuente:');
        foreach ($stats as $source => $count) {
            $percentage = round(($count / $total) * 100, 1);
            $this->line("  • <fg=cyan>$source</>: <fg=yellow>$count</> ($percentage%)");
        }

        $this->newLine();
        
        // Mostrar algunas fuentes disponibles
        $recentDomains = DisposableEmailDomain::latest('id')
            ->limit(5)
            ->pluck('domain')
            ->toArray();

        $this->line('Últimos dominios sincronizados:');
        foreach ($recentDomains as $domain) {
            $this->line("  • $domain");
        }

        $this->newLine();
        
        if (config('email-blacklist.sync.enabled')) {
            $this->line('⏰ Sincronización automática: <fg=green>Habilitada</>');
            $this->line('   Schedule: <fg=blue>' . config('email-blacklist.sync.schedule') . '</>');
        } else {
            $this->line('⏰ Sincronización automática: <fg=red>Deshabilitada</>');
        }

        return self::SUCCESS;
    }
}
