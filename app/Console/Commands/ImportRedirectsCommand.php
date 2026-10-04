<?php

namespace App\Console\Commands;

use App\Models\Redirect;
use Illuminate\Console\Command;

class ImportRedirectsCommand extends Command
{
    protected $signature = 'redirects:import {file : CSV file with columns from,to[,status]}';

    protected $description = 'Bulk import 301 redirects from a CSV file (e.g. exported from Search Console)';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Cannot read {$file}");

            return self::FAILURE;
        }
        $handle = fopen($file, 'r');
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || str_starts_with(trim($row[0]), '#') || strtolower(trim($row[0])) === 'from') {
                continue;
            }
            $from = Redirect::normalizePath(trim($row[0]));
            $to = trim($row[1]);
            if ($from === '' || $to === '' || $from === Redirect::normalizePath($to)) {
                continue;
            }
            Redirect::updateOrCreate(['from_path' => $from], [
                'to_path' => $to,
                'status_code' => isset($row[2]) && in_array((int) $row[2], [301, 302, 307, 308], true) ? (int) $row[2] : 301,
            ]);
            $count++;
        }
        fclose($handle);
        $this->info("Imported {$count} redirects.");

        return self::SUCCESS;
    }
}
