# Magpie - Backup Manager for Statamic

Magpie is a delightful Statamic addon that makes it easier for you to stash away backups of your site. Like the clever fox you are, you know the importance of keeping backups of your site on hand! Public Service Announcement: a magpie is not a fox.

## Installation

To install Magpie, simply copy the `Magpie` directory to your site's `addons` directory and you are done!

## Configuration

It is highly recommended that you create a `magpie.yaml` file in your site's `site/settings/addons` directory. The default settings file looks like this:

```yaml
backup-folders: []
copy-backups: []
remove-backups:
  "after-move": No
  "after-days": 30
```

### Backup Folders

The `backup-folders` configuration entry determines which folders within your site's `site` (what a mouth full!) directory are backed up. If no directories are specified *everything* in the `site` directory is backed up. For example, if we only wanted to back up our `content`, `settings` and `storage` directories, we would specify these in the settings like so:

```yaml
backup-folders:
  - content
  - settings
  - storage
```

Now, when we run the `php please magpie:backup` command only these three directories specified will be backed up. Win!

### Copying Backup Files

It is often important to move your backup files to some other place (like a different server). To make this as easy as possible to accomplish, Magpie taps into the power and ease of Statamic's asset system. In fact, to tell Magpie to copy your backups simply list the name of the asset container and the folder you want them moved to. Really! That's it! Magpie is so obedient. It's a shame, really.

For example, if we already have an asset container named `main` that is connected to Amazon S3, we can tell Magpie to copy backups to a `backups` folder there:

```yaml
copy-backups:
  "main": "backups"
```

> Just make sure the asset container and folder already exists! I also recommend you read Statamic's documentation on Assets: [https://docs.statamic.com/assets](https://docs.statamic.com/assets).

You can list more than one asset container-as long as they are already configured within Statamic!

### Purging Backup Files

The following configuration values help Magpie figure out which backup files it should remove when the `php please magpie:purge` command is ran:

```yaml
remove-backups:
  "after-move": No
  "after-days": 30
```

The `after-move` configuration value determines if Magpie should delete the local backup file after it has been moved to some other location (via the asset containers). If the value is `No`, Magpie will keep the local copy. If the value is `Yes`, Magpie will remove the local copy once it detects the backup file has been stored in an asset container.

The `after-days` configuration value determines how many days old a backup file has to be before it gets automatically deleted by Magpie.

## Creating a Backup

To create a backup file of your site, simply issue the following command:

```bash
php please magpie:backup
```

The `backup` command will move the backup files to any configured asset containers automatically, no need to worry about it! Additionally, this command will also remove old backup files for you.

## Removing Old Backup Files

By default Magpie will delete backup files that are 30 days or older, or files that have already been moved to another server (see the previous section titled "Copying Backup Files").

To manually remove old backup files, simply issue the following command:

```bash
php please magpie:purge
```

The `magpie:backup` command automatically calls this for you.

## But What About `git`?

Ah yes, you. You clever person. The thing is, it gets to be too much to try and manage all of the various things you can do with `git` using YAML configuration files. For you, my dear friend, I have something very special. Magpie emits a very special event named `magpie.finished`. This event passes along an instance of `Symfony\Component\Process\Process` as it's payload. The working directory of this process has already been set to your site's `site` directory.

To use it, create an event listener (again, consult Statamic's official documentation on Event Listeners: [https://docs.statamic.com/addons/classes/event-listeners](https://docs.statamic.com/addons/classes/event-listeners)) that listen's for the `magpie.finished` event. Here is an example event listener:

```php
<?php

namespace Statamic\Addons\Example;

use Statamic\Extend\Listener;
use Symfony\Component\Process\Process;

class ExampleListener extends Listener
{

    public $events = [
        'magpie.finished' => 'handleBackup'
    ];

    public function handleBackup(Process $process)
    {
        // Create an array of the commands that we
        // would normally issue in the console.
        $commands[] = 'git add .';
        $commands[] = 'git commit -a -m "Automated backup"';
        $commands[] = 'git push';

        // The implode function will add the ` && `
        // between each command so we don't have to.
        $process->setCommandLine(implode(' && ', $commands))->run();
    }

}
```

> There is a lot you can do with the `Process` class. Check out the documentation at [https://symfony.com/doc/current/components/process.html](https://symfony.com/doc/current/components/process.html).

"But wait! I just want to hook into the event without generating a backup file!" - Ah yes! I hear you. That's why there exists a `--no-create` option that will cause the backup command to simply fire the event without generating the backup file (this also means that Magpie won't move or purge backup files). To use this, you can specify the following command in your automated task:

```bash
php please magpie:backup --no-create
```

How do you automate Magpie? Funny you ask...

## Automating Magpie

To automate Magpie, please read the official Statamic documentation on Tasks: [https://docs.statamic.com/addons/classes/tasks](https://docs.statamic.com/addons/classes/tasks).

Once you have read and understood the documentation, and completed the steps it outlined, please create a new task that runs the `magpie:backup` command at whatever interval you would like (I recommend weekly, unless you perform many site updates).

## License

Magpie - Backup Manager for Statamic is open-sourced software licensed under the MIT license.
