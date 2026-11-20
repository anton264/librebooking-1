<?php

require_once(ROOT_DIR . 'Domain/Values/ReservationStartTimeConstraint.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationPastTimeConstraint.php');

class ReservationStartTimeRule implements IReservationValidationRule
{
    /**
     * @var IScheduleRepository
     */
    private $scheduleRepository;

    public function __construct(IScheduleRepository $scheduleRepository)
    {
        $this->scheduleRepository = $scheduleRepository;
    }

    /**
     * @param ReservationSeries $reservationSeries
     * @param $retryParameters
     * @return ReservationRuleResult
     * @throws Exception
     */
    public function Validate($reservationSeries, $retryParameters)
    {
        $constraint = Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT);

        if (empty($constraint)) {
            $constraint = ReservationStartTimeConstraint::_DEFAULT;
        }

        if ($constraint == ReservationStartTimeConstraint::NONE) {
            return new ReservationRuleResult();
        }

        $currentInstance = $reservationSeries->CurrentInstance();

        $dateThatShouldBeLessThanNow = $currentInstance->StartDate();
        if ($constraint == ReservationStartTimeConstraint::CURRENT) {
            $timezone = $dateThatShouldBeLessThanNow->Timezone();
            /** @var SchedulePeriod $currentPeriod */
            $currentPeriod = $this->scheduleRepository
                    ->GetLayout($reservationSeries->ScheduleId(), new ScheduleLayoutFactory($timezone))
                    ->GetPeriod($currentInstance->EndDate());
            $dateThatShouldBeLessThanNow = $currentPeriod->BeginDate();

            if ($this->IsPastStartedSlotCutoff($reservationSeries, $currentInstance, $timezone)) {
                return new ReservationRuleResult(false, Resources::GetInstance()->GetString('StartIsInPast'));
            }
        }
        Log::Debug('Start Time Rule: Comparing %s to %s', $dateThatShouldBeLessThanNow, Date::Now());

        $startIsInFuture = $dateThatShouldBeLessThanNow->Compare(Date::Now()) >= 0;
        return new ReservationRuleResult($startIsInFuture, Resources::GetInstance()->GetString('StartIsInPast'));
    }

    /**
     * When the reservation starts in a slot that has already begun, the slot
     * must still have at least the configured cutoff minutes left. Existing
     * reservations whose start time is unchanged are not affected, so an
     * ongoing reservation can still be edited or extended.
     */
    private function IsPastStartedSlotCutoff(
        ReservationSeries $reservationSeries,
        Reservation $currentInstance,
        string $timezone
    ): bool {
        if (ReservationPastTimeConstraint::CurrentSlotCutoffMinutes() === 0) {
            return false;
        }

        $start = $currentInstance->StartDate();
        if (!$start->LessThan(Date::Now())) {
            return false;
        }

        if ($reservationSeries instanceof ExistingReservationSeries) {
            $previousStart = $currentInstance->PreviousStartDate();
            if ($previousStart == null || $previousStart instanceof NullDate || $previousStart->Equals($start)) {
                return false;
            }
        }

        $startPeriod = $this->scheduleRepository
                ->GetLayout($reservationSeries->ScheduleId(), new ScheduleLayoutFactory($timezone))
                ->GetPeriod($start);
        $slotEnd = $startPeriod == null ? $currentInstance->EndDate() : $startPeriod->EndDate();

        return ReservationPastTimeConstraint::IsPastCurrentSlotCutoff($slotEnd);
    }
}
