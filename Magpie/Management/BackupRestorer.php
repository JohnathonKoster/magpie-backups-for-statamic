<?php

namespace Statamic\Addons\Magpie\Management;

use Statamic\API\Zip;
use Statamic\API\Path;
use Statamic\API\Folder;
use Statamic\Assets\Asset;
use Illuminate\Support\Str;
use Statamic\API\AssetContainer;
use Statamic\Assets\AssetFolder;
use Illuminate\Support\Collection;
use Statamic\Addons\Magpie\Traits\BackupPaths;

class BackupRestorer
{
    use BackupPaths;

    /**
     * The storage containers that backups might be in.
     *
     * @var array
     */
    protected $storageContainers = [];

    /**
     * The required paths for Magpie restoration to work.
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
            if (!is_dir(storage_path($path))) {
                mkdir(storage_path($path), 0777, true);
            }
        }
    }

    /**
     * Sets the storage containers that backups might be in.
     *
     * @param array $containers
     */
    public function setStorageContainers(array $containers)
    {
        $this->storageContainers = $containers;
    }

    /**
     * Locates the backup file.
     *
     * @param $backupFile
     * @return null|string
     */
    protected function locateBackupFile($backupFile)
    {
        if ($backupFile instanceof \SplFileInfo) {
            if (file_exists($backupFile->getRealPath())) {
                event('magpie.foundBackupFile', $backupFile);
                event('magpie.foundLocalBackupFile', $backupFile);
                return $backupFile->getRealPath();
            }
        }

        if (is_string($backupFile) && file_exists($backupFile)) {
            event('magpie.foundBackupFile', $backupFile);
            event('magpie.foundRemoteBackupFile', $backupFile);
            return $backupFile;
        }

        return null;
    }

    /**
     * Locates the given backup file.
     *
     * @param  $backupFile
     * @return mixed
     */
    protected function findBackupFile($backupFile)
    {
        $backups = $this->getLocalBackups()->filter(function (\SplFileInfo $file) use ($backupFile) {
            return $file->getFilename() == $backupFile;
        });

        // We have a local copy of the desired backup file, lets go with it.
        if (count($backups) > 0) {
            return $backups[0];
        }

        $remotes = [];

        collect($this->storageContainers)->map(function ($folder, $container) {
            return AssetContainer::find($container)->folder($folder);
        })->filter(function (AssetFolder $folder) use ($backupFile, &$remotes) {
            $assets = array_pluck($folder->assets()->toArray(), 'basename');

            if (in_array($backupFile, $assets)) {
                // Copy the file to the local file system.
                $asset = $folder->assets()->filter(function ($file) use ($backupFile) {
                    return $file->basename() == $backupFile;
                });

                if (count($asset) > 0) {
                    $remotes[] = $asset->first();
                }
            }
        });

        if (count($remotes) > 0) {
            /** @var Asset $remote */
            $remote = $remotes[0];
            $localPath = storage_path("backups/{$remote->basename()}");
            event('magpie.downloadRemoteBackup', $remote->basename());
            file_put_contents($localPath, $remote->disk()->get($remote->path()));
            event('magpie.downloadRemoteBackupComplete', $remote->basename());
            return $localPath;
        } else {
            return null;
        }
    }

    /**
     * Restores the given backup.
     *
     * @param $backupName
     */
    public function restore($backupName)
    {
        $nameWithoutZip = mb_substr($backupName, 0, -4);
        $localWithoutZip = Path::makeRelative(storage_path("restores/{$nameWithoutZip}"));
        $length = mb_strlen($localWithoutZip);

        $this->ensurePathExists();

        event('magpie.backupFileSearchStarted', $backupName);

        $localPath = $this->locateBackupFile($this->findBackupFile($backupName));

        if ($localPath !== null) {
            $restorePath = storage_path('restores');
            $restoreDirectory = Str::finish($restorePath, "/{$nameWithoutZip}");

            Zip::extract($localPath, $restoreDirectory);

            $paths = collect(Folder::getFilesRecursively(storage_path("restores/{$nameWithoutZip}")));

            event('magpie.restoreStarted', count($paths));

            $paths->each(function ($path) use ($length) {
                $destination = root_path(mb_substr($path, $length));

                if (!file_exists(dirname($destination))) {
                    mkdir(dirname($destination), 0777, true);
                }

                copy(root_path($path), $destination);
                event('magpie.fileRestored', [$path, $destination]);
            });

            event('magpie.restoreComplete', [
                $backupName, $paths, $restorePath
            ]);

            event('magpie.restoreCleanupStarted', $restoreDirectory);
            Folder::delete($restoreDirectory);
            event('magpie.restoreCleanupComplete', $restoreDirectory);

        } else {
            event('magpie.restoreFailed', $backupName);
        }
    }

}