<?php

namespace Statamic\Addons\Magpie\Management;

use Carbon\Carbon;
use Statamic\API\Folder;
use Statamic\Assets\AssetFolder;
use Statamic\API\AssetContainer;
use Illuminate\Support\Collection;

class BackupRemover
{

    /**
     * The backup file locations.
     *
     * @var array
     */
    protected $copyToLocations = [];

    /**
     * The maximum backup age, in days.
     *
     * @var int
     */
    protected $backupAge = 30;

    /**
     * Sets the backup copy locations.
     *
     * @param array $locations
     */
    public function setBackupCopyLocations(array $locations)
    {
        $this->copyToLocations = $locations;
    }

    /**
     * Sets the maximum backup age, in days.
     *
     * @param $age
     */
    public function setMaximumBackupAge($age)
    {
        $this->backupAge = $age;
    }

    /**
     * Get the backup files.
     *
     * @return Collection
     */
    protected function getBackups()
    {
        return collect(Folder::getFiles(storage_path('backups')))->map(function ($file) {
            return (new \SplFileInfo(root_path($file)));
        });
    }

    /**
     * Removes the local path from a file name.
     *
     * @param $path
     * @return string
     */
    protected function stripLocalPath($path)
    {
        $path = realpath($path);
        $path = str_replace('\\', '/', $path);
        $storagePath = str_replace('\\', '/', storage_path());
        return mb_substr($path, mb_strlen($storagePath) + 1);
    }

    /**
     * Gets the backups that are older than the maximum backup age.
     *
     * @return Collection
     */
    protected function getOldBackups()
    {
        return $this->getBackups()->filter(function ($file) {
            $date = Carbon::createFromTimestamp($file->getMTime());
            return (Carbon::now()->diffInDays($date) >= $this->backupAge);
        });
    }

    /**
     * Get a list of backup files that also exist within the remote containers.
     *
     * @return Collection
     */
    protected function getMovedBackups()
    {
        $backups = $this->getBackups();

        $backupsToRemove = collect();

        collect($this->copyToLocations)->map(function ($folder, $container) {
            return AssetContainer::find($container)->folder($folder);
        })->each(function (AssetFolder $folder) use ($backups, &$backupsToRemove) {
            $paths = array_pluck($folder->assets()->toArray(), 'path');

            $backupsToRemove = $backupsToRemove->merge($backups->filter(function($item) use ($paths, $backups) {
                /** @var \SplFileInfo $item */
                $path = $this->stripLocalPath($item->getPathname());
                return in_array($path, $paths);
            }));

        });

        return $backupsToRemove;
    }

    /**
     * Purges the backup files that can be removed.
     */
    public function purge()
    {
        // Remove the old backups.
        $backups = $this->getOldBackups()->merge($this->getMovedBackups());

        event('magpie.purgingStarted', count($backups));

        $backups->each(function ($file) {
            event('magpie.purgingFileStarted', $file->getRealPath());
            unlink($file->getRealPath());
            event('magpie.purgingFileComplete', $file->getRealPath());
        });

        event('magpie.purgingComplete');
    }

}