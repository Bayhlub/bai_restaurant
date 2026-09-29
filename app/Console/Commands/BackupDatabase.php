<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run
                            {--force : Take a snapshot even if today already has one}';

    protected $description = 'Write a verified snapshot of the database to the backup folder';

    /** Snapshots are named so they sort chronologically and are easy to spot. */
    private const PREFIX = 'backup-';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            $this->error("Only the sqlite connection is supported; this app is using [{$connection}].");

            return self::FAILURE;
        }

        $source = config('database.connections.sqlite.database');

        if (! is_file($source)) {
            $this->error("Database file not found at [{$source}].");

            return self::FAILURE;
        }

        $directory = config('backup.path');
        File::ensureDirectoryExists($directory);

        if (! $this->option('force') && ($existing = $this->todaysSnapshot($directory)) !== null) {
            $this->line('Already backed up today: '.basename($existing).' (use --force to take another).');

            return self::SUCCESS;
        }

        $destination = $directory.DIRECTORY_SEPARATOR.self::PREFIX.now()->format('Y-m-d_His').'.sqlite';

        try {
            // VACUUM INTO copies a consistent snapshot even while orders are being
            // written, which a plain file copy cannot promise under WAL.
            DB::statement('VACUUM INTO '.$this->quote($destination));
        } catch (Throwable $e) {
            File::delete($destination);
            $this->fail('Could not write the snapshot: '.$e->getMessage());
        }

        if (($problem = $this->verify($destination)) !== null) {
            File::delete($destination);
            $this->fail("The snapshot was written but failed verification ({$problem}); older backups were kept.");
        }

        $size = number_format(File::size($destination) / 1024, 0);
        $this->info("Backed up to {$destination} ({$size} KB).");
        Log::info('Database backup written', ['file' => $destination]);

        $this->prune($directory);
        $this->warnIfNotOffsite($directory, $source);

        return self::SUCCESS;
    }

    /** The newest snapshot taken today, if there is one. */
    private function todaysSnapshot(string $directory): ?string
    {
        $today = self::PREFIX.now()->format('Y-m-d');

        return collect($this->snapshots($directory))
            ->first(fn (string $path) => str_starts_with(basename($path), $today));
    }

    /**
     * Snapshot paths, newest first.
     *
     * @return array<int, string>
     */
    private function snapshots(string $directory): array
    {
        return collect(File::glob($directory.DIRECTORY_SEPARATOR.self::PREFIX.'*.sqlite'))
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Open the snapshot and confirm it is a readable, intact copy of this app's
     * database. A corrupt backup that looks fine is worse than no backup.
     */
    private function verify(string $path): ?string
    {
        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                return 'integrity check failed';
            }

            $orders = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'orders'")->fetchColumn();

            if ((int) $orders !== 1) {
                return 'the orders table is missing';
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** Delete snapshots past the retention window, always leaving the newest few. */
    private function prune(string $directory): void
    {
        $snapshots = $this->snapshots($directory);
        $cutoff = CarbonImmutable::now()->subDays(config('backup.keep_days'));

        $stale = collect($snapshots)
            ->slice(config('backup.always_keep'))
            ->filter(fn (string $path) => CarbonImmutable::createFromTimestamp(File::lastModified($path))->lt($cutoff));

        foreach ($stale as $path) {
            File::delete($path);
        }

        if ($stale->isNotEmpty()) {
            $this->line('Removed '.$stale->count().' snapshot(s) older than '.config('backup.keep_days').' days.');
        }
    }

    /** A backup on the same disk disappears with it, so say so plainly. */
    private function warnIfNotOffsite(string $directory, string $source): void
    {
        if (str_starts_with(realpath($directory) ?: $directory, dirname(realpath($source) ?: $source, 2))) {
            $this->warn('This folder is inside the project, so a disk failure would take the backups too.');
            $this->warn('Point BACKUP_PATH at another drive or a network share.');
        }
    }

    private function quote(string $path): string
    {
        return "'".str_replace("'", "''", $path)."'";
    }
}
