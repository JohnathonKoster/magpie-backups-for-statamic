<?php

namespace Statamic\Addons\Magpie\Commands;

use Statamic\Extend\Command;
use Statamic\API\AssetContainer;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Helper\ProgressBar;
use Statamic\Addons\Magpie\Management\BackupRemover;

class PurgeCommand extends Command
{

    protected $addon_name = 'Magpie';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magpie:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purges old site backup files.';

    /**
     * The BackupRemover instance.
     *
     * @var BackupRemover
     */
    protected $backupRemover;

    /**
     * The ProgressBar instance.
     *
     * @var ProgressBar
     */
    protected $progress;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->backupRemover = app(BackupRemover::class);
        $this->registerOutputEvents();
    }

    private function registerOutputEvents()
    {
        Event::listen('magpie.purgingStarted', function ($backupCount) {
            $this->output->newLine();
            $this->info("Preparing to purge {$backupCount} backup files...");
            $this->progress = $this->output->createProgressBar($backupCount);
        });

        Event::listen('magpie.purgingFileComplete', function ($file) {
            $this->progress->setMessage("Purged {$file}");
            $this->progress->advance();
        });

        Event::listen('magpie.purgingComplete', function () {
            $this->progress->finish();
            $this->output->newLine();
            $this->comment("Purged backup files.");
        });
    }

    private function syncAssets()
    {
        $this->info("Before backups are purged, we will sync the assets for any containers you push backups to. This may take a while.");
        collect($this->getConfig('copy-backups', []))->map(function ($folder, $container) {
            return AssetContainer::find($container);
        })->each(function (\Statamic\Assets\AssetContainer $container) {
            $this->info("\nSyncing {$container->title()}...");
            $container->sync();
            $this->info("\nSyncing {$container->title()}... complete!");
        });
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->syncAssets();

        $this->backupRemover->setBackupCopyLocations($this->getConfig('copy-backups', []));
        $removeBackups = $this->getConfig('remove-backups', []);
        $this->backupRemover->setMaximumBackupAge(array_get($removeBackups, 'after-days', 30));
        $this->backupRemover->purge();

        return 0;
    }
}
