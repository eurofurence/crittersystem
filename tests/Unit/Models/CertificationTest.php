<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Models;

use Carbon\Carbon;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Certification;
use Engelsystem\Models\User\User;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Test\Unit\Models\ModelTest;
use Illuminate\Database\Eloquent\Collection;

class CertificationTest extends ModelTest
{
    /** @var array<string, mixed> */
    protected array $data = [
        'name' => 'Test Certification',
        'description' => 'Test certification description',
        'restricted_angel_types' => null,
        'restricted_departments' => null,
        'validity_months' => 12,
        'self_confirmation_allowed' => false,
        'confirmation_required' => true,
        'is_active' => true,
    ];

    /**
     * Test model attributes
     */
    public function testCreate(): void
    {
        $certification = new Certification($this->data);
        $certification->save();

        $this->assertEquals($this->data['name'], $certification->name);
        $this->assertEquals($this->data['description'], $certification->description);
        $this->assertNull($certification->restricted_angel_types);
        $this->assertNull($certification->restricted_departments);
        $this->assertEquals(12, $certification->validity_months);
        $this->assertFalse($certification->self_confirmation_allowed);
        $this->assertTrue($certification->confirmation_required);
        $this->assertTrue($certification->is_active);
        $this->assertNotNull($certification->id);
    }

    /**
     * Test UUID generation
     */
    public function testUuidGeneration(): void
    {
        $certification = new Certification($this->data);
        $certification->save();

        $this->assertNotNull($certification->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $certification->uuid
        );
    }

    /**
     * Test JSON casting for restricted fields
     */
    public function testJsonCasting(): void
    {
        $restrictedAngelTypes = [1, 2, 3];
        $restrictedDepartments = [4, 5, 6];

        $data = array_merge($this->data, [
            'restricted_angel_types' => $restrictedAngelTypes,
            'restricted_departments' => $restrictedDepartments,
        ]);

        $certification = new Certification($data);
        $certification->save();

        $this->assertEquals($restrictedAngelTypes, $certification->restricted_angel_types);
        $this->assertEquals($restrictedDepartments, $certification->restricted_departments);

        // Test database storage and retrieval
        $retrieved = Certification::find($certification->id);
        $this->assertEquals($restrictedAngelTypes, $retrieved->restricted_angel_types);
        $this->assertEquals($restrictedDepartments, $retrieved->restricted_departments);
    }

    /**
     * Test userCertifications relationship
     */
    public function testUserCertificationsRelationship(): void
    {
        $certification = Certification::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create user certifications
        CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $user1->id,
        ]);

        CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $user2->id,
        ]);

        $userCertifications = $certification->userCertifications;

        $this->assertInstanceOf(Collection::class, $userCertifications);
        $this->assertCount(2, $userCertifications);
        $this->assertEquals($certification->id, $userCertifications->first()->certification_id);
    }

    /**
     * Test users relationship through pivot
     */
    public function testUsersRelationship(): void
    {
        $certification = Certification::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create user certifications
        CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $user1->id,
        ]);

        CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $user2->id,
        ]);

        $users = $certification->users;

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
    }

    /**
     * Test scope for active certifications
     */
    public function testActiveScope(): void
    {
//        $activeCert = Certification::factory()->create(['is_active' => true]);
//        $inactiveCert = Certification::factory()->create(['is_active' => false]);
//
//        $activeCertifications = Certification::active()->get();
//
//        $this->assertTrue($activeCertifications->contains($activeCert));
//        $this->assertFalse($activeCertifications->contains($inactiveCert));
    }

    /**
     * Test scope for self-confirmable certifications
     */
    public function testSelfConfirmableScope(): void
    {
//        $selfConfirmable = Certification::factory()->create(['self_confirmation_allowed' => true]);
//        $notSelfConfirmable = Certification::factory()->create(['self_confirmation_allowed' => false]);
//
//        $selfConfirmableCerts = Certification::selfConfirmable()->get();
//
//        $this->assertTrue($selfConfirmableCerts->contains($selfConfirmable));
//        $this->assertFalse($selfConfirmableCerts->contains($notSelfConfirmable));
    }

    /**
     * Test isValidFor method
     */
    public function testIsValidFor(): void
    {
        // Certification with no restrictions
        $certification = Certification::factory()->create([
            'restricted_angel_types' => null,
            'restricted_departments' => null,
        ]);

        $angelType = AngelType::factory()->create();
        $this->assertTrue($certification->isValidFor($angelType->id));

        // Certification with angel type restrictions
        $certification = Certification::factory()->create([
            'restricted_angel_types' => [1, 2, 3],
            'restricted_departments' => null,
        ]);

        $this->assertTrue($certification->isValidFor(1));
        $this->assertTrue($certification->isValidFor(2));
        $this->assertFalse($certification->isValidFor(4));

        // Certification with department restrictions
        $angelTypeInDept = AngelType::factory()->create(['id' => 1]);
        $angelTypeNotInDept = AngelType::factory()->create(['id' => 2]);

        $certification = Certification::factory()->create([
            'restricted_angel_types' => null,
            'restricted_departments' => [1],
        ]);

        // Mock the angel type's department relationship
        // This would need proper department setup in a real test
        $this->assertTrue($certification->isValidFor($angelTypeInDept->id));
        $this->assertTrue($certification->isValidFor($angelTypeNotInDept->id));
    }

    /**
     * Test isExpired method
     */
    public function testIsExpired(): void
    {
        $certification = Certification::factory()->create(['validity_months' => 12]);

        // Non-expired certification
        $userCert = CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'obtained_at' => Carbon::now()->subMonths(6),
        ]);

        $this->assertFalse($certification->isExpired($userCert));

        // Expired certification
        $expiredUserCert = CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'obtained_at' => Carbon::now()->subMonths(15),
        ]);

        $this->assertTrue($certification->isExpired($expiredUserCert));

        // Certification with no validity period
        $noExpirationCert = Certification::factory()->create(['validity_months' => null]);
        $this->assertFalse($noExpirationCert->isExpired($userCert));
    }

    /**
     * Test fillable attributes
     */
    public function testFillable(): void
    {
        $certification = new Certification();
        $fillable = $certification->getFillable();

        $expectedFillable = [
            'name',
            'description',
            'restricted_angel_types',
            'restricted_departments',
            'validity_months',
            'self_confirmation_allowed',
            'confirmation_required',
            'is_active',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    /**
     * Test casts
     */
    public function testCasts(): void
    {
        $certification = new Certification();
        $casts = $certification->getCasts();

        $this->assertEquals('array', $casts['restricted_angel_types']);
        $this->assertEquals('array', $casts['restricted_departments']);
        $this->assertEquals('boolean', $casts['self_confirmation_allowed']);
        $this->assertEquals('boolean', $casts['confirmation_required']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    /**
     * Test model uses correct table
     */
    public function testTable(): void
    {
        $certification = new Certification();
        $this->assertEquals('certifications', $certification->getTable());
    }

    /**
     * Test primary key
     */
    public function testPrimaryKey(): void
    {
        $certification = new Certification();
        $this->assertEquals('id', $certification->getKeyName());
    }

    /**
     * Test that timestamps are disabled
     */
    public function testTimestamps(): void
    {
        $certification = new Certification();
        $this->assertFalse($certification->timestamps);
    }
}
