# Tape
Tape is a backup solution designed to customize your system and database backup to major storage providers and protocols

## Note
This project is still in development and is made for creator's personal use. Use at your own risk.

Currently, only supports S3 compatible storage providers for uploading backups. You can enable persistent backups by setting the `persistent` option to `true` in your config file. So that the backup will not be deleted after uploading to the storage provider. Alternatively, You can use the none provider to disable uploading to storage provider.

## For Developers
Please feel free to contribute, every ideas are welcome. Currently no policy for PRs/issues. If you review the structures of the code, I make it minimal as much as possible and intentionally adds a provider plugin system that are not heavenly customizable because I want to use it as soon as possible, if you are about to create a provider that have complex requirements such as validation, pre/post function, please consider changing that first as you see fit. Hope you have fun poking in the system!.

### Features
 - [ ] Upload support for more storage providers
     - [x] S3 compatible `since v1.0.1`
     - [ ] S/FTP
     - [ ] SCP
     - [ ] Rsync
     - [ ] SSH
     - [ ] Google Drive
 - [ ] Backup types
     - [x] File/Directory `since v1.0.1`
     - [ ] MySQL
     - [ ] PostgreSQL
     - [ ] MongoDB
 - [ ] Metrics Producer, Grafana compatible
     - [ ] Producer Engine
     - [ ] MySQL
     - [ ] PostgreSQL
     - [ ] MongoDB
 

#### Docker compose
```yaml
version: '3.8'
services:
  tape:
    image: lighty262/tape:1.0.1
    container_name: tape
    restart: unless-stopped
    environment:
      # The config file name to look for
      - CONFIG_NAME=config.json
      # The directory to look for the config file
      - CONFIG_DIR=/app
      # All created backups will be stored in this 
      # directory, wether it is temporary or persistent
      - BACKUPS_DIR=/app/backups
    volumes:
      # Sync all your files to /app/directory in the container.
      # It is recommended to use a read-only volume to prevent data loss.
      # You can also sync your whole system to /app/directory, just be sure to
      # prefix the paths in your config file with /app/directory
      - ../:/app/directory:ro
      # Sync your config to /app/config.json in the container
      - ./config/tape.json:/app/config.json
```

#### config.json
```json
{
    "timezone": "Asia/Manila",
    "backups": [
        {
            "provider": "r2-tape",
            "cron": "0 * * * *",
            "type": "file",
            "name": "backup-%provider%-%Y%%m%%d%-%H%%i%%s%",
            "paths": [
                "/app/directory/grafana",
                "/app/directory/prometheus",
                "/app/directory/npm",
                "/app/directory/portainer",
                "/app/directory/fail2ban",
                "/app/directory/dockovpn"
            ],
            "exclude": [
                "*/.git/*",
                "*/vendor/*",
                "*/node_modules/*",
                "*/.pnpm-store/*"
            ],
            "password": false,
            "persistent": false
        }
    ],
    "providers": {
        "none": {
            "class": "App\\Provider\\None"
        },
        "r2-tape": {
            "class": "App\\Provider\\S3",
            "endpoint": "<s3 endpoint or compatible endpoint (e.g. minio, r2)>",
            "version": "latest",
            "bucket": "tape",
            "access_key": "<access key>",
            "api_key": "<api key>",
        }
    }
}
```

### Non-docker
For non docker users, you can clone this repository and install dependencies using composer.
#### Requirements
 - PHP 8.1 - should be installed as user `/usr/bin/php`
     - php-posix
     - php-pcntl
     - php-curl
     - php-zip
     - php-mbstring
     - php-xml
     - Please add more if I missed something (still in development)
 - Composer

#### Run
```bash
cd /path/to/tape
/usr/bin/php daemon
# if permission denied, try with sudo
# Tested on Ubuntu 20.04
```
