# Backup Rotator

## Overview

This is a CLI-based backup utility designed to help keep a regular rotation of backups inn one or more storage systems.
It's meant to fit quietly into a backup pipeline, rather than to be a full-fledged, stand-alone utility.  For example,
if I wanted to back up an encrypted copy of my system's `/etc` folder regularly, I'd add the following line to my
crontab:

```cron
0 0 * * * tar -czf - etc | gpg --encrypt | /opt/BackupRotator/bin/backup-rotator --scope=etc
```

While its primary goal is to be used as a binary tool, all classes and methods are exposed via PSR-4 and can easily
be incorporated into other projects as well.

### Key concepts

#### Configs
The rotator currently relies on configuration in a special .ini file (see `config.ini.template` for format
details).  The `backup-rotate` binary will first look for this file in `/.backup-rotator.ini`, then in
`/etc/backup-rotator.ini`.

#### Storage
A type of data storage system (currently FileStorage and S3Storage) that can be used to hold your backup data.
Multiple storage systems can be assigned to a single backup configuration for redundancy.

#### Rotator
How the backups should be rotated.  Currently there is only one strategy that keeps a certain number of days, months,
and years of data (DMYRotator).

#### Scopes
If you're managing multiple backups with this tool, each backup needs its own configured "scope" inside your
config file.  That includes a generic `[$scope]` section and at least one `[$scope.storage.$name]` section.  Scope
can be anything but "logging" (ex: "photos", "config", "db", "configurations"), and $name can be anything, but must be
unique to the scope (ex: "local", "s3", "nas")

Note that if you're only backing up a single file, your scope name can be `default` and you don't have to pass a
`--scope` option to the executable.

## Examples

### Backing up /etc to Amazon's S3

**/etc/backup-rotator.ini**

```ini
[logging]
path = /var/log/backup-rotator.ini

[etc]
maxkb = 204800
days = 7
months = 3
years = 5

[etc.storage.primary]
engine = S3Storage
bucket = server-etc
filename = etc.tar.gz
region = us-east-1
aws_key = 
aws_secret = 
```

**/etc/cron.d/backup-rotator**

```
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 0 * * *   root    tar -czf - etc | gpg --encrypt | /opt/BackupRotator/bin/backup-rotator --scope=etc
```

### Monthly photo and daily document snapshots to a mounted NAS

**~/.backup-rotator.ini**
```ini
[logging]
path = ~/backup-rotator.log

[photos]
maxkb = 10485760
days = 0
months = 1
years = 5

[photos.storage.default]
engine = FileStorage
path = /media/home-nas/photos

[documents]
maxkb = 204800
days = 2
months = 6
years = 10

[documents.storage.default]
engine = FileStorage
path = /media/home-nas/documents
```

**crontab -e**
```
0 0 1 * * joesmith tar -czf - /home/joesmith/Pictures | /home/joesmith/BackupRotator/bin/backup-rotator --scope=photos
0 0 * * * joesmith tar -czf - /home/joesmith/Documents | /home/joesmith/BackupRotator/bin/backup-rotator --scope=documents
```

## Installation

Here's how to install on Ubuntu 18.04 and presumably later:

```sh
sudo apt install php-cli composer php-xml
git clone git@github.com:lexicalbits/backup-rotator.git
cd backup-rotator
composer install --no-dev

# DO ONE OF THE FOLLOWING:
# Want to install global rules?
sudo cp config.ini.template /etc/backup-rotator.ini
# Or would you prefer to run on a per-user basis?
cp config.ini.template ~/.backup-rotator.ini

# Run this if you want to make the script globally available
ln -s `realpath bin/backup-rotator` /usr/local/bin

# Set up your ini file with the backup scopes you want to use
```

## Why you should use this

There are a lot of backup tools out there, and others might be a better fit for how you want to work.  Here's some
reasons you might want to use Backup Rotator:

1. You're a PHP developer and want to be able to understand what you're running, add some additional functionality,
   or roll Backup Rotator's functionality into your own application.

1. You're on a system where PHP is already installed and you have limited access to other runtimes

1. You can't actually pack up the data you're backing up locally before shipping it and have to rely on piping.

## Contributing

Here's some rough contribution guidelines:

1. All PRs must have a description and reason for existing.  If this fixes a problem, reference the issue and/or
   provide a way to recreate the problem your PR fixes.  Your PR name should make sense given the contents of the PR.

1. Make sure your branch is up-to-date with master before submitting the PR, and keep it up to date during the review
   process.

1. Make sure your tests pass and that any new classes & methods are included in the test suite.  All public methods
   must have tests, and line coverage should be as close to 100% as you can get it.

Looking for ways to contribute?  Here are some ideas for upgrades we could use:

1. **More storage systems**: Missing your favorite storage provider?  Write your own extension to the Storage/Storage
   base class and submit a PR!  Some ideas:
    * Azure
    * Dropbox
    * Google Cloud

1. **More backup strategies**: Feeling limited by our single D/M/Y storage paradigm?  Extend Rotator/Rotator with your
   own strategy and submit a PR!

1. **Better logging**: Right now outputting logs feels faily limited - if it's getting in your way, replace
   Logging with a better implementation!  One caveat: it'll need to have some kind of backwards compatibility
   with the current configuration system to be merged into the main branch.

1. **Sanity check state**': Having a generic sanity check for the state of a storage system would be a good idea.
   Right now it's technically possible to call onEnd, then onChunk, with unintended side effects for each engine.
   A good PR would add Storage::STATE_{READY|OPEN|CLOSED} to each instance and throw if onChunk or onEnd is called
   during STATE_CLOSED.
