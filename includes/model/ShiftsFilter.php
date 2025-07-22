<?php

namespace Engelsystem;

use Engelsystem\Helpers\Carbon;

/**
 * BO Class that stores all parameters used to filter shifts for users.
 *
 * @author msquare
 */
class ShiftsFilter
{
    /**
     * Shift is completely full.
     */
    public const FILLED_FILLED = 1;

    /**
     * Shift has some free slots.
     */
    public const FILLED_FREE = 0;

    /**
     * Has the user "user shifts admin" privilege?
     *
     * @var boolean
     */
    private $userShiftsAdmin;

    /** @var int[] */
    private $filled;

    /** @var int[] */
    private $selectedTypes;

    /** @var int[] */
    private $selectedLocations;

    /** @var int unix timestamp */
    private $startTime = null;

    /** @var int unix timestamp */
    private $endTime = null;

    /**
     * ShiftsFilter constructor.
     *
     * @param bool  $user_shifts_admin
     * @param int[] $allLocations
     * @param int[] $types
     * @param int[] $ownTypes
     */
    public function __construct($user_shifts_admin = false, private $locations = [], private $types = [], $ownTypes = [])
    {
        $this->selectedTypes = $ownTypes;
        $this->selectedLocations = $locations;

        $this->filled = [
            ShiftsFilter::FILLED_FREE,
        ];

        if ($user_shifts_admin) {
            $this->filled[] = ShiftsFilter::FILLED_FILLED;
        }
    }

    /**
     * @return array
     */
    public function sessionExport()
    {
        // remove nonexisting locations
        $this->selectedLocations = array_intersect($this->locations, $this->selectedLocations);
        // remove non-existing types
        $this->selectedTypes = array_intersect($this->types, $this->selectedTypes);

        return [
            'userShiftsAdmin' => $this->userShiftsAdmin,
            'filled'          => $this->filled,
            'locations'       => $this->selectedLocations,
            'all_locations'   => $this->locations,
            'types'           => $this->selectedTypes,
            'all_types'       => $this->types,
            'startTime'       => $this->startTime,
            'endTime'         => $this->endTime,
        ];
    }

    /**
     * @param array $data
     */
    public function sessionImport($data)
    {
        $this->userShiftsAdmin = $data['userShiftsAdmin'] ?? false;
        $this->filled = $data['filled'] ?? [];
        $this->selectedLocations = $data['locations'] ?? [];
        $this->locations = $data['all_locations'] ?? $this->selectedLocations;
        $this->selectedTypes = $data['types'] ?? [];
        $this->types = $data['all_types'] ?? $this->selectedTypes;
        $this->startTime = $data['startTime'] ?? null;
        $this->endTime = $data['endTime'] ?? null;

        $this->filled = array_map('intval', $this->filled);
        $this->selectedLocations = array_map('intval', $this->selectedLocations);
        $this->locations = array_map('intval', $this->locations);
        $this->selectedTypes = array_map('intval', $this->selectedTypes);
        $this->types = array_map('intval', $this->types);
    }

    /**
     * Update the location list. Add any new locations to the selectedLocations
     * @param array $allLocations
     * @return void
     */
    public function updateLocations($allLocations)
    {
        $diff = array_diff($allLocations, $this->locations);
        $this->locations = $allLocations;
        if (count($diff) > 0) {
            $newDiffLocations = array_diff($diff, $this->selectedLocations);
            $this->selectedLocations = array_merge($this->selectedLocations, $newDiffLocations);
        }
    }

    /**
     * Update the types list. Add new types if they are own to the selectedTypes
     * @param mixed $allTypes
     * @return void
     */
    public function updateTypes($allTypes, $ownTypes)
    {
        $diff = array_diff($allTypes, $this->types);
        $this->types = $allTypes;
        if (count($diff) > 0) {
            // find new types that are own types
            $newOwnTypes = array_intersect($ownTypes, $diff);
            $newDiffTypes = array_diff($newOwnTypes, $this->selectedTypes);
            $this->selectedTypes = array_merge($this->selectedTypes, $newDiffTypes);
        }
    }

    /**
     * @return Carbon
     */
    public function getStart()
    {
        return Carbon::createFromTimestamp($this->startTime);
    }

    /**
     * @return int unix timestamp
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param int $startTime unix timestamp
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * @return Carbon
     */
    public function getEnd()
    {
        return Carbon::createFromTimestamp($this->endTime);
    }

    /**
     * @return int unix timestamp
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @param int $endTime unix timestamp
     */
    public function setEndTime($endTime)
    {
        $filterMaxDuration = config('filter_max_duration') * 60 * 60;
        if ($filterMaxDuration && ($endTime - $this->startTime > $filterMaxDuration)) {
            $endTime = $this->startTime + $filterMaxDuration;
        }

        $this->endTime = $endTime;
    }

    /**
     * @return int[]
     */
    public function getTypes()
    {
        if (count($this->selectedTypes) == 0) {
            return [0];
        }
        return $this->selectedTypes;
    }

    /**
     * @param int[] $types
     */
    public function setTypes($selectedTypes)
    {
        $this->selectedTypes = array_map('intval', $selectedTypes);
    }

    /**
     * @return int[]
     */
    public function getLocations()
    {
        if (count($this->selectedLocations) == 0) {
            return [0];
        }
        return $this->selectedLocations;
    }

    /**
     * @param int[] $locations
     */
    public function setLocations($locations)
    {
        $this->selectedLocations = array_map('intval', $locations);
    }

    /**
     * @param bool $userShiftsAdmin
     */
    public function setUserShiftsAdmin($userShiftsAdmin)
    {
        $this->userShiftsAdmin = $userShiftsAdmin;
    }

    /**
     * @return int[]
     */
    public function getFilled()
    {
        return $this->filled;
    }

    /**
     * @param int[] $filled
     */
    public function setFilled($filled)
    {
        $this->filled = array_map('intval', $filled);
    }
}
