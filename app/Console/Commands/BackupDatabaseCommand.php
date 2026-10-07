<?php

namespace App\Console\Commands;

use App\Services\ProviderReadinessService;
use App\Support\AdminAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'sms:backup-database';

    protected $description = 'Write a dated database backup and prune files older than the retention window.';

    public function handle(ProviderReadinessService $readiness): int
    {
        $connection = (string) config('database.default');
        $dir = $readiness->backupDirectory();
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Y-m-d-His');
        $path = $dir.'/soscom-'.$connection.'-'.$stamp.'.sql';

        $written = match ($connection) {
            'sqlite' => $this->backupSqlite($path),
            'mysql', 'mariadb' => $this->backupMysql($path),
            default => $this->failUnsupported($connection),
        };

        if ($written === false) {
            AdminAlerts::technical(
                'backup-failed',
                'Database backup failed',
                'The daily '.$connection.' backup could not be written to '.$dir.'. Check the server logs and disk space.',
            );

            return self::FAILURE;
        }

        if ($written === null) {
            return self::SUCCESS;
        }

        $this->prune($dir);
        $this->info("Wrote database backup to {$path}.");

        return self::SUCCESS;
    }

    private function backupSqlite(string $path): ?bool
    {
        $database = (string) config('database.connections.sqlite.database');

        if ($database === ':memory:' || $database === '') {
            $this->warn('Skipped sqlite in-memory backup.');

            return null;
        }

        if (! is_file($database)) {
            $this->error('SQLite database file was not found.');

            return false;
        }

        return copy($database, $path);
    }

    private function backupMysql(string $path): bool
    {
        $config = config('database.connections.'.config('database.default'));
        $command = [
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--single-transaction',
            '--no-tablespaces',
            '--databases',
            (string) ($config['database'] ?? ''),
        ];

        $process = new Process($command, timeout: 120);
        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $env = getenv();
            $process->setEnv(['MYSQL_PWD' => $password] + (is_array($env) ? $env : []));
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump failed: '.$process->getErrorOutput());

            return false;
        }

        return File::put($path, $process->getOutput()) !== false;
    }

    private function failUnsupported(string $connection): bool
    {
        $this->error("No backup driver is configured for {$connection}.");

        return false;
    }

    private function prune(string $dir): void
    {
        $keepDays = (int) config('sms.production.backup_keep_days', 14);
        $cutoff = now()->subDays($keepDays)->getTimestamp();

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
