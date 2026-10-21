<?php

namespace App\Console\Commands;

use App\Actions\Jobs\UpdateExchangeRates;
use Illuminate\Console\Command;

class UpdateExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:update';

    protected $description = 'Actualiza las tasas de cambio desde la API externa';

    public function handle(UpdateExchangeRates $action): int
    {
        $this->info('Actualizando tasas de cambio...');

        $success = $action->handle();

        if ($success) {
            $this->info('✓ Tasas actualizadas correctamente');

            return self::SUCCESS;
        }

        $this->error('✗ No se pudieron actualizar las tasas. Se mantienen las anteriores.');

        return self::FAILURE;
    }
}
