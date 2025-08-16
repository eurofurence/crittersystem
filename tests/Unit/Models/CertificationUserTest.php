<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Models;

use Carbon\Carbon;
use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Engelsystem\Test\Unit\Models\ModelTest;

class CertificationUserTest extends ModelTest
{
    /** @var array<string, mixed> */
    protected array $data = [
        'user_id' => 1,
        'certification_id' => 1,
        'status' => CertificationUser::STATUS_PENDING,
        'date_completed' => null,
        'date_expires' => null,
        'notes' => 'Test notes',
        'certified_by' => null,
    ];

    /**
     * Test model creation
     */
    public function testCreate(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create();

        $data = array_merge($this->data, [
            'user_id' => $user->id,
            'certification_id' => $certification->id,
        ]);

        $certificationUser = new CertificationUser($data);
        $certificationUser->save();

        $this->assertEquals($user->id, $certificationUser->user_id);
        $this->assertEquals($certification->id, $certificationUser->certification_id);
        $this->assertEquals(CertificationUser::STATUS_PENDING, $certificationUser->status);
        $this->assertEquals('Test notes', $certificationUser->notes);
        $this->assertNull($certificationUser->date_completed);
        $this->assertNull($certificationUser->date_expires);
        $this->assertNull($certificationUser->certified_by);
    }

