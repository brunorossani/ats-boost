<?php

namespace App\Console\Commands;

use App\Actions\Jobs\NormalizeSalary;
use App\Models\JobListing;
use Illuminate\Console\Command;

class NormalizeSalariesCommand extends Command
{
    protected $signature = 'jobs:normalize-salaries {--force : Force re-normalization of all listings}';

    protected $description = 'Normalize salary data for job listings to USD/month';

    public function handle(NormalizeSalary $normalize): int
    {
        $query = JobListing::query()
            ->where(function ($q) {
                $q->whereNotNull('salary_min')
                    ->orWhereNotNull('salary_max');
            });

        if (! $this->option('force')) {
            $query->whereNull('salary_min_usd_month');
        }

        $listings = $query->get();

        if ($listings->isEmpty()) {
            $this->info('No listings to normalize.');

            return self::SUCCESS;
        }

        $this->info("Normalizing {$listings->count()} listings...");
        $bar = $this->output->createProgressBar($listings->count());

        $normalized = 0;
        $skipped = 0;

        foreach ($listings as $listing) {
            $result = $normalize->handle(
                amount: $listing->salary_min,
                currency: $listing->salary_currency,
                period: null,
            );

            $updates = [];

            if ($result['normalized_usd_month']) {
                $updates['salary_min_usd_month'] = $result['normalized_usd_month'];
                $updates['salary_period'] = $result['period'];
                $updates['salary_is_estimated'] = $result['is_estimated'];
            }

            if ($listing->salary_max) {
                $resultMax = $normalize->handle(
                    amount: $listing->salary_max,
                    currency: $listing->salary_currency,
                    period: $result['period'],
                );

                if ($resultMax['normalized_usd_month']) {
                    $updates['salary_max_usd_month'] = $resultMax['normalized_usd_month'];
                }
            }

            if (! empty($updates)) {
                $listing->update($updates);
                $normalized++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Normalized: {$normalized}");

        if ($skipped > 0) {
            $this->warn("Skipped: {$skipped}");
        }

        return self::SUCCESS;
    }
}
