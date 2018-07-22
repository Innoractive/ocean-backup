# Ocean Backup

Script for automating backup of [DigitalOcean](https://www.digitalocean.com/) "DO" [Droplet](https://www.digitalocean.com/products/droplets/) using [snapshot](https://do.co/2mx3U58).

# Instruction

1. Copy *ocean_backup.ini* to */etc/*.
1. Make sure you configure the API **token** and list of **Droplet IDs** that need to be backup.
1. Setup a cron job to execute *index.php*.

Example cron configuration

```
0 0 * * * php /path/to/ocean-backup/index.php >> /tmp/ocean-backup.log 2>&1

```

# Notes

* Follow this [tutorial](https://do.co/2mxq2MS) to create API token.
* You can either use [DO command line tool](http://bit.ly/2mBGY4Y) or [this alternate method](https://do.co/2myHBMn) to determine Droplet ID.

# TODO

* Add email notification.