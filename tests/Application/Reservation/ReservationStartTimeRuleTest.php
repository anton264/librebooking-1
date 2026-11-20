<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

class ReservationStartTimeRuleTest extends TestBase
{
    private IScheduleRepository&\PHPUnit\Framework\MockObject\MockObject $scheduleRepository;

    private IScheduleLayout&\PHPUnit\Framework\MockObject\MockObject $layout;

    public function setUp(): void
    {
        parent::setup();
        $this->scheduleRepository = $this->createMock('IScheduleRepository');
        $this->layout = $this->createMock('IScheduleLayout');

        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::FUTURE);
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testRuleIsValidStartTimeIsNow()
    {
        $start = Date::Now();
        $end = Date::Now()->AddDays(1);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertTrue($result->IsValid());
    }

    public function testRuleIsValidIfStartTimeIsInFuture()
    {
        $start = Date::Now()->AddDays(1);
        $end = Date::Now()->AddDays(2);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertTrue($result->IsValid());
    }

    public function testRuleIsInvalidIfStartIsInPast()
    {
        $start = Date::Now()->AddDays(-2);
        $end = Date::Now()->AddDays(-1);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertFalse($result->IsValid());
    }

    public function testRuleIsInvalidIfStartTimeIsInPast()
    {
        $now = Date::Parse('2011-04-04 12:13:15', 'UTC');
        Date::_SetNow($now);
        $start = Date::Parse('2011-04-04 12:13:14', 'UTC');
        $end = $start->AddDays(5);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertFalse($result->IsValid());
    }

