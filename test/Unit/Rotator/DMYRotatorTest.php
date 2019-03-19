<?php
namespace LexicalBits\BackupRotator\Test\Unit\Rotator;

use LexicalBits\BackupRotator\Test\TestCase;
use LexicalBits\BackupRotator\Rotator\DMYRotator;

final class DMYRotatorTest extends TestCase
{
    public function makeStorage()
    {
        return $this->getMockBuilder(MockStorage::class)
            ->setMethods(['copyWithModifier', 'deleteWithModifier'])
            ->getMock();
    }
    public function testRotateCreatesToday()
    {
        $today = new \DateTime();
        $storage = $this->makeStorage();
        $storage->existingModifiers = [];
        $rotator = new DMYRotator($storage, 1, 0, 0);
        $storage->expects($this->once())
            ->method('copyWithModifier')
            ->with($today->format('Y-m-d'));
        $rotator->rotate();
    }

    public function testRotateOneDayRemovesTomorrow()
    {
        $today = new \DateTime();
        $tomorrow = clone $today;
        $tomorrow->modify('-1 day');
        $storage = $this->makeStorage();
        $storage->existingModifiers = [
            $today->format('Y-m-d')
            , $tomorrow->format('Y-m-d')
        ];
        $rotator = new DMYRotator($storage, 1, 0, 0);
        $storage->expects($this->once())
            ->method('deleteWithModifier')
            ->with($tomorrow->format('Y-m-d'));
        $rotator->rotate();
    }
    public function testRotateTwoMonthsRemovesTwoMonthsAgo()
    {
        $today = new \DateTime();
        $lastMonth = clone $today;
        $lastMonth->modify('first day of this month');
        $twoMonths = clone $lastMonth;
        $twoMonths->modify('-1 month');
        $storage = $this->makeStorage();
        $storage->existingModifiers = [
            $today->format('Y-m-d')
            , $lastMonth->format('Y-m-d')
            , $twoMonths->format('Y-m-d')
        ];
        $rotator = new DMYRotator($storage, 1, 1, 0);
        $storage->expects($this->once())
            ->method('deleteWithModifier')
            ->with($twoMonths->format('Y-m-d'));
        $rotator->rotate();
    }
    public function testRotateComplexConfigWorksCorrectly()
    {
        $storage = $this->makeStorage();
        $storage->existingModifiers = [
            '2019-01-11',
            '2019-01-10',
            '2019-01-09',
            '2019-01-07',
            '2019-01-06',
            '2019-01-05',
            '2019-01-04',
            '2019-01-01',
            '2018-12-31',
            '2018-12-30',
            '2018-12-01',
            '2018-11-01',
            '2018-01-01',
            '2017-01-01',
            '2016-01-01'
        ];
        $rotator = new DMYRotator($storage, 5, 2, 2);
        $this->setInternalProperty(DMYRotator::class, 'dateOfRecord', $rotator, new \DateTime('2019-01-11'));
        $storage->expects($this->exactly(8))
            ->method('deleteWithModifier')
            ->withConsecutive(
                ['2019-01-06'],
                ['2019-01-05'],
                ['2019-01-04'],
                ['2018-12-31'],
                ['2018-12-30'],
                ['2018-11-01'],
                ['2017-01-01'],
                ['2016-01-01']
            );
        $rotator->rotate();
    }
}
