<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Engelsystem\Models\Location;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\Shifts\ShiftType;
use Engelsystem\Models\User\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShiftExportHelper
{
    public function __construct(
        protected Location $location,
        protected AngelType $angelType,
        protected Shift $shift,
        protected ShiftType $shiftType,
        protected User $user
    ) {
    }


    public function exportToSpreadsheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $row = 2; // First row is headers

        $shiftTypes = $this->shiftType->all();

        // 1. Get all shift types
        foreach ($shiftTypes as $shiftType) {
            $shifts = $this->shift
                ->whereShiftTypeId($shiftType->id)
                ->get();

            foreach ($shifts as $shift) {
                $sheet->setCellValue([1, $row], $shiftType->id);
                $sheet->setCellValue([2, $row], $shiftType->name);

                $sheet->setCellValue([3, $row], $shift->id);
                $sheet->setCellValue([4, $row], $shift->title);
                $sheet->setCellValue([5, $row], $shift->start->format('Y-m-d H:i:s'));
                $sheet->setCellValue([6, $row], $shift->end->format('Y-m-d H:i:s'));

                // TODO: Clean-up the function and improve the code
//
//                // Get angel types for this shift
////                $angelTypes = $shift->angelTypes()->get();
//                $angelTypes = $shift->neededAngelTypes()->get();
//
//                $angelTypeNames = $angelTypes->pluck('name')->implode(', ');
//                $sheet->setCellValue([2, $row], $angelTypeNames);
//
//                $sheet->setCellValue([3, $row], $shift->start->format('Y-m-d H:i:s'));
//                $sheet->setCellValue([4, $row], $shift->end->format('Y-m-d H:i:s'));
//
//                // Get users assigned to this shift
////                $users = $shift->users()->get();
////                $userNames = $users->pluck('name')->implode(', ');
////                $sheet->setCellValue([5, $row], $userNames);
                $row++;
            }
        }


//        dd();
//
//
//
//
//        $locations = $this->location->all();
//        foreach ($locations as $location) {
//            $shifts = $this->shift
//                ->whereLocationId($location->id)
//                ->get();
//
//            foreach ($shifts as $shift) {
//                $sheet->setCellValue([1, $row], $location->name);
//
//                // Get angel types for this shift
////                $angelTypes = $shift->angelTypes()->get();
//                $angelTypes = $shift->neededAngelTypes()->get();
//                dd($angelTypes);
//                $angelTypeNames = $angelTypes->pluck('name')->implode(', ');
//                $sheet->setCellValue([2, $row], $angelTypeNames);
//
//                $sheet->setCellValue([3, $row], $shift->start->format('Y-m-d H:i:s'));
//                $sheet->setCellValue([4, $row], $shift->end->format('Y-m-d H:i:s'));
//
//                // Get users assigned to this shift
////                $users = $shift->users()->get();
////                $userNames = $users->pluck('name')->implode(', ');
////                $sheet->setCellValue([5, $row], $userNames);
//
//                $row++;
//            }
//        }
    }

    public function importFromFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Remove headers
        array_shift($rows);

        $stats = [
            'locations_created' => 0,
            'angel_types_created' => 0,
            'shifts_created' => 0,
            'shifts_updated' => 0,
        ];

        foreach ($rows as $row) {
            if (count($row) < 5) {
                continue;
            }

            [$locationName, $angelTypeNames, $start, $end, $userNames] = $row;

            // Find or create location
            $location = $this->location->firstOrCreate(
                ['name' => trim($locationName)],
            );

            if ($location->wasRecentlyCreated) {
                $stats['locations_created']++;
            }

            // Process angel types
            $angelTypeIds = [];
            foreach (explode(',', $angelTypeNames) as $typeName) {
                $typeName = trim($typeName);
                if (empty($typeName)) {
                    continue;
                }

                $angelType = $this->angelType->firstOrCreate(
                    ['name' => $typeName]
                );

                if ($angelType->wasRecentlyCreated) {
                    $stats['angel_types_created']++;
                }

                $angelTypeIds[] = $angelType->id;
            }

            // Create or update shift
            $shift = $this->shift->firstOrNew([
                'location_id' => $location->id,
                'start' => $start,
                'end' => $end,
            ]);

            if (!$shift->exists) {
                $stats['shifts_created']++;
            } else {
                $stats['shifts_updated']++;
            }

            $shift->save();

            // Sync angel types
            $shift->angelTypes()->sync($angelTypeIds);

            // Process users
            $userIds = [];
            foreach (explode(',', $userNames) as $userName) {
                $userName = trim($userName);
                if (empty($userName)) {
                    continue;
                }

                $user = $this->user->where('name', $userName)->first();
                if ($user) {
                    $userIds[] = $user->id;
                }
            }

            // Sync users
            $shift->users()->sync($userIds);
        }

        return $stats;
    }
}
