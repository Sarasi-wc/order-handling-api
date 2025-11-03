<?php

namespace App\Domain\Orders\Console;

use App\Domain\Orders\Jobs\ImportOrdersJob;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportOrdersCommand extends Command
{
    protected $signature = 'orders:import {file}';

    protected $description = 'Import orders from a CSV file and queue them for processing';

    public function handle(): void
    {
        $path = $this->argument('file');

        $resolvedPath = file_exists($path)
            ? $path
            : storage_path('app/'.ltrim($path, '/'));

        if (! file_exists($resolvedPath)) {
            $this->error("File not found: {$resolvedPath}");

            return;
        }

        $this->info("Importing orders from {$resolvedPath}...");

        $csv = Reader::createFromPath($resolvedPath)->setHeaderOffset(0);
        $records = collect(iterator_to_array($csv->getRecords()));

        $records->chunk(100)->each(function ($chunk, $i) {
            ImportOrdersJob::dispatch($chunk->toArray());
            $this->info("Queued chunk #{$i} (".count($chunk).' rows)');
        });

        $this->info('Orders queued for import successfully!');
    }
}
