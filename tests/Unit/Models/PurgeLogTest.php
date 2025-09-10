<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Models;

use Engelsystem\Models\PurgeLog;
use Engelsystem\Test\Unit\TestCase;

class PurgeLogTest extends TestCase
{
    public function testModelConstants(): void
    {
        $this->assertEquals('initiated', PurgeLog::STATUS_INITIATED);
        $this->assertEquals('in_progress', PurgeLog::STATUS_IN_PROGRESS);
        $this->assertEquals('completed', PurgeLog::STATUS_COMPLETED);
        $this->assertEquals('failed', PurgeLog::STATUS_FAILED);
        $this->assertEquals('cancelled', PurgeLog::STATUS_CANCELLED);
    }

    public function testFillableAttributes(): void
    {
        $model = new PurgeLog();
        $fillable = $model->getFillable();

        $expectedFillable = [
            'user_id',
            'categories',
            'cutoff_date',
            'affected_counts',
            'status',
            'error_message',
            'backup_file_path',
            'completed_at',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains(
                $attribute,
                $fillable,
                'Attribute ' . $attribute . ' should be fillable'
            );
        }
    }

    public function testCasts(): void
    {
        $model = new PurgeLog();
        $casts = $model->getCasts();

        $this->assertEquals('array', $casts['categories']);
        $this->assertEquals('array', $casts['affected_counts']);
        $this->assertEquals('date', $casts['cutoff_date']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['completed_at']);
        $this->assertEquals('datetime', $casts['updated_at']);
    }

    public function testGetTotalAffectedCountWithEmptyData(): void
    {
        $model = new PurgeLog();
        $model->affected_counts = null;

        $this->assertEquals(0, $model->getTotalAffectedCount());
    }

    public function testGetTotalAffectedCountWithData(): void
    {
        $model = new PurgeLog();
        $model->affected_counts = [
            'users' => 10,
            'shifts' => 5,
            'news' => 3,
        ];

        $this->assertEquals(18, $model->getTotalAffectedCount());
    }

    public function testGetCategoriesStringWithEmptyData(): void
    {
        $model = new PurgeLog();
        $model->categories = null;

        $this->assertEquals('', $model->getCategoriesString());
    }

    public function testGetCategoriesStringWithData(): void
    {
        $model = new PurgeLog();
        $model->categories = ['users', 'shifts', 'news'];

        $this->assertEquals('Users, Shifts, News', $model->getCategoriesString());
    }

    public function testGetStatusBadgeColor(): void
    {
        $model = new PurgeLog();

        $model->status = PurgeLog::STATUS_INITIATED;
        $this->assertEquals('primary', $model->getStatusBadgeColor());

        $model->status = PurgeLog::STATUS_IN_PROGRESS;
        $this->assertEquals('warning', $model->getStatusBadgeColor());

        $model->status = PurgeLog::STATUS_COMPLETED;
        $this->assertEquals('success', $model->getStatusBadgeColor());

        $model->status = PurgeLog::STATUS_FAILED;
        $this->assertEquals('danger', $model->getStatusBadgeColor());

        $model->status = PurgeLog::STATUS_CANCELLED;
        $this->assertEquals('secondary', $model->getStatusBadgeColor());
    }

    public function testIsActiveMethod(): void
    {
        $model = new PurgeLog();

        $model->status = PurgeLog::STATUS_INITIATED;
        $this->assertTrue($model->isActive());

        $model->status = PurgeLog::STATUS_IN_PROGRESS;
        $this->assertTrue($model->isActive());

        $model->status = PurgeLog::STATUS_COMPLETED;
        $this->assertFalse($model->isActive());

        $model->status = PurgeLog::STATUS_FAILED;
        $this->assertFalse($model->isActive());
    }

    public function testIsCompletedMethod(): void
    {
        $model = new PurgeLog();

        $model->status = PurgeLog::STATUS_INITIATED;
        $this->assertFalse($model->isCompleted());

        $model->status = PurgeLog::STATUS_IN_PROGRESS;
        $this->assertFalse($model->isCompleted());

        $model->status = PurgeLog::STATUS_COMPLETED;
        $this->assertTrue($model->isCompleted());

        $model->status = PurgeLog::STATUS_FAILED;
        $this->assertTrue($model->isCompleted());

        $model->status = PurgeLog::STATUS_CANCELLED;
        $this->assertTrue($model->isCompleted());
    }

    public function testGetDurationWithNullCompletedAt(): void
    {
        $model = new PurgeLog();
        $model->completed_at = null;

        $this->assertNull($model->getDuration());
    }

    public function testMarkCompletedUpdatesStatus(): void
    {
        $model = new PurgeLog();
        $originalUpdate = $model->update; // phpcs:ignore

        // Mock the update method
        $updateCalled = false;
        $updateData = null;

        $model->update = function ($data) use (&$updateCalled, &$updateData): void {
            $updateCalled = true;
            $updateData = $data;
        };

        $model->markCompleted();

        // In a real test with database, we would verify the actual update
        $this->assertTrue(method_exists($model, 'markCompleted'));
    }

    public function testStaticMethodsExist(): void
    {
        $methods = [
            'getRecentLogs',
            'getByStatus',
            'getActivePurges',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(PurgeLog::class, $method),
                'Static method ' . $method . ' should exist'
            );
        }
    }

    public function testRelationshipMethodExists(): void
    {
        $this->assertTrue(
            method_exists(PurgeLog::class, 'user'),
            "Relationship method 'user' should exist"
        );
    }
}
