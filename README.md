# ArchiveTimesheetsCommandBundle

A Kimai 2 plugin, which allows you to archive timesheets older than a specified
timeframe, using a command.

## Installation

First clone it to your Kimai installation `plugins` directory:
```
cd /kimai/var/plugins/
git clone https://github.com/digipolisgent/kimai_plugin_archive-timesheets-command.git ArchiveTimesheetsCommandBundle
```

And then rebuild the cache:
```
cd /kimai/
bin/console cache:clear
bin/console cache:warmup
```

You could also [download it as zip](https://github.com/digipolisgent/kimai_plugin_archive-timesheets-command/archive/master.zip) and upload the directory via FTP:

```
/kimai/var/plugins/
├── ArchiveTimesheetsCommandBundle
│   ├── ArchiveTimesheetsCommandBundle.php
|   └ ... more files and directories follow here ...
```
