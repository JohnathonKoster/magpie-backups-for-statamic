<?php

namespace Statamic\Addons\Magpie\Commands;

use Statamic\Addons\Magpie\Events\FinishedEvent;
use Statamic\Extend\Command;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Helper\ProgressBar;
use Statamic\Addons\Magpie\Management\BackupCreator;

class BackupCommand extends Command
{

    protected $addon_name = 'Magpie';

    /**
     * The BackupCreator instance.
     *
     * @var BackupCreator
     */
    protected $backupCreator;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magpie:backup {--no-purge : Do not run the purge command afterwards.}
                                          {--no-move : Do not run the move command after backups have been created.}
                                          {--no-create : Do not create the backups, and just run the command instead.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a backup of your Statamic site.';

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
        $this->backupCreator = app(BackupCreator::class);
        $this->registerOutputEvents();
    }

    /**
     * Fires the Magpie finished event. So pretty.
     */
    private function fireFinishedEvent()
    {
        (new FinishedEvent())->fire();
    }

    /**
     * Registers the event listeners so we can show the
     * backup progress to the end user in a nice way.
     */
    private function registerOutputEvents()
    {
        Event::listen('magpie.backupStarted', function ($pathCount) {
            $this->output->newLine();
            $this->info("Preparing to backup {$pathCount} files...");
            $this->progress = $this->output->createProgressBar($pathCount);
        });

        Event::listen('magpie.puttingFile', function ($file) {
            $this->progress->setMessage("Backing up {$file}...");
            $this->progress->advance();
        });

        Event::listen('magpie.backupCreated', function ($destination) {
            $this->progress->finish();
            $this->output->newLine();
            $this->comment("Created {$destination} backup file.");
        });
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        if ($this->option('no-create')) {
            $this->fireFinishedEvent();
            return 0;
        }

        $this->backupCreator->setBackupPaths($this->getConfig('backup-folders', []));
        $this->backupCreator->createBackup();

        if (!$this->option('no-move')) {
            $copyTo = $this->getConfig('copy-backups', []);

            if (count($copyTo) > 0) {
                // For every disk we want to copy backups to we
                // will call a new Artisan command.
                foreach ($copyTo as $container => $folder) {
                    $this->call('magpie:move', [
                        'file' => $this->backupCreator->getWrittenFile(),
                        'container' => $container,
                        'folder' => $folder
                    ]);
                }
            }
        }

        if (!$this->option('no-purge')) {
            $this->call('magpie:purge');
        }

        $this->fireFinishedEvent();
        return 0;
    }
}
