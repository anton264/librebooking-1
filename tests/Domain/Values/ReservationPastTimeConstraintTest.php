<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Domain/Values/ReservationStartTimeConstraint.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationPastTimeConstraint.php');

class ReservationPastTimeConstraintTest extends TestBase
{
    private Date $now;

    public function setUp(): void
    {
        parent::setup();
        $this->now = Date::Parse('2026-11-20 10:20:00', 'UTC');
        Date::_SetNow($this->now);
    }

    public function testFutureConstraintTreatsStartedSlotAsPast()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::FUTURE);

        $this->assertTrue(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('11:00')));
        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('11:00'), $this->Slot('12:00')));
    }

    public function testCurrentConstraintWithoutCutoffAllowsStartedSlotUntilItEnds()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);

        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:30')));
        $this->assertTrue(ReservationPastTimeConstraint::IsPast($this->Slot('09:30'), $this->Slot('10:00')));
    }

    public function testCurrentConstraintAllowsStartedSlotBeforeCutoff()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 15);

        // 40 minutes left, cutoff 15
        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('11:00')));
        // exactly 15 minutes left
        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:35')));
    }

    public function testCurrentConstraintBlocksStartedSlotAfterCutoff()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 15);

        // 10 minutes left, cutoff 15
        $this->assertTrue(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:30')));
    }

    public function testCutoffDoesNotAffectSlotsThatHaveNotStarted()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 15);

        // a 10 minute slot that starts in the future is shorter than the cutoff
        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:25'), $this->Slot('10:35')));
    }

    public function testNegativeCutoffIsTreatedAsZero()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, -10);

        $this->assertSame(0, ReservationPastTimeConstraint::CurrentSlotCutoffMinutes());
    }

    public function testResourceCutoffAllowsStartedSlotEvenWhenGlobalConstraintIsFuture()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::FUTURE);

        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('11:00'), 15));
        $this->assertTrue(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:30'), 15));
        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:30'), 0));
    }

    public function testResourceCutoffOverridesGlobalCurrentCutoff()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 30);

        // 20 minutes left: blocked by the global cutoff, allowed by the resource cutoff
        $this->assertTrue(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:40')));
        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('10:00'), $this->Slot('10:40'), 10));
    }

    public function testNoConstraintIgnoresResourceCutoff()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::NONE);

        $this->assertFalse(ReservationPastTimeConstraint::IsPast($this->Slot('09:00'), $this->Slot('10:00'), 15));
    }

    private function Slot(string $time): Date
    {
        return Date::Parse("2026-11-20 $time:00", 'UTC');
    }
}
