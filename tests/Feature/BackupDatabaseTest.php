<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    private string $backupDir;

    private string $databaseFile;

    /**
     * The suite normally runs on an in-memory database, which cannot be
     * snapshotted, so this test works against a real file it migrates itself.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::random(8);
        $this->backupDir = storage_path('framework/testing/backups-'.$suffix);
        $this->databaseFile = storage_path('framework/testing/db-'.$suffix.'.sqlite');

        File::ensureDirectoryExists(dirname($this->databaseFile));
        File::put($this->databaseFile, '');

        config([
            'backup.path' => $this->backupDir,
            'backup.keep_days' => 30,
            'backup.always_keep' => 7,
            'database.connections.sqlite.database' => $this->databaseFile,
        ]);

        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->run();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        File::deleteDirectory($this->backupDir);
        File::delete($this->databaseFile);

        parent::tearDown();
    }

    /** @return array<int, string> */
    private function snapshots(): array
    {
        return File::glob($this->backupDir.DIRECTORY_SEPARATOR.'backup-*.sqlite') ?: [];
    }

    private function fakeSnapshot(string $name, int $daysOld): string
    {
        File::ensureDirectoryExists($this->backupDir);
        $path = $this->backupDir.DIRECTORY_SEPARATOR.$name;
        File::put($path, 'not a real snapshot');
        touch($path, now()->subDays($daysOld)->getTimestamp());

        return $path;
    }

    public function test_it_writes_a_snapshot_that_can_be_read_back(): void
    {
        $this->artisan('backup:run')->assertSuccessful();

        $snapshots = $this->snapshots();
        $this->assertCount(1, $snapshots);

        // The snapshot must be a usable database, not just a file that exists.
        $pdo = new PDO('sqlite:'.$snapshots[0]);
        $this->assertSame('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        $this->assertGreaterThan(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'invoices'")->fetchColumn());
    }

    public function test_it_takes_one_snapshot_a_day_unless_forced(): void
    {
        $this->artisan('backup:run')->assertSuccessful();

        $this->artisan('backup:run')
            ->expectsOutputToContain('Already backed up today')
            ->assertSuccessful();

        $this->assertCount(1, $this->snapshots());

        sleep(1); // The filename carries the time, so a forced run needs a new second.
        $this->artisan('backup:run', ['--force' => true])->assertSuccessful();

        $this->assertCount(2, $this->snapshots());
    }

    public function test_it_deletes_snapshots_past_the_retention_window(): void
    {
        config(['backup.keep_days' => 30, 'backup.always_keep' => 2]);

        $old = $this->fakeSnapshot('backup-2026-01-01_000000.sqlite', daysOld: 90);
        $recent = $this->fakeSnapshot('backup-2026-09-01_000000.sqlite', daysOld: 5);

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }

    public function test_it_never_prunes_below_the_floor_even_when_everything_is_old(): void
    {
        config(['backup.keep_days' => 1, 'backup.always_keep' => 3]);

        foreach (range(1, 5) as $i) {
            $this->fakeSnapshot("backup-2026-01-0{$i}_000000.sqlite", daysOld: 200);
        }

        $this->artisan('backup:run')->assertSuccessful();

        // The new snapshot plus the two newest old ones: the history is not wiped.
        $this->assertCount(3, $this->snapshots());
    }

    public function test_a_failed_backup_leaves_existing_snapshots_alone(): void
    {
        $existing = $this->fakeSnapshot('backup-2026-01-01_000000.sqlite', daysOld: 200);

        // Windows keeps the open database file locked, so point at a missing one
        // rather than deleting the file under the connection.
        config(['database.connections.sqlite.database' => storage_path('framework/testing/gone.sqlite')]);

        $this->artisan('backup:run')->assertFailed();

        $this->assertFileExists($existing);
        $this->assertCount(1, $this->snapshots());
    }

    public function test_it_refuses_to_run_against_an_unsupported_driver(): void
    {
        config(['database.default' => 'mysql']);

        $this->artisan('backup:run')
            ->expectsOutputToContain('Only the sqlite connection is supported')
            ->assertFailed();

        $this->assertSame([], $this->snapshots());
    }
}
