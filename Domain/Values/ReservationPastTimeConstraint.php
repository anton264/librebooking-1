<?php

class ReservationPastTimeConstraint
{
    /**
     * @param Date $begin slot begin
     * @param Date $end slot end
     * @param int|null $resourceStartedSlotCutoffMinutes per-resource override; when set,
     *                 an already started slot can be reserved until this many minutes
     *                 before it ends, regardless of the global 'future'/'current' setting
     */
    public static function IsPast(Date $begin, Date $end, ?int $resourceStartedSlotCutoffMinutes = null): bool
    {
        $constraint = self::StartTimeConstraint();

        if ($constraint == ReservationStartTimeConstraint::NONE) {
            return false;
        }

        if ($resourceStartedSlotCutoffMinutes !== null) {
            return self::IsPastStartedSlot($begin, $end, $resourceStartedSlotCutoffMinutes);
        }

        if ($constraint == ReservationStartTimeConstraint::CURRENT) {
            return self::IsPastStartedSlot($begin, $end, self::CurrentSlotCutoffMinutes());
        }

        return $begin->LessThan(Date::Now());
    }

    public static function StartTimeConstraint(): string
    {
        $constraint = Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT);

        if (empty($constraint)) {
            return ReservationStartTimeConstraint::_DEFAULT;
        }

        return (string) $constraint;
    }

    /**
     * Minutes before the end of an already started slot after which the slot
     * can no longer be reserved when the start time constraint is 'current'.
     */
    public static function CurrentSlotCutoffMinutes(): int
    {
        $minutes = Configuration::Instance()->GetKey(
            ConfigKeys::RESERVATION_CURRENT_SLOT_CUTOFF_MINUTES,
            new IntConverter()
        );

        return max(0, (int) $minutes);
    }

    /**
     * True when fewer than the cutoff minutes remain before the given slot end.
     *
     * @param int|null $cutoffMinutes defaults to the global 'current' slot cutoff
     */
    public static function IsPastCurrentSlotCutoff(Date $slotEnd, ?int $cutoffMinutes = null): bool
    {
        $cutoff = $cutoffMinutes ?? self::CurrentSlotCutoffMinutes();

        return $slotEnd->AddMinutes(-$cutoff)->LessThan(Date::Now());
    }

    private static function IsPastStartedSlot(Date $begin, Date $end, int $cutoffMinutes): bool
    {
        $now = Date::Now();
        if ($end->LessThan($now)) {
            return true;
        }

        return $begin->LessThan($now) && self::IsPastCurrentSlotCutoff($end, $cutoffMinutes);
    }
}
