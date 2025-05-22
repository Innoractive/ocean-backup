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

    /** @var \DigitalOceanV2\Api\Droplet $droplet Droplet API, initialized in constructor. */
    protected $droplet;

    /** @var \DigitalOceanV2\Api\Volume $volume Volume API, initialized in constructor. */
    protected $volume;

    public function __construct()
    {
        $adapter = new GuzzleHttpAdapter(Configtor::instance()->get('token'));
        $this->digitalOcean = new DigitalOceanV2($adapter);
        $this->droplet = $this->digitalOcean->droplet();
        $this->volume = $this->digitalOcean->volume();
    }

    public function runBackup()
    {
        Logger::info("Processing Droplet snapshots...");
        $dropletIds = Configtor::instance()->get('droplet_ids', []);
        foreach ($dropletIds as $dropletId) {
            try {
                $this->backupDroplet($dropletId);
                $this->deleteOldSnapshots($this->droplet->getSnapshots($dropletId));
            } catch (Exception $exception) {
                Logger::warn("Unable to process Droplet ID({$dropletId}): " . $exception->getMessage());
                continue;
            }
        }

        Logger::info("Processing Volume snapshots...");
        $volumeIds = Configtor::instance()->get('volume_ids', []);
        foreach ($volumeIds as $volumeId) {
            try {
                $this->backupVolume($volumeId);
                $this->deleteOldSnapshots($this->volume->getSnapshots($volumeId));
            } catch (Exception $exception) {
                Logger::warn("Unable to process Volume ID({$volumeId}): ". $exception->getMessage());
                continue;
            }
        }

        Logger::info("Backup process completed.");
    }

    public function backupVolume($volumeId)
    {
        $prefix = $this->snapshotPrefix();
        $timestamp = date('ymdHis');
        try {
            // Snapshot namne: foobar-autobackup-1812252359
            $snapshot = $this->volume->snapshot($volumeId, "{$prefix}{$timestamp}");
            Logger::info("Volume snapshot '{$prefix}{$timestamp}' created.");
        } catch (Exception $exception) {
            Logger::error("Failed to create snapshot for volume({$volumeId}): ". $exception->getMessage());
            throw $exception;
        }
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
            $snapshot = $this->droplet->snapshot($dropletId, "{$prefix}{$timestamp}");
            Logger::info("Droplet snapshot '{$prefix}{$timestamp}' created.");
        } catch (Exception $exception) {
            Logger::error("Failed to create snapshot for DO({$dropletId}): " . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Given a list of Droplet/Volume snapshots, delete old ones.
     * If backup within `rentetion_days`, not deleteion.
     * Else if num of backups < `min_backups`, no deletion.
     * Else, delete.
     * @param array $snapshots
     */
    public function deleteOldSnapshots(array $snapshots)
    {
        $config = Configtor::instance();
        
        // Retain forever if `retention_days` == 0
        $retentionDays = $config->get('retention_days', 7);
        if ($retentionDays <= 0)
            return;

        // Only process those that have matching prefix
        $snapshots = array_filter($snapshots, [$this, "filterSnapshotBackup"]);

        // No deletion if number of backup is lesser than `min_backups`
        $deletableCount = count($snapshots) - $config->get('min_backups', 3);

        // Image API
        $image = $this->digitalOcean->image();

        // Days to seconds conversion, delete snapshots after retention days
        $expiry = time() - $retentionDays * 86400;
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
     * @param $snapshot Either Droplet or Volume snapshot.
     * @return false|int
     */
    protected function filterSnapshotBackup($snapshot)
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