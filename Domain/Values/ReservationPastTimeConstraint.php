<?php

class ReservationPastTimeConstraint
{
    public static function IsPast(Date $begin, Date $end): bool
    {
        $constraint = Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_START_TIME_CONSTRAINT);

        if (empty($constraint)) {
            $constraint = ReservationStartTimeConstraint::_DEFAULT;
        }

        if ($constraint == ReservationStartTimeConstraint::NONE) {
            return false;
        }

        if ($constraint == ReservationStartTimeConstraint::CURRENT) {
            $now = Date::Now();
            if ($end->LessThan($now)) {
                return true;
            }

            return $begin->LessThan($now) && self::IsPastCurrentSlotCutoff($end);
        }

        return $begin->LessThan(Date::Now());
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
     * True when fewer than the configured cutoff minutes remain before the
     * given slot end.
     */
    public static function IsPastCurrentSlotCutoff(Date $slotEnd): bool
    {
        $cutoff = self::CurrentSlotCutoffMinutes();

        return $slotEnd->AddMinutes(-$cutoff)->LessThan(Date::Now());
    }
}
