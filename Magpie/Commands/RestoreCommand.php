<?php

namespace Statamic\Addons\Magpie\Commands;

use Statamic\Extend\Command;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Finder\SplFileInfo;
use Statamic\Addons\Magpie\Traits\BackupPaths;
use Symfony\Component\Console\Helper\ProgressBar;
use Statamic\Addons\Magpie\Management\BackupRestorer;

class RestoreCommand extends Command
{
    use BackupPaths;

    protected $addon_name = 'Magpie';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magpie:restore {backup : The backup file to restore.}
                                           {--keep-up : Do not enable maintenance mode.}
                                           {--no-backup : Do not create a backup of the current state of the site before restoring.}
                                           {--no-fix : Do not attempt to restore the state of the site if the primary restore fails.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restores a backup file.';

    /**
     * The BackupRestorer instance.
     *
     * @var BackupRestorer
     */
    protected $restorer;

    /**
     * The ProgressBar instance.
     *
     * @var ProgressBar
     */
    protected $progress;

    public function __construct()
    {
        parent::__construct();
        $this->restorer = app(BackupRestorer::class);
        $this->restorer->setStorageContainers($this->getConfig('copy-backups', []));
        $this->registerOutputEvents();
    }

    private function registerOutputEvents()
    {
        Event::listen('magpie.backupFileSearchStarted', function ($backupName) {
            $this->info("\nLooking for backup file {$backupName}...");
        });

        Event::listen('magpie.foundLocalBackupFile', function ($backupFile) {
            $this->info("\nDiscovered {$backupFile} on local server...");
        });

        Event::listen('magpie.foundRemoteBackupFile', function ($backupFile) {
            $this->info("\nDiscovered {$backupFile} on remote server...");
        });

        Event::listen('magpie.downloadRemoteBackup', function ($fileName) {
            $this->info("\nStarting to download remote {$fileName} backup...");
        });

        Event::listen('magpie.downloadRemoteBackupComplete', function ($fileName) {
            $this->info("\nStarting to download remote {$fileName} backup... complete!");
        });

        Event::listen('magpie.restoreFailed', function ($backupName) {
            $this->error("\nRestoration of {$backupName} failed: Could not locate the backup file.");
        });

        Event::listen('magpie.restoreStarted', function ($restoreFiles) {
            if (!$this->option('keep-up')) {
                $this->output->newLine();
                // Bring the site into maintenance mode.
                $this->call('down');
            }

            $this->info("\nPreparing to restore {$restoreFiles} files...");
            $this->progress = $this->output->createProgressBar($restoreFiles);
        });

        Event::listen('magpie.fileRestored', function ($path, $destination) {
            $this->progress->setMessage("Restoring {$path} to {$destination}...");
            $this->progress->advance();
        });

        Event::listen('magpie.restoreComplete', function ($backupName) {
            if (!$this->option('keep-up')) {
                $this->output->newLine();
                // Bring the site out of maintenance mode.
                $this->call('up');
            }

            $this->progress->finish();
            $this->comment("\nRestored backup {$backupName}!");
        });

        Event::listen('magpie.restoreCleanupStarted', function ($restoreDirectory) {
            $this->info("\nCleaning up backup files from {$restoreDirectory}...");
        });

        Event::listen('magpie.restoreCleanupComplete', function ($restoreDirectory) {
            $this->info("\nCleaning up backup files from {$restoreDirectory}... complete!");
        });
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('no-backup')) {
            $this->call('magpie:backup', [
                '--no-purge' => true,
            ]);
        }

        try {
            $this->restorer->restore($this->argument('backup'));
        } catch (\Exception $e) {
            $this->error("An unexpected error occurred when restoring the backup: {$e->getMessage()}");


            if (! $this->option('no-fix')) {

                $this->info('Attempting to locate a backup to reset to...');

                $recentPath = $this->getLocalBackups()->sortBy(function ($file) {
                    /** @var \SplFileInfo $file */
                    return $file->getCTime();
                })->reverse()->first();

                // Do not attempt to try and automatically fix
                // a failed restore if the backup is the same
                // as the backup file that has just failed.
                if ($recentPath == $this->argument('backup')) {
                    return 0;
                }

                if ($recentPath !== null) {
                    $this->call('magpie:restore', [
                        'backup' => $recentPath->getFilename(),
                        '--no-fix' => true,
                        '--no-backup' => true
                    ]);
                }
            }

        }

        return 0;
    }

}