    public function testWhenAllowedToWorkWithCurrentSlot()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);

        $scheduleId = 123;
        $now = Date::Parse('2011-04-04 12:13:15', 'UTC');
        Date::_SetNow($now);

        $start = $now->AddDays(-5);
        $end = $now->AddDays(5);

        $periodStart =  Date::Parse('2011-04-04 12:13:16', 'UTC');
        $periodEnd = Date::Parse('2011-04-04 12:13:17', 'UTC');

        $reservation = new TestReservationSeries();
        $reservation->WithScheduleId($scheduleId);
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $this->scheduleRepository->expects($this->once())
            ->method('GetLayout')
            ->with($this->equalTo($scheduleId), $this->equalTo(new ScheduleLayoutFactory('UTC')))
            ->willReturn($this->layout);

        $period = new SchedulePeriod($periodStart, $periodEnd);
        $this->layout->expects($this->once())
                ->method('GetPeriod')
                ->with($this->equalTo($end))
                ->willReturn($period);


        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertTrue($result->IsValid());
    }

    public function testWhenPeriodStartTimeIsInPast()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);

        $scheduleId = 123;
        $now = Date::Parse('2011-04-04 12:13:15', 'UTC');
        Date::_SetNow($now);

        $start = $now->AddDays(-5);
        $end = $now->AddDays(5);

        $periodStart = Date::Parse('2011-04-04 12:13:14', 'UTC');
        $periodEnd = Date::Parse('2011-04-04 12:13:16', 'UTC');

        $reservation = new TestReservationSeries();
        $reservation->WithScheduleId($scheduleId);
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $this->scheduleRepository->expects($this->once())
            ->method('GetLayout')
            ->with($this->equalTo($scheduleId), $this->equalTo(new ScheduleLayoutFactory('UTC')))
            ->willReturn($this->layout);

        $period = new SchedulePeriod($periodStart, $periodEnd);
        $this->layout->expects($this->once())
                ->method('GetPeriod')
                ->with($this->equalTo($end))
                ->willReturn($period);


        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertFalse($result->IsValid());
    }

    public function testWhenNotConstrainedByTime()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::NONE);
        $now = Date::Parse('2011-04-04 12:13:15', 'UTC');
        Date::_SetNow($now);
        $start = Date::Parse('2011-04-04 12:13:14', 'UTC');
        $end = $start->AddDays(5);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertTrue($result->IsValid());
    }

    public function testNewReservationInStartedSlotIsValidBeforeCutoff()
    {
        $result = $this->ValidateNewReservationInStartedSlot('2026-11-20 10:40:00');

        $this->assertTrue($result->IsValid());
    }

    public function testNewReservationInStartedSlotIsInvalidAfterCutoff()
    {
        $result = $this->ValidateNewReservationInStartedSlot('2026-11-20 10:50:00');

        $this->assertFalse($result->IsValid());
    }

    public function testCutoffDoesNotApplyToExistingReservationWithUnchangedStart()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 15);
        Date::_SetNow(Date::Parse('2026-11-20 10:50:00', 'UTC'));

        $start = Date::Parse('2026-11-20 10:00:00', 'UTC');
        $instance = new TestReservation('1', new DateRange($start, $start->AddHours(1)));
        // extend the ongoing reservation by one slot, keeping the start time
        $instance->SetReservationDate(new DateRange($start, $start->AddHours(2)));

        $builder = new ExistingReservationSeriesBuilder();
        $builder->WithCurrentInstance($instance);
        $series = $builder->Build();

        $this->scheduleRepository->method('GetLayout')->willReturn($this->layout);
        $this->layout->method('GetPeriod')
                ->willReturnCallback(fn (Date $date) => $this->HourSlotContaining($date));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($series, null);

        $this->assertTrue($result->IsValid());
    }

    public function testCutoffAppliesToExistingReservationMovedIntoStartedSlot()
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 15);
        Date::_SetNow(Date::Parse('2026-11-20 10:50:00', 'UTC'));

        $originalStart = Date::Parse('2026-11-20 14:00:00', 'UTC');
        $instance = new TestReservation('1', new DateRange($originalStart, $originalStart->AddHours(1)));
        $newStart = Date::Parse('2026-11-20 10:00:00', 'UTC');
        $instance->SetReservationDate(new DateRange($newStart, $newStart->AddHours(1)));

        $builder = new ExistingReservationSeriesBuilder();
        $builder->WithCurrentInstance($instance);
        $series = $builder->Build();

        $this->scheduleRepository->method('GetLayout')->willReturn($this->layout);
        $this->layout->method('GetPeriod')
                ->willReturnCallback(fn (Date $date) => $this->HourSlotContaining($date));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);
        $result = $rule->Validate($series, null);

        $this->assertFalse($result->IsValid());
    }

    public function testResourceCutoffAllowsStartedSlotWhenGlobalConstraintIsFuture()
    {
        $result = $this->ValidateNewStartedSlotReservationForResources('2026-11-20 10:40:00', [15]);

        $this->assertTrue($result->IsValid());
    }

    public function testResourceCutoffBlocksStartedSlotAfterCutoff()
    {
        $result = $this->ValidateNewStartedSlotReservationForResources('2026-11-20 10:50:00', [15]);

        $this->assertFalse($result->IsValid());
    }

    public function testEveryResourceMustAllowTheStartedSlot()
    {
        // second resource has no override, so the global 'future' constraint applies to it
        $result = $this->ValidateNewStartedSlotReservationForResources('2026-11-20 10:40:00', [15, null]);

        $this->assertFalse($result->IsValid());
    }

    /**
     * @param array<int|null> $resourceCutoffs
     */
    private function ValidateNewStartedSlotReservationForResources(string $now, array $resourceCutoffs): ReservationRuleResult
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::FUTURE);
        Date::_SetNow(Date::Parse($now, 'UTC'));

        $start = Date::Parse('2026-11-20 10:00:00', 'UTC');
        $end = Date::Parse('2026-11-20 11:00:00', 'UTC');

        $reservation = new TestReservationSeries();
        $reservation->WithScheduleId(123);
        foreach ($resourceCutoffs as $i => $cutoff) {
            $resource = new FakeBookableResource($i + 1);
            $resource->SetStartedSlotCutoffMinutes($cutoff);
            if ($i === 0) {
                $reservation->WithResource($resource);
            } else {
                $reservation->AddResource($resource);
            }
        }
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $this->scheduleRepository->method('GetLayout')->willReturn($this->layout);
        $this->layout->method('GetPeriod')
                ->willReturnCallback(fn (Date $date) => $this->HourSlotContaining($date));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);

        return $rule->Validate($reservation, null);
    }

    private function ValidateNewReservationInStartedSlot(string $now): ReservationRuleResult
    {
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT, ReservationStartTimeConstraint::CURRENT);
        $this->fakeConfig->SetKey(ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES, 15);
        Date::_SetNow(Date::Parse($now, 'UTC'));

        // fixed slot 10:00-11:00
        $start = Date::Parse('2026-11-20 10:00:00', 'UTC');
        $end = Date::Parse('2026-11-20 11:00:00', 'UTC');

        $reservation = new TestReservationSeries();
        $reservation->WithScheduleId(123);
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));

        $this->scheduleRepository->method('GetLayout')->willReturn($this->layout);
        $this->layout->method('GetPeriod')
                ->willReturnCallback(fn (Date $date) => $this->HourSlotContaining($date));

        $rule = new ReservationStartTimeRule($this->scheduleRepository);

        return $rule->Validate($reservation, null);
    }

    private function HourSlotContaining(Date $date): SchedulePeriod
    {
        $begin = Date::Create($date->Year(), $date->Month(), $date->Day(), $date->Hour(), 0, 0, 'UTC');

        return new SchedulePeriod($begin, $begin->AddHours(1));
    }
}
