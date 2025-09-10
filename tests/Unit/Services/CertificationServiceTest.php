<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Services;

use Carbon\Carbon;
use Engelsystem\Config\Config;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Engelsystem\Services\CertificationService;
use Engelsystem\Test\Unit\HasDatabase;
use Engelsystem\Test\Unit\TestCase;
use Exception;
use Psr\Log\LoggerInterface;

class CertificationServiceTest extends TestCase
{
    use HasDatabase;

    protected CertificationService $service;
    protected LoggerInterface $logger;
    protected Config $config;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initDatabase();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = new Config([
            'certification' => [
                'default_validity_months' => 12,
            ],
        ]);

        $this->app->instance(Config::class, $this->config);
        $this->service = new CertificationService($this->logger);
    }

    /**
     * Test getAllCertifications method
     */
    public function testGetAllCertifications(): void
    {
        $cert1 = Certification::factory()->create(['name' => 'First Aid']);
        $cert2 = Certification::factory()->create(['name' => 'Safety']);
        $cert3 = Certification::factory()->create(['name' => 'Driver', 'is_active' => false]);

        // Get all certifications
        $allCerts = $this->service->getAllCertifications();
        $this->assertCount(3, $allCerts);

        // Get only active certifications
        $activeCerts = $this->service->getAllCertifications(['active' => true]);
        $this->assertCount(2, $activeCerts);
        $this->assertTrue($activeCerts->contains($cert1));
        $this->assertTrue($activeCerts->contains($cert2));
        $this->assertFalse($activeCerts->contains($cert3));

        // Test search filter
        $searchResults = $this->service->getAllCertifications(['search' => 'First']);
        $this->assertCount(1, $searchResults);
        $this->assertTrue($searchResults->contains($cert1));
    }

    /**
     * Test createCertification method
     */
    public function testCreateCertification(): void
    {
        $data = [
            'name' => 'Test Certification',
            'description' => 'Test Description',
            'validity_months' => 24,
            'self_confirmation_allowed' => true,
            'confirmation_required' => false,
            'is_active' => true,
        ];

        $certification = $this->service->createCertification($data);

        $this->assertEquals($data['name'], $certification->name);
        $this->assertEquals($data['description'], $certification->description);
        $this->assertEquals($data['validity_months'], $certification->validity_months);
        $this->assertTrue($certification->self_confirmation_allowed);
        $this->assertFalse($certification->confirmation_required);
        $this->assertTrue($certification->is_active);
        $this->assertNotNull($certification->uuid);
    }

    /**
     * Test updateCertification method
     */
    public function testUpdateCertification(): void
    {
        $certification = Certification::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'validity_months' => 18,
        ];

        $updatedCertification = $this->service->updateCertification($certification, $updateData);

        $this->assertEquals($updateData['name'], $updatedCertification->name);
        $this->assertEquals($updateData['description'], $updatedCertification->description);
        $this->assertEquals($updateData['validity_months'], $updatedCertification->validity_months);
    }

    /**
     * Test deleteCertification method
     */
    public function testDeleteCertification(): void
    {
        $certification = Certification::factory()->create();
        $certificationId = $certification->id;

        $result = $this->service->deleteCertification($certification);

        $this->assertTrue($result);
        $this->assertNull(Certification::find($certificationId));
    }

    /**
     * Test deleteCertification fails when users have certification
     */
    public function testDeleteCertificationFailsWhenUsersHaveCertification(): void
    {
        $certification = Certification::factory()->create();
        $user = User::factory()->create();

        // Create user certification
        CertificationUser::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete certification that is assigned to users');

        $this->service->deleteCertification($certification);
    }

    /**
     * Test getUserCertifications method
     */
    public function testGetUserCertifications(): void
    {
        $user = User::factory()->create();
        $cert1 = Certification::factory()->create(['name' => 'First Aid']);
        $cert2 = Certification::factory()->create(['name' => 'Safety']);

        // Create user certifications
        CertificationUser::factory()->approved()->create([
            'user_id' => $user->id,
            'certification_id' => $cert1->id,
        ]);

        CertificationUser::factory()->pending()->create([
            'user_id' => $user->id,
            'certification_id' => $cert2->id,
        ]);

        // Get all user certifications
        $allUserCerts = $this->service->getUserCertifications($user);
        $this->assertCount(2, $allUserCerts);

        // Get only approved certifications
        $approvedCerts = $this->service->getUserCertifications($user, ['status' => 'approved']);
        $this->assertCount(1, $approvedCerts);

        // Get only pending certifications
        $pendingCerts = $this->service->getUserCertifications($user, ['status' => 'pending']);
        $this->assertCount(1, $pendingCerts);
    }

    /**
     * Test addCertificationToUser method
     */
    public function testAddCertificationToUser(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create();

        $userCertification = $this->service->addCertificationToUser(
            $user,
            $certification,
            CertificationUser::STATUS_APPROVED,
            null,
            'Test notes',
            Carbon::now()
        );

        $this->assertEquals($user->id, $userCertification->user_id);
        $this->assertEquals($certification->id, $userCertification->certification_id);
        $this->assertEquals(CertificationUser::STATUS_APPROVED, $userCertification->status);
        $this->assertEquals('Test notes', $userCertification->notes);
        $this->assertNotNull($userCertification->date_expires);
    }

    /**
     * Test addCertificationToUser fails for duplicate
     */
    public function testAddCertificationToUserFailsForDuplicate(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create();

        // Create existing certification
        CertificationUser::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User already has this certification');

        $this->service->addCertificationToUser($user, $certification, CertificationUser::STATUS_PENDING);
    }

    /**
     * Test updateUserCertification method
     */
    public function testUpdateUserCertification(): void
    {
        $userCertification = CertificationUser::factory()->pending()->create();

        $updateData = [
            'status' => CertificationUser::STATUS_APPROVED,
            'notes' => 'Updated notes',
            'certified_by' => 1,
        ];

        $updated = $this->service->updateUserCertification($userCertification, $updateData);

        $this->assertEquals(CertificationUser::STATUS_APPROVED, $updated->status);
        $this->assertEquals('Updated notes', $updated->notes);
        $this->assertEquals(1, $updated->certified_by);
    }

    /**
     * Test removeCertificationFromUser method
     */
    public function testRemoveCertificationFromUser(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create();

        $userCertification = CertificationUser::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
        ]);

        $result = $this->service->removeCertificationFromUser($user, $certification);

        $this->assertTrue($result);
        $this->assertNull(CertificationUser::find($userCertification->id));
    }

    /**
     * Test removeCertificationFromUser fails when not found
     */
    public function testRemoveCertificationFromUserFailsWhenNotFound(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User does not have this certification');

        $this->service->removeCertificationFromUser($user, $certification);
    }

    /**
     * Test selfConfirmCertification method
     */
    public function testSelfConfirmCertification(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create([
            'self_confirmation_allowed' => true,
        ]);

        $userCertification = $this->service->selfConfirmCertification($user, $certification);

        $this->assertEquals($user->id, $userCertification->user_id);
        $this->assertEquals($certification->id, $userCertification->certification_id);
        $this->assertEquals(CertificationUser::STATUS_SELF_CONFIRMED, $userCertification->status);
        $this->assertNotNull($userCertification->date_completed);
    }

    /**
     * Test selfConfirmCertification fails when not allowed
     */
    public function testSelfConfirmCertificationFailsWhenNotAllowed(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create([
            'self_confirmation_allowed' => false,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Self-confirmation is not allowed for this certification');

        $this->service->selfConfirmCertification($user, $certification);
    }

    /**
     * Test automatic expiry date calculation
     */
    public function testAutomaticExpiryDateCalculation(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create(['validity_months' => 24]);

        $completionDate = Carbon::now();
        $expectedExpiry = $completionDate->copy()->addMonths(24);

        $userCertification = $this->service->addCertificationToUser(
            $user,
            $certification,
            CertificationUser::STATUS_APPROVED,
            null,
            null,
            $completionDate
        );

        $this->assertTrue($expectedExpiry->equalTo($userCertification->date_expires));
    }

    /**
     * Test no expiry for certifications without validity period
     */
    public function testNoExpiryForCertificationsWithoutValidityPeriod(): void
    {
        $user = User::factory()->create();
        $certification = Certification::factory()->create(['validity_months' => null]);

        $userCertification = $this->service->addCertificationToUser(
            $user,
            $certification,
            CertificationUser::STATUS_APPROVED,
            null,
            null,
            Carbon::now()
        );

        $this->assertNull($userCertification->date_expires);
    }

    /**
     * Test checkUserCertificationRequirements method
     */
    public function testCheckUserCertificationRequirements(): void
    {
        $user = User::factory()->create();
        $angelType = AngelType::factory()->create();

        // For this test, just verify the method exists and returns an array
        $result = $this->service->checkUserCertificationRequirements($user, $angelType);

        $this->assertIsArray($result);
    }
}
