<?php

declare(strict_types=1);

namespace Engelsystem\Test\Feature\Controllers\Admin;

use Engelsystem\Application;
use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Engelsystem\Services\CertificationService;
use Engelsystem\Test\Feature\ApplicationFeatureTest;
use Exception;

/**
 * @group certification-feature-tests
 */
final class CertificationsControllerFeatureTest extends ApplicationFeatureTest
{
    private Application $app;
    private CertificationService $certificationService;
    private array $modelsToBeDeleted = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->app = app();
        $this->certificationService = $this->app->get(CertificationService::class);
    }

    protected function tearDown(): void
    {
        // Clean up created models
        foreach (array_reverse($this->modelsToBeDeleted) as $model) {
            try {
                $model->delete();
            } catch (Exception) {
                // Ignore deletion errors during cleanup
            }
        }

        parent::tearDown();
    }

    /**
     * Test complete certification CRUD workflow
     */
    public function testCertificationCrudWorkflow(): void
    {
        // Create certification
        $certificationData = [
            'name' => 'Test Safety Certification',
            'description' => 'Comprehensive safety training certification',
            'validity_months' => 24,
            'self_confirmation_allowed' => true,
            'confirmation_required' => false,
            'is_active' => true,
            'restricted_angel_types' => [1, 2],
            'restricted_departments' => [3, 4],
        ];

        $certification = $this->certificationService->createCertification($certificationData);
        $this->modelsToBeDeleted[] = $certification;

        // Verify creation
        $this->assertEquals('Test Safety Certification', $certification->name);
        $this->assertEquals(24, $certification->validity_months);
        $this->assertTrue($certification->self_confirmation_allowed);
        $this->assertEquals([1, 2], $certification->restricted_angel_types);
        $this->assertEquals([3, 4], $certification->restricted_departments);

        // Update certification
        $updateData = [
            'name' => 'Updated Safety Certification',
            'validity_months' => 36,
            'self_confirmation_allowed' => false,
        ];

        $updatedCertification = $this->certificationService->updateCertification($certification, $updateData);

        // Verify update
        $this->assertEquals('Updated Safety Certification', $updatedCertification->name);
        $this->assertEquals(36, $updatedCertification->validity_months);
        $this->assertFalse($updatedCertification->self_confirmation_allowed);

        // Test deletion (should succeed since no users assigned)
        $result = $this->certificationService->deleteCertification($certification);
        $this->assertTrue($result);

        // Remove from cleanup list since already deleted
        $this->modelsToBeDeleted = array_filter($this->modelsToBeDeleted, fn($m) => $m !== $certification);
    }

    /**
     * Test user certification assignment workflow
     */
    public function testUserCertificationWorkflow(): void
    {
        // Create certification and user
        $certification = Certification::factory()->create(['name' => 'First Aid Certification']);
        $user = User::factory()->create(['name' => 'John Doe']);

        $this->modelsToBeDeleted[] = $certification;
        $this->modelsToBeDeleted[] = $user;

        // Assign certification to user
        $userCertification = $this->certificationService->addCertificationToUser(
            $user,
            $certification,
            'approved',
            null,
            'Successfully completed training'
        );

        $this->modelsToBeDeleted[] = $userCertification;

        // Verify assignment
        $this->assertNotNull($userCertification);
        $this->assertEquals($user->id, $userCertification->user_id);
        $this->assertEquals($certification->id, $userCertification->certification_id);
        $this->assertEquals('approved', $userCertification->status);
        $this->assertEquals('Successfully completed training', $userCertification->notes);

        // Test getting user certifications
        $userCertifications = $this->certificationService->getUserCertifications($user);
        $this->assertCount(1, $userCertifications);
        $this->assertEquals($certification->id, $userCertifications->first()->certification_id);

        // Update certification status
        $updateData = [
            'status' => 'revoked',
            'notes' => 'Revoked due to policy violation',
        ];

        $updatedCertification = $this->certificationService->updateUserCertification($userCertification, $updateData);
        $this->assertEquals('revoked', $updatedCertification->status);
        $this->assertStringContainsString('Revoked due to policy violation', $updatedCertification->notes);

        // Remove certification from user
        $result = $this->certificationService->removeCertificationFromUser($user, $certification);
        $this->assertTrue($result);

        // Remove from cleanup list since already deleted
        $this->modelsToBeDeleted = array_filter($this->modelsToBeDeleted, fn($m) => $m !== $userCertification);
    }

    /**
     * Test self-confirmation workflow
     */
    public function testSelfConfirmationWorkflow(): void
    {
        // Create self-confirmable certification
        $certification = Certification::factory()->create([
            'name' => 'Basic Safety',
            'self_confirmation_allowed' => true,
        ]);

        $user = User::factory()->create(['name' => 'Self Confirming User']);

        $this->modelsToBeDeleted[] = $certification;
        $this->modelsToBeDeleted[] = $user;

        // Self-confirm certification
        $userCertification = $this->certificationService->selfConfirmCertification($user, $certification);

        $this->modelsToBeDeleted[] = $userCertification;

        // Verify self-confirmation
        $this->assertEquals($user->id, $userCertification->user_id);
        $this->assertEquals($certification->id, $userCertification->certification_id);
        $this->assertEquals('self_confirmed', $userCertification->status);
        $this->assertNotNull($userCertification->date_completed);

        // Test self-confirmation failure for non-self-confirmable certification
        $nonSelfConfirmCertification = Certification::factory()->create([
            'name' => 'Advanced Safety',
            'self_confirmation_allowed' => false,
        ]);
        $this->modelsToBeDeleted[] = $nonSelfConfirmCertification;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Self-confirmation is not allowed for this certification');

        $this->certificationService->selfConfirmCertification($user, $nonSelfConfirmCertification);
    }

    /**
     * Test certification expiry handling
     */
    public function testCertificationExpiryHandling(): void
    {
        // Create certification with validity period
        $certification = Certification::factory()->create([
            'name' => 'Expiring Certification',
            'validity_months' => 12,
        ]);

        $user = User::factory()->create(['name' => 'Test User']);

        $this->modelsToBeDeleted[] = $certification;
        $this->modelsToBeDeleted[] = $user;

        // Create non-expired certification
        $validCertification = $this->certificationService->addCertificationToUser(
            $user,
            $certification,
            CertificationUser::STATUS_APPROVED,
            null,
            'Valid certification'
        );

        $this->modelsToBeDeleted[] = $validCertification;

        // Verify expiry calculation
        $this->assertNotNull($validCertification->date_expires);
        $expectedExpiry = $validCertification->date_completed->copy()->addMonths(12);
        $this->assertTrue($expectedExpiry->equalTo($validCertification->date_expires));

        // Test certification without validity period
        $perpetualCertification = Certification::factory()->create([
            'name' => 'Perpetual Certification',
            'validity_months' => null,
        ]);
        $this->modelsToBeDeleted[] = $perpetualCertification;

        $perpetualUserCert = $this->certificationService->addCertificationToUser(
            $user,
            $perpetualCertification,
            'approved'
        );
        $this->modelsToBeDeleted[] = $perpetualUserCert;

        $this->assertNull($perpetualUserCert->date_expires);
    }

    /**
     * Test mass revocation workflow
     */
    public function testMassRevocationWorkflow(): void
    {
        // Create certification and multiple user assignments
        $certification = Certification::factory()->create(['name' => 'Revocation Test']);
        $users = User::factory()->count(3)->create();

        $userCertifications = [];
        foreach ($users as $user) {
            $userCertifications[] = $this->certificationService->addCertificationToUser(
                $user,
                $certification,
                'approved',
                null,
                'Initial certification'
            );
        }

        $this->modelsToBeDeleted = array_merge($this->modelsToBeDeleted, $userCertifications);
        $this->modelsToBeDeleted = array_merge($this->modelsToBeDeleted, $users->all());
        $this->modelsToBeDeleted[] = $certification;

        // Perform mass revocation
        $reason = 'Safety protocol violation';
        $revokedCount = $this->certificationService->massRevokeCertification($certification, $reason);

        // Verify revocation
        $this->assertEquals(3, $revokedCount);

        foreach ($userCertifications as $userCert) {
            $userCert->refresh();
            $this->assertEquals('revoked', $userCert->status);
            $this->assertStringContainsString($reason, $userCert->notes);
        }
    }

    /**
     * Test error handling for duplicate certifications
     */
    public function testDuplicateCertificationErrorHandling(): void
    {
        $certification = Certification::factory()->create(['name' => 'Duplicate Test']);
        $user = User::factory()->create(['name' => 'Test User']);

        $this->modelsToBeDeleted[] = $certification;
        $this->modelsToBeDeleted[] = $user;

        // Add initial certification
        $userCertification = $this->certificationService->addCertificationToUser(
            $user,
            $certification,
            'approved'
        );
        $this->modelsToBeDeleted[] = $userCertification;

        // Attempt to add duplicate certification
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User already has this certification');

        $this->certificationService->addCertificationToUser(
            $user,
            $certification,
            'pending'
        );
    }

    /**
     * Test certification deletion with assigned users
     */
    public function testCertificationDeletionErrorHandling(): void
    {
        // Create certification with assigned users
        $certification = Certification::factory()->create(['name' => 'Assigned Certification']);
        $user = User::factory()->create(['name' => 'Test User']);

        $userCertification = $this->certificationService->addCertificationToUser(
            $user,
            $certification,
            'approved'
        );

        $this->modelsToBeDeleted[] = $userCertification;
        $this->modelsToBeDeleted[] = $user;
        $this->modelsToBeDeleted[] = $certification;

        // Attempt to delete certification with assigned users
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete certification that is assigned to users');

        $this->certificationService->deleteCertification($certification);
    }

    /**
     * Test filtering and search functionality
     */
    public function testFilteringAndSearchFunctionality(): void
    {
        // Create test certifications
        $activeCert1 = Certification::factory()->create([
            'name' => 'Active First Aid',
            'is_active' => true,
        ]);

        $activeCert2 = Certification::factory()->create([
            'name' => 'Active Safety',
            'is_active' => true,
        ]);

        $inactiveCert = Certification::factory()->create([
            'name' => 'Inactive Training',
            'is_active' => false,
        ]);

        $this->modelsToBeDeleted[] = $activeCert1;
        $this->modelsToBeDeleted[] = $activeCert2;
        $this->modelsToBeDeleted[] = $inactiveCert;

        // Test getting all certifications
        $allCertifications = $this->certificationService->getAllCertifications();
        $this->assertGreaterThanOrEqual(3, $allCertifications->count());

        // Test filtering by active status
        $activeCertifications = $this->certificationService->getAllCertifications(['active' => true]);
        $activeCertificationNames = $activeCertifications->pluck('name')->toArray();

        $this->assertContains('Active First Aid', $activeCertificationNames);
        $this->assertContains('Active Safety', $activeCertificationNames);
        $this->assertNotContains('Inactive Training', $activeCertificationNames);

        // Test search functionality
        $searchResults = $this->certificationService->getAllCertifications(['search' => 'First']);
        $this->assertGreaterThanOrEqual(1, $searchResults->count());

        $searchResultNames = $searchResults->pluck('name')->toArray();
        $this->assertContains('Active First Aid', $searchResultNames);
    }
}
