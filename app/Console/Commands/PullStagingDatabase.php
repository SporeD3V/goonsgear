<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class PullStagingDatabase extends Command
{
    protected $signature = 'db:pull-staging
        {--host= : SSH host}
        {--port= : SSH port}
        {--user= : SSH user}
        {--remote-path= : Remote app path}
        {--dump-only : Only download the dump file, do not import}';

    protected $description = 'Pull the staging database to the local environment';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command can only be run in the local environment.');

            return 1;
        }

        $host = $this->option('host') ?: config('services.staging.ssh_host');
        $port = $this->option('port') ?: config('services.staging.ssh_port');
        $user = $this->option('user') ?: config('services.staging.ssh_user');
        $remotePath = $this->option('remote-path') ?: config('services.staging.ssh_path');

        if (! $host || ! $port || ! $user || ! $remotePath) {
            $this->error('Staging SSH credentials are not configured. Set STAGING_SSH_* in your .env file.');

            return 1;
        }
        $dumpFile = storage_path('app/staging-dump.sql.gz');

        $localDb = config('database.connections.mysql.database');
        $localUser = config('database.connections.mysql.username');
        $localPass = config('database.connections.mysql.password');
        $localHost = config('database.connections.mysql.host');
        $localPort = config('database.connections.mysql.port');

        $this->info("Pulling staging database to local '{$localDb}'...");
        $this->warn('This will REPLACE all data in your local database.');

        if (! $this->confirm('Continue?')) {
            return 0;
        }

        // Step 1: Dump staging DB via SSH
        $this->info('[1/3] Creating dump on staging server...');

        $remoteScript = <<<'BASH'
            set -eu
            cd "$1"
            # Read DB credentials from .env
            DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2-)
            DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2-)
            DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d= -f2-)
            DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d= -f2-)
            DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
            # Dump and compress
            mysqldump \
                --host="$DB_HOST" \
                --port="${DB_PORT:-3306}" \
                --user="$DB_USERNAME" \
                --password="$DB_PASSWORD" \
                --single-transaction \
                --routines \
                --triggers \
                --set-gtid-purged=OFF \
                "$DB_DATABASE" | gzip
        BASH;

        $sshCmd = sprintf(
            'ssh -p %s %s@%s "bash -s -- %s" > %s',
            escapeshellarg((string) $port),
            escapeshellarg((string) $user),
            escapeshellarg((string) $host),
            escapeshellarg((string) $remotePath),
            escapeshellarg($dumpFile),
        );

        $result = Process::timeout(300)->input($remoteScript)->run($sshCmd);

        if (! $result->successful()) {
            $this->error('Failed to create staging dump: '.$result->errorOutput());

            return 1;
        }

        $sizeMb = round(filesize($dumpFile) / 1024 / 1024, 2);
        $this->info("  Dump downloaded: {$sizeMb} MB");

        if ($this->option('dump-only')) {
            $this->info("Dump saved to: {$dumpFile}");

            return 0;
        }

        // Step 2: Drop and recreate local DB
        $this->info('[2/3] Importing to local database...');

        $passArg = $localPass !== '' && $localPass !== null
            ? '--password='.escapeshellarg($localPass)
            : '';

        $importCmd = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s %s %s',
            escapeshellarg($dumpFile),
            escapeshellarg((string) $localHost),
            escapeshellarg((string) $localPort),
            escapeshellarg((string) $localUser),
            $passArg,
            escapeshellarg((string) $localDb),
        );

        // Drop all tables first to avoid FK constraint issues
        $this->dropAllTables($localDb);

        $importResult = Process::timeout(300)->run($importCmd);

        if (! $importResult->successful()) {
            $this->error('Failed to import dump: '.$importResult->errorOutput());

            return 1;
        }

        // Step 3: Run pending migrations
        $this->info('[3/3] Running pending migrations...');
        $this->call('migrate', ['--force' => true, '--no-interaction' => true]);

        // Clean up
        @unlink($dumpFile);
        $this->info('✓ Staging database synced to local successfully.');

        return 0;
    }

    private function dropAllTables(string $database): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $tables = DB::select(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [$database],
            );

            foreach ($tables as $table) {
                DB::statement("DROP TABLE IF EXISTS `{$table->TABLE_NAME}`");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
