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
     * Every resource in the reservation must accept the start time. A
     * resource with its own started slot cutoff is validated as if the
     * constraint were 'current' with that cutoff; other resources follow the
     * global start time constraint.
     *
     * @param ReservationSeries $reservationSeries
     * @param $retryParameters
     * @return ReservationRuleResult
     * @throws Exception
     */
    public function Validate($reservationSeries, $retryParameters)
    {
        $constraint = ReservationPastTimeConstraint::StartTimeConstraint();

        if ($constraint == ReservationStartTimeConstraint::NONE) {
            return new ReservationRuleResult();
        }

        foreach ($this->GetResourceCutoffs($reservationSeries) as $resourceCutoff) {
            if ($resourceCutoff === null) {
                $isValid = $this->IsValidFor(
                    reservationSeries: $reservationSeries,
                    constraint: $constraint,
                    cutoffMinutes: ReservationPastTimeConstraint::CurrentSlotCutoffMinutes()
                );
            } else {
                $isValid = $this->IsValidFor(
                    reservationSeries: $reservationSeries,
                    constraint: ReservationStartTimeConstraint::CURRENT,
                    cutoffMinutes: $resourceCutoff
                );
            }

            if (!$isValid) {
                return new ReservationRuleResult(false, Resources::GetInstance()->GetString('StartIsInPast'));
            }
        }

        return new ReservationRuleResult();
    }

    /**
     * @return array<int|null> distinct started slot cutoffs of the reserved
     *                         resources; null means the global setting applies
     */
    private function GetResourceCutoffs(ReservationSeries $reservationSeries): array
    {
        $cutoffs = [];
        foreach ($reservationSeries->AllResources() as $resource) {
            if ($resource instanceof BookableResource) {
                $cutoffs[] = $resource->GetStartedSlotCutoffMinutes();
            }
        }

        if (empty($cutoffs)) {
            return [null];
        }

        return array_unique($cutoffs, SORT_REGULAR);
    }

    private function IsValidFor(ReservationSeries $reservationSeries, string $constraint, int $cutoffMinutes): bool
    {
        $currentInstance = $reservationSeries->CurrentInstance();

        $dateThatShouldBeLessThanNow = $currentInstance->StartDate();
        if ($constraint == ReservationStartTimeConstraint::CURRENT) {
            $timezone = $dateThatShouldBeLessThanNow->Timezone();
            $currentPeriod = $this->scheduleRepository
                    ->GetLayout($reservationSeries->ScheduleId(), new ScheduleLayoutFactory($timezone))
                    ->GetPeriod($currentInstance->EndDate());
            $dateThatShouldBeLessThanNow = $currentPeriod == null
                    ? $currentInstance->EndDate()
                    : $currentPeriod->BeginDate();

            if ($this->IsPastStartedSlotCutoff($reservationSeries, $currentInstance, $timezone, $cutoffMinutes)) {
                return false;
            }
        }
        Log::Debug('Start Time Rule: Comparing %s to %s', $dateThatShouldBeLessThanNow, Date::Now());

        return $dateThatShouldBeLessThanNow->Compare(Date::Now()) >= 0;
    }

    /**
     * When the reservation starts in a slot that has already begun, the slot
     * must still have at least the cutoff minutes left. Existing reservations
     * whose start time is unchanged are not affected, so an ongoing
     * reservation can still be edited or extended.
     */
    private function IsPastStartedSlotCutoff(
        ReservationSeries $reservationSeries,
        Reservation $currentInstance,
        string $timezone,
        int $cutoffMinutes
    ): bool {
        if ($cutoffMinutes === 0) {
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

        return ReservationPastTimeConstraint::IsPastCurrentSlotCutoff(
            slotEnd: $slotEnd,
            cutoffMinutes: $cutoffMinutes
        );
    }
}
