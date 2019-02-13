<?php
namespace LexicalBits\BackupRotator\Rotator;

use LexicalBits\BackupRotator\Storage\Storage;

class WMYRotator {
    protected $storage;
    protected $daysToKeep;
    protected $monthsToKeep;
    protected $yearsToKeep;
    public function __construct(Storage $storage, int $daysToKeep, int $monthsToKeep, int $yearsToKeep)
    {
        $this->storage = $storage;
        $this->daysToKeep = $daysToKeep;
        $this->monthsToKeep = $monthsToKeep;
        $this->yearsToKeep = $yearsToKeep;
        $this->dateOfRecord = new DateTime();
    }

    protected function generateStorageDates()
    {
        $dates = [];
        // Days
        for($i = 1; $i <= $this->daysToKeep; $i ++) {
            $date = clone $this->dateOfRecord;
            $date->modify('-'.$i.' days');
            $dates[] = $date;
        }
        // Months
        for($i = 1; $i <= $this->daysToKeep; $i ++) {
            $date = clone $this->dateOfRecord;
            $date->modify('first day of this month');
            $date->modify('-'.$i.' month');
            $dates[] = $date;
        }
        // Years
        for($i = 1; $i <= $this->daysToKeep; $i ++) {
            $date = clone $this->dateOfRecord;
            $date->modify('first day of this year');
            $date->modify('-'.$i.' year');
            $dates[] = $date;
        }
        return $dates;
    }

    public function rotate()
    {
        $neededDates = $this->generateStorageDates();
        $existingDates = $this->storage->getExistingCopies();
        $toAdd = array_diff($existingDates, $neededDates);
        $toDelete = array_diff($neededDates, $existingDates);
        foreach($toAdd as $date) {
            $this->storage->copyWithModifier($date->format('Y-m-d'), false);
        }
        foreach($toDelete as $date) {
            $this->storage->removeWithModifier($date->format('Y-m-d'));
        }
    }
}
