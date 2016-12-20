<?php

namespace Statamic\Addons\Magpie\Events;

use Symfony\Component\Process\Process;

class FinishedEvent
{

    /**
     * Fires the Magpie finished event.
     */
    public function fire()
    {
        $process = new Process(null);
        $process->setWorkingDirectory(site_path());
        $process->setTimeout(null);
        event('magpie.finished', $process);
    }

}