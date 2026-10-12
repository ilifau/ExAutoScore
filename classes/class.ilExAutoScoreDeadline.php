<?php
declare(strict_types=1);

// Copyright (c) 2026 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Single rule for "has the submission phase of this member ended?"
 *
 * Uses the effective deadline of ILIAS (ilExcAssMemberState), which includes
 * individual deadlines, the grace period, relative deadlines and teams.
 */
class ilExAutoScoreDeadline
{
    /** @var array<string, int> effective deadline per "assignment:user" within one request */
    private static array $cache = [];

    /**
     * Results and grades may be shown or published for this user
     */
    public static function hasEnded(ilExAssignment $ass, int $user_id): bool
    {
        $key = $ass->getId() . ':' . $user_id;
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = ilExcAssMemberState::getInstanceByIds($ass->getId(), $user_id)->getEffectiveDeadline();
        }
        return self::isOver(self::$cache[$key], time());
    }

    /**
     * Same as hasEnded() for all given users (e.g. the members of a team)
     * @param int[] $user_ids
     */
    public static function hasEndedForAll(ilExAssignment $ass, array $user_ids): bool
    {
        foreach ($user_ids as $user_id) {
            if (!self::hasEnded($ass, (int) $user_id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * An assignment without any deadline has no protection phase
     */
    public static function isOver(int $effective_deadline, int $now): bool
    {
        return $effective_deadline <= 0 || $now > $effective_deadline;
    }
}