    /**
     * Test status enum casting
     */
    public function testStatusEnum(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create();

        $certificationUser = CertificationUser::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
            'status' => CertificationUser::STATUS_APPROVED,
        ]);

        $this->assertEquals(CertificationUser::STATUS_APPROVED, $certificationUser->status);
        $this->assertTrue($certificationUser->status === CertificationUser::STATUS_APPROVED);
    }

    /**
     * Test date casting
     */
    public function testDateCasting(): void
    {
        $now = Carbon::now();
        $expires = Carbon::now()->addYear();

        $certificationUser = CertificationUser::factory()->create([
            'date_completed' => $now,
            'date_expires' => $expires,
        ]);

        $this->assertInstanceOf(Carbon::class, $certificationUser->date_completed);
        $this->assertInstanceOf(Carbon::class, $certificationUser->date_expires);
        $this->assertTrue($now->equalTo($certificationUser->date_completed));
        $this->assertTrue($expires->equalTo($certificationUser->date_expires));
    }

    /**
     * Test user relationship
     */
    public function testUserRelationship(): void
    {
        $user = User::factory()->create();
        $certificationUser = CertificationUser::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $certificationUser->user->id);
        $this->assertEquals($user->name, $certificationUser->user->name);
    }

    /**
     * Test certification relationship
     */
    public function testCertificationRelationship(): void
    {
        $certification = Certification::factory()->create();
        $certificationUser = CertificationUser::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $this->assertEquals($certification->id, $certificationUser->certification->id);
        $this->assertEquals($certification->name, $certificationUser->certification->name);
    }

    /**
     * Test isExpired method
     */
    public function testIsExpired(): void
    {
        // Not expired
        $notExpired = CertificationUser::factory()->create([
            'date_expires' => Carbon::now()->addMonths(6),
        ]);
        $this->assertFalse($notExpired->isExpired());

        // Expired
        $expired = CertificationUser::factory()->create([
            'date_expires' => Carbon::now()->subMonths(1),
        ]);
        $this->assertTrue($expired->isExpired());

        // No expiry date
        $noExpiry = CertificationUser::factory()->create([
            'date_expires' => null,
        ]);
        $this->assertFalse($noExpiry->isExpired());
    }

    /**
     * Test isValid method
     */
    public function testIsValid(): void
    {
        // Valid approved certification
        $valid = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_APPROVED,
            'date_expires' => Carbon::now()->addMonths(6),
        ]);
        $this->assertTrue($valid->isValid());

        // Valid self-confirmed certification
        $validSelfConfirmed = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_SELF_CONFIRMED,
            'date_expires' => Carbon::now()->addMonths(6),
        ]);
        $this->assertTrue($validSelfConfirmed->isValid());

        // Pending certification (not valid)
        $pending = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_PENDING,
            'date_expires' => Carbon::now()->addMonths(6),
        ]);
        $this->assertFalse($pending->isValid());

        // Revoked certification (not valid)
        $revoked = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_REVOKED,
            'date_expires' => Carbon::now()->addMonths(6),
        ]);
        $this->assertFalse($revoked->isValid());

        // Expired but approved (not valid)
        $expiredApproved = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_APPROVED,
            'date_expires' => Carbon::now()->subMonths(1),
        ]);
        $this->assertFalse($expiredApproved->isValid());
    }

    /**
     * Test calculateExpiryDate method
     */
    public function testCalculateExpiryDate(): void
    {
        $certification = Certification::factory()->create(['validity_months' => 12]);
        $completedDate = Carbon::now();

        $certificationUser = CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'date_completed' => $completedDate,
        ]);

        $expectedExpiry = $completedDate->copy()->addMonths(12);
        $calculatedExpiry = $certificationUser->calculateExpiryDate();

        $this->assertTrue($expectedExpiry->equalTo($calculatedExpiry));

        // Test with certification that doesn't expire
        $noExpiryCert = Certification::factory()->create(['validity_months' => null]);
        $noExpiryCertUser = CertificationUser::factory()->create([
            'certification_id' => $noExpiryCert->id,
            'date_completed' => $completedDate,
        ]);

        $this->assertNull($noExpiryCertUser->calculateExpiryDate());

        // Test with no completion date
        $noCompletionDate = CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'date_completed' => null,
        ]);

        $this->assertNull($noCompletionDate->calculateExpiryDate());
    }

    /**
     * Test isSelfConfirmed method
     */
    public function testIsSelfConfirmed(): void
    {
        $selfConfirmed = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_SELF_CONFIRMED,
        ]);
        $this->assertTrue($selfConfirmed->isSelfConfirmed());

        $approved = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_APPROVED,
        ]);
        $this->assertFalse($approved->isSelfConfirmed());

        $pending = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_PENDING,
        ]);
        $this->assertFalse($pending->isSelfConfirmed());
    }

    /**
     * Test isApproved method
     */
    public function testIsApproved(): void
    {
        $approved = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_APPROVED,
        ]);
        $this->assertTrue($approved->isApproved());

        $selfConfirmed = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_SELF_CONFIRMED,
        ]);
        $this->assertFalse($selfConfirmed->isApproved());

        $pending = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_PENDING,
        ]);
        $this->assertFalse($pending->isApproved());
    }

    /**
     * Test isPending method
     */
    public function testIsPending(): void
    {
        $pending = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_PENDING,
        ]);
        $this->assertTrue($pending->isPending());

        $approved = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_APPROVED,
        ]);
        $this->assertFalse($approved->isPending());
    }

    /**
     * Test isRevoked method
     */
    public function testIsRevoked(): void
    {
        $revoked = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_REVOKED,
        ]);
        $this->assertTrue($revoked->isRevoked());

        $approved = CertificationUser::factory()->create([
            'status' => CertificationUser::STATUS_APPROVED,
        ]);
        $this->assertFalse($approved->isRevoked());
    }

    /**
     * Test fillable attributes
     */
    public function testFillable(): void
    {
        $certificationUser = new CertificationUser();
        $fillable = $certificationUser->getFillable();

        $expectedFillable = [
            'user_id',
            'certification_id',
            'status',
            'date_completed',
            'date_expires',
            'notes',
            'certified_by',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    /**
     * Test casts
     */
    public function testCasts(): void
    {
        $certificationUser = new CertificationUser();
        $casts = $certificationUser->getCasts();

        $this->assertEquals('datetime', $casts['date_completed']);
        $this->assertEquals('datetime', $casts['date_expires']);
    }

    /**
     * Test model uses correct table
     */
    public function testTable(): void
    {
        $certificationUser = new CertificationUser();
        $this->assertEquals('certification_user', $certificationUser->getTable());
    }

    /**
     * Test primary key
     */
    public function testPrimaryKey(): void
    {
        $certificationUser = new CertificationUser();
        $this->assertEquals('id', $certificationUser->getKeyName());
    }

    /**
     * Test that it's a pivot model
     */
    public function testIsPivot(): void
    {
        $certificationUser = new CertificationUser();
        $this->assertTrue($certificationUser->getIncrementing());
    }

    /**
     * Test pivot attributes getter
     */
    public function testGetPivotAttributes(): void
    {
        $attributes = CertificationUser::getPivotAttributes();

        $expectedAttributes = [
            'status',
            'date_completed',
            'date_expires',
            'notes',
            'certified_by',
        ];

        $this->assertEquals($expectedAttributes, $attributes);
    }

    /**
     * Test status constants
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', CertificationUser::STATUS_PENDING);
        $this->assertEquals('approved', CertificationUser::STATUS_APPROVED);
        $this->assertEquals('self_confirmed', CertificationUser::STATUS_SELF_CONFIRMED);
        $this->assertEquals('revoked', CertificationUser::STATUS_REVOKED);
        $this->assertEquals('expired', CertificationUser::STATUS_EXPIRED);
    }
}
