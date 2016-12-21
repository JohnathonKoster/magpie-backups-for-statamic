<?php

namespace Statamic\Addons\Magpie\Management;

use Statamic\API\Zip;
use Statamic\API\File;
use Statamic\API\Folder;

class BackupCreator
{

    /**
     * The file that was written to.
     *
     * @var string
     */
    protected $fileWrittenTo = '';

    /**
     * The paths to backup.
     *
     * @var array
     */
    protected $backupPaths = [];

    /**
     * The required paths for Magpie to work.
     *
     * @var array
     */
    protected $requiredPaths = [
        'backups',
        'restores'
    ];

    /**
     * Creates the required paths if they don't exist.
     */
    protected function ensurePathExists()
    {
        foreach ($this->requiredPaths as $path) {
            if (! is_dir(storage_path($path))) {
                mkdir(storage_path($path), 0777, true);
            }
        }
    }

    /**
     * Sets the paths that should be backed up.
     *
     * @param array $paths
     */
    public function setBackupPaths(array $paths)
    {
        $this->backupPaths = $paths;
    }

    /**
     * Gets the files that should be backed up.
     *
     * This method handles the collection of files
     * across multiple directories. You're welcome.
     *
     * @return array
     */
    protected function getBackupFiles()
    {
        if (count($this->backupPaths) == 0) {
            return Folder::getFilesRecursively(site_path());
        }

        $paths = [];

        foreach ($this->backupPaths as $path) {
            $paths = array_merge($paths, Folder::getFilesRecursively(site_path($path)));
        }

        return $paths;
    }

    /**
     * Creates the backup file.
     *
     * @return bool
     * @throws BackupFailedException
     */
    public function createBackup()
    {
        $this->ensurePathExists();

        $this->fileWrittenTo = $destination = storage_path('backups/statamic-'.STATAMIC_VERSION.'-'.time().'.zip');

        try {
            $zip = Zip::make($destination);

            $paths = $this->getBackupFiles();

            event('magpie.backupStarted', count($paths));

            foreach ($paths as $path) {
                event('magpie.puttingFile', $path);
                $zip->put($path, File::get($path));
            }

            Zip::write($zip);

            event('magpie.backupCreated', $destination);

        } catch (\Exception $e) {
            throw new BackupFailedException('Could not create backup file: '.$e->getMessage());
        }

        return true;
    }

    /**
     * Gets the file that the backup was written to.
     *
     * @return string
     */
    public function getWrittenFile()
    {
        return $this->fileWrittenTo;
    }

}