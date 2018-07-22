<?php

namespace OceanBackup;

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\DigitalOceanV2;
use DigitalOceanV2\Adapter\GuzzleHttpAdapter;
use DigitalOceanV2\Entity\Image;
use DigitalOceanV2\Entity\Snapshot;
use Exception;
use OceanBackup\Configtor;
use OceanBackup\Logger;

class OceanBackup
{
    /** @var \DigitalOceanV2\DigitalOceanV2 $digitalOcean DO API, initialized in constructor. */
    protected $digitalOcean;

    /** @var \DigitalOceanV2|Droplet $droplet Droplet API, initialized in constructor. */
    protected $droplet;

    public function __construct()
    {
        $adapter = new GuzzleHttpAdapter(Configtor::instance()->get('token'));
        $this->digitalOcean = new DigitalOceanV2($adapter);

    }

    public function runBackup()
    {
        $this->droplet = $this->digitalOcean->droplet();

        $dropletIds = Configtor::instance()->get('droplet_ids', []);
        foreach ($dropletIds as $dropletId) {
            try {
                $snapshots = $this->droplet->getSnapshots($dropletId);
                $this->backupDroplet($dropletId);
                $this->deleteOldBackups($dropletId);
            } catch (Exception $exception) {
                Logger::warn("Unable to process Droplet ID({$dropletId}): " . $exception->getMessage());
                continue;
            }
        }

        Logger::info("Backup process completed.");
    }

    /**
     * Create 1 new snapshot.
     * @param int $dropletId
     */
    public function backupDroplet($dropletId)
    {
        $prefix = $this->snapshotPrefix();
        $timestamp = date('ymdHis');

        try {
            // Snapshot namne: foobar-autobackup-1812252359
            $this->droplet->snapshot($dropletId, "{$prefix}{$timestamp}");
            Logger::info("Snapshot '{$prefix}{$timestamp}' created.");
        } catch (Exception $exception) {
            Logger::warn("Failed to create snapshot for DO({$dropletId}): " . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Delete old backups for selected droplet.
     * If num of backups < MIN_BACKUPS, no deletion
     * elseif backup date older then MAX_BACKUP_DAYS, delete.
     * @param int $dropletId
     */
    public function deleteOldBackups($dropletId)
    {
        $config = Configtor::instance();
        $prefix = $this->snapshotPrefix();

        $snapshots = $this->droplet->getSnapshots($dropletId);
        $snapshots = array_filter($snapshots, [$this, "filterSnapshotBackup"]);

        // No deletion if number of backup is lesser than `min_backups`
        $deletableCount = count($snapshots) - $config->get('min_backups', 3);

        // Image API
        $image = $this->digitalOcean->image();

        // Days to seconds conversion, delete snapshots after retention days
        $expiry = time() - $config->get('retention_days', 7) * 86400;
        foreach ($snapshots as $snapshot) {
            if ($deletableCount <= 0)
                return;

            if (strtotime($snapshot->createdAt) > $expiry)
                continue;

            Logger::info("Deleting Droplet({$snapshot->name})");
            $image->delete($snapshot->id);
            $deletableCount--;
        }
    }

    /**
     * Determine if a snapshot's name matches backup naming convention.
     * @param Image $snapshot
     * @return false|int
     */
    protected function filterSnapshotBackup(Image $snapshot)
    {
        $prefix = preg_quote($this->snapshotPrefix());
        return preg_match("/^{$prefix}\d{12}$/", $snapshot->name);
    }

    /**
     * If user defined prefix is "foobar", then snapshot prefix will be "foobar-autobackup-".
     * @return string
     */
    protected function snapshotPrefix()
    {
        return Configtor::instance()->get('prefix', 'oceanbackup') . '-autobackup-';
    }
}