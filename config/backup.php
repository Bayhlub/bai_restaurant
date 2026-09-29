<?php

return [
    /*
     * Where snapshots are written. Keep this on a different drive from the
     * application (a USB stick, a second disk or a network share): a backup
     * sitting next to the database is lost with it when the disk fails.
     */
    // `?:` rather than a default, so an empty BACKUP_PATH= in .env still falls back.
    'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),

    /*
     * Delete snapshots older than this many days.
     */
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    /*
     * Never prune below this many of the newest snapshots, whatever their age.
     * Without it, a machine left switched off for a month would have its whole
     * backup history deleted on the next run.
     */
    'always_keep' => (int) env('BACKUP_ALWAYS_KEEP', 7),
];
