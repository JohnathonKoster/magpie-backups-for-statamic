<?php

namespace Statamic\Addons\Magpie\Management;

use Statamic\API\Asset;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BackupMover
{

    /**
     * Gets the original file name for the provided path.
     *
     * @param $path
     * @return mixed
     */
    private function getOriginalFileName($path)
    {
        $parts = explode('/', $path);
        return array_pop($parts);
    }

    /**
     * Uploads the backup file to the asset container and folder.
     *
     * @param $backupFile
     * @param $assetContainer
     * @param $assetFolder
     */
    public function move($backupFile, $assetContainer, $assetFolder)
    {
        event('magpie.moveBackupStarted', $backupFile);
        $backupFile = str_replace('\\', '/', $backupFile);

        $asset = Asset::create()->container($assetContainer)->folder($assetFolder)->get();
        $asset->upload(new UploadedFile($backupFile, $this->getOriginalFileName($backupFile), mime_content_type($backupFile)));
        event('magpie.moveBackupComplete', $backupFile);
    }

}

