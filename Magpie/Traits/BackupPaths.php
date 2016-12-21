<?php

namespace Statamic\Addons\Magpie\Traits;

use Statamic\API\Folder;
use Illuminate\Support\Collection;

trait BackupPaths
{

    /**
     * Gets the local backups.
     *
     * @return Collection
     */
    protected function getLocalBackups()
    {
        return collect(Folder::getFiles(storage_path('backups')))->map(function ($file) {
            return (new \SplFileInfo(root_path($file)));
        });
    }

}