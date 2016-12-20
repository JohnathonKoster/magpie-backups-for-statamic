<?php

namespace Statamic\Addons\Magpie\Commands;

use Statamic\Extend\Command;
use Illuminate\Support\Facades\Event;
use Statamic\Addons\Magpie\Management\BackupMover;

class MoveCommand extends Command
{

    protected $addon_name = 'Magpie';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magpie:move {file : The file to move.}{container : The asset container to copy the backup to.}
                                        {folder : The folder within the asset container to copy the backup to.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Moves the specified backup file to the asset container.';

    /**
     * The BackupMover instance.
     *
     * @var BackupMover
     */
    protected $backupMover;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->backupMover = app(BackupMover::class);
        $this->registerOutputEvents();
    }

    /**
     * Registers the event listeners to update the console.
     */
    private function registerOutputEvents()
    {
        Event::listen('magpie.moveBackupStarted', function($path) {
            $this->output->newLine();
            $this->info("Starting to move {$path} backup file...");
        });

        Event::listen('magpie.moveBackupComplete', function($path) {
            $this->output->newLine();
            $this->comment("Finished moving {$path} backup file.");
        });
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->backupMover->move($this->argument('file'), $this->argument('container'), $this->argument('folder'));

        return 0;
    }

}
