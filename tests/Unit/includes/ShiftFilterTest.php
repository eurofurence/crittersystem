<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\includes;

use Engelsystem\ShiftsFilter;
use Engelsystem\Test\Unit\TestCase;

class ShiftsFilterTest extends TestCase
{
    /**
     * @covers \Engelsystem\ShiftsFilter::sessionImport
     */
    public function testImportsLegacySession(): void
    {
        $session = array (
            'userShiftsAdmin' => false,
            'filled' => array ( 0 => '0', ),
            'locations' => array ( 0 => '91', ),
            'types' => array ( 0 => '1', 1 => '2', 2 => '3', ),
            'startTime' => 1752684300,
            'endTime' => 1752770700,
        );

        $filter = new ShiftsFilter();
        $filter->sessionImport($session);

        $this->assertEqualsCanonicalizing($session['types'], $filter->getTypes());
        $this->assertEqualsCanonicalizing($session['locations'], $filter->getLocations());

        $saved_session = $filter->sessionExport();

        $expected_types = array(1, 2, 3);
        $expected_locations = array(91);

        $this->assertEqualsCanonicalizing($expected_types, $saved_session['types']);
        $this->assertEqualsCanonicalizing($expected_locations, $saved_session['locations']);
        $this->assertEqualsCanonicalizing($expected_types, $saved_session['all_types']);
        $this->assertEqualsCanonicalizing($expected_locations, $saved_session['all_locations']);
    }

    /**
     * @covers \Engelsystem\ShiftsFilter::updateLocations
     * @covers \Engelsystem\ShiftsFilter::updateTypes
     */
    public function testUpdatesLegacySessionWithAdditional(): void
    {
        $session = array (
            'userShiftsAdmin' => false,
            'filled' => array ( 0 => '0', ),
            'locations' => array ( 0 => '91', ),
            'types' => array ( 0 => '1', 1 => '2', 2 => '3', ),
            'startTime' => 1752684300,
            'endTime' => 1752770700,
        );

        $filter = new ShiftsFilter();
        $filter->sessionImport($session);
        $location_ids = array(91, 92);
        $type_ids = array(1, 2, 3, 10, 11);
        $own_type_ids = array(1, 2, 3, 10);

        $filter->updateLocations($location_ids);
        $filter->updateTypes($type_ids, $own_type_ids);

        $this->assertEqualsCanonicalizing($own_type_ids, $filter->getTypes());
        $this->assertEqualsCanonicalizing($location_ids, $filter->getLocations());

        $saved_session = $filter->sessionExport();

        $this->assertEqualsCanonicalizing($own_type_ids, $saved_session['types']);
        $this->assertEqualsCanonicalizing($location_ids, $saved_session['locations']);
        $this->assertEqualsCanonicalizing($type_ids, $saved_session['all_types']);
        $this->assertEqualsCanonicalizing($location_ids, $saved_session['all_locations']);
    }

    /**
     * @covers \Engelsystem\ShiftsFilter::updateLocations
     * @covers \Engelsystem\ShiftsFilter::updateTypes
     */
    public function testUpdatesLegacySessionWithNew(): void
    {
        $session = array (
            'userShiftsAdmin' => false,
            'filled' => array ( 0 => '0', ),
            'locations' => array ( 0 => '91', ),
            'types' => array ( 0 => '1', 1 => '2', 2 => '3', ),
            'startTime' => 1752684300,
            'endTime' => 1752770700,
        );

        $filter = new ShiftsFilter();
        $filter->sessionImport($session);
        $location_ids = array(92);
        $type_ids = array(10, 11);
        $own_type_ids = array(10);

        $expect_locations = array(91, 92);
        $expect_types = array(1, 2, 3, 10);

        $filter->updateLocations($location_ids);
        $filter->updateTypes($type_ids, $own_type_ids);

        $this->assertEqualsCanonicalizing($expect_types, $filter->getTypes());
        $this->assertEqualsCanonicalizing($expect_locations, $filter->getLocations());

        $saved_session = $filter->sessionExport();
        // during export non-existing IDs are cleaned up
        $this->assertEqualsCanonicalizing($own_type_ids, $saved_session['types']);
        $this->assertEqualsCanonicalizing($location_ids, $saved_session['locations']);
        $this->assertEqualsCanonicalizing($type_ids, $saved_session['all_types']);
        $this->assertEqualsCanonicalizing($location_ids, $saved_session['all_locations']);
    }


        /**
     * @covers \Engelsystem\ShiftsFilter::updateLocations
     * @covers \Engelsystem\ShiftsFilter::updateTypes
     */
    public function testUpdatesSessionWithoutUnselected(): void
    {
        $session = array (
            'userShiftsAdmin' => false,
            'filled' => array ( 0 => 0, ),
            'locations' => array ( 0 => 91, ),
            'all_locations' => array ( 0 => 91, 1 => 92, ),
            'types' => array ( 0 => 1, 1 => 2, 2 => 5, ),
            'all_types' => array ( 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, ),
            'startTime' => 1752684300,
            'endTime' => 1752770700,
        );

        $filter = new ShiftsFilter();
        $filter->sessionImport($session);
        $location_ids = array(91, 92, 100);
        $type_ids = array(1, 2, 3, 4, 5, 10, 11);
        $own_type_ids = array(1, 2, 3, 4, 10);

        $expect_locations = array(91, 100);
        $expect_types = array(1, 2, 5, 10);

        $filter->updateLocations($location_ids);
        $filter->updateTypes($type_ids, $own_type_ids);

        $this->assertEqualsCanonicalizing($expect_types, $filter->getTypes());
        $this->assertEqualsCanonicalizing($expect_locations, $filter->getLocations());

        $saved_session = $filter->sessionExport();
        // during export non-existing IDs are cleaned up
        $this->assertEqualsCanonicalizing($expect_types, $saved_session['types']);
        $this->assertEqualsCanonicalizing($expect_locations, $saved_session['locations']);
        $this->assertEqualsCanonicalizing($type_ids, $saved_session['all_types']);
        $this->assertEqualsCanonicalizing($location_ids, $saved_session['all_locations']);
    }
}
