<?php
namespace LexicalBits\BackupRotator\Rotator;

use LexicalBits\BackupRotator\Storage\Storage;

/**
 * Class: WMYRotator - A rotator that keeps a fixed number of daily, weekly, and yearly backups.
 *
 * @see Rotator
 */
class DMYRotator extends Rotator
{
    /**
     * The engine we're using for rotation
     *
     * @var Storage
     */
    protected $storage;
    /**
     * How many previous days' backups to keep
     *
     * @var int
     */
    protected $daysToKeep;
    /**
     * How many previous months' backups to keep
     *
     * @var int
     */
    protected $monthsToKeep;
    /**
     * How many previous years' backups to keep
     *
     * @var int
     */
    protected $yearsToKeep;
    /**
     * Which date we consider "today"
     *
     * @var \DateTime
     */
    protected $dateOfRecord;
    static public function fromConfig(array $config)
    {
    }
    /**
     * Make a rotator that keeps a certain number of days in each category.
     *
     * @param Storage $storage The engine we're using for rotation
     * @param int $daysToKeep How many previous days' backups to keep
     * @param int $monthsToKeep How many previous months' backups to keep
     * @param int $yearsToKeep How many previous years' backups to keep
     */
    public function __construct(Storage $storage, int $daysToKeep, int $monthsToKeep, int $yearsToKeep)
    {
        $this->storage = $storage;
        $this->daysToKeep = $daysToKeep;
        $this->monthsToKeep = $monthsToKeep;
        $this->yearsToKeep = $yearsToKeep;
        $this->dateOfRecord = new \DateTime();
    }

    /**
     * Generate a unique list of dates who should be kept if they exist.
     *
     */
    protected function generateStorageModifiers()
    {
        $dates = [];
        // Days, working backwards from today
        for($i = 0; $i < $this->daysToKeep; $i ++) {
            $date = clone $this->dateOfRecord;
            $date->modify('-'.$i.' days');
            $dates[] = $date;
        }
        // Months, working backwards from the first day of the current month
        for($i = 0; $i < $this->monthsToKeep; $i ++) {
            $date = clone $this->dateOfRecord;
            $date->modify('first day of this month');
            $date->modify('-'.$i.' month');
            $dates[] = $date;
        }
        // Years, working backwards from the first day of this year.
        for($i = 0; $i < $this->yearsToKeep; $i ++) {
            $date = clone $this->dateOfRecord;
            $date->modify('first day of this year');
            $date->modify('-'.$i.' year');
            $dates[] = $date;
        }
        return array_unique(
            array_map([$this, 'makeModifier'], $dates)
        );
    }

    /**
     * Turn a date into a string modifier the storage engine will understand
     *
     * @param Date $date
     */
    protected function makeModifier(\DateTime $date)
    {
        return $date->format('Y-m-d');
    }

    public function rotate()
    {
        // First, copy the current file to a timestamped one
        $this->storage->copyWithModifier($this->makeModifier($this->dateOfRecord));
        // Then figure out which old files we can get rid of
        $datesToKeep = $this->generateStorageModifiers();
        $existingDates = $this->storage->getExistingCopyModifiers();
        $toDelete = array_diff($existingDates, $datesToKeep);
        foreach($toDelete as $modifier) {
            $this->storage->deleteWithModifier($modifier);
        }
    }
}
