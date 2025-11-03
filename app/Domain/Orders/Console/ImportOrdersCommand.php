<?php

namespace App\Domain\Orders\Console;

use Illuminate\Console\Command;
use App\Domain\Orders\Jobs\ImportOrdersJob;
use League\Csv\Reader;

class ImportOrdersCommand extends Command
{
    protected $signature = 'orders:import {file}';
    protected $description = 'Import orders from a CSV file and queue them for processing';

    public function handle()
    {
        $path = $this->argument('file');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return;
        }

        $this->info("Importing orders from {$path}...");

        $csv = Reader::createFromPath($path)->setHeaderOffset(0);
        $records = collect(iterator_to_array($csv->getRecords()));

        $records->chunk(100)->each(function ($chunk) {
            ImportOrdersJob::dispatch($chunk->toArray());
        });

        $this->info('Orders queued for import successfully!');
    }
}
