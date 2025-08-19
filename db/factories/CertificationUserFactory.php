<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace Database\Factories\Engelsystem\Models;

use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class CertificationUserFactory extends Factory
{
    /** @var string */
    protected $model = CertificationUser::class; // phpcs:ignore

    public function definition(): array
    {
        $status = $this->faker->randomElement([
            CertificationUser::STATUS_PENDING,
            CertificationUser::STATUS_APPROVED,
            CertificationUser::STATUS_SELF_CONFIRMED,
            CertificationUser::STATUS_REVOKED,
            CertificationUser::STATUS_EXPIRED,
        ]);

        $dateCompleted = $this->faker->optional(0.8)->dateTimeBetween('-2 years', 'now');
        $dateExpires = null;

        // Calculate expiry date based on completion date and random validity period
        if ($dateCompleted && $this->faker->boolean(70)) { // 70% chance of having expiry
            $validityDays = $this->faker->randomElement([30, 90, 180, 365, 730, 1095]);
            $dateExpires = Carbon::parse($dateCompleted)->addDays($validityDays);

            // If expiry is in the past and status is approved/self_confirmed, change to expired
            if ($dateExpires->isPast() && in_array($status, [CertificationUser::STATUS_APPROVED, CertificationUser::STATUS_SELF_CONFIRMED])) {
                $status = CertificationUser::STATUS_EXPIRED;
            }
        }

        return [
            'user_id' => User::factory(),
            'certification_id' => Certification::factory(),
            'status' => $status,
            'date_completed' => $dateCompleted,
            'date_expires' => $dateExpires,
            'notes' => $this->faker->optional(0.3)->sentence(), // 30% chance of having notes
        ];
    }

    /**
     * Create a pending certification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationUser::STATUS_PENDING,
            'date_completed' => null,
            'date_expires' => null,
        ]);
    }

    /**
     * Create an approved certification.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationUser::STATUS_APPROVED,
            'date_completed' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Create a self-confirmed certification.
     */
    public function selfConfirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationUser::STATUS_SELF_CONFIRMED,
            'date_completed' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Create a revoked certification.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationUser::STATUS_REVOKED,
            'date_completed' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
            'notes' => 'Revoked due to policy changes',
        ]);
    }

    /**
     * Create an expired certification.
     */
    public function expired(): static
    {
        $dateCompleted = $this->faker->dateTimeBetween('-3 years', '-1 year');
        $dateExpires = Carbon::parse($dateCompleted)->addYear()->subDay(); // Expired yesterday

        return $this->state(fn (array $attributes) => [
            'status' => CertificationUser::STATUS_EXPIRED,
            'date_completed' => $dateCompleted,
            'date_expires' => $dateExpires,
        ]);
    }

    /**
     * Create a valid (non-expired) certification.
     */
    public function valid(): static
    {
        $dateCompleted = $this->faker->dateTimeBetween('-6 months', 'now');
        $dateExpires = Carbon::parse($dateCompleted)->addYear(); // Valid for another year

        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement([
                CertificationUser::STATUS_APPROVED,
                CertificationUser::STATUS_SELF_CONFIRMED,
            ]),
            'date_completed' => $dateCompleted,
            'date_expires' => $dateExpires,
        ]);
    }

    /**
     * Create a certification expiring soon.
     */
    public function expiringSoon(int $days = 7): static
    {
        $dateCompleted = $this->faker->dateTimeBetween('-11 months', '-10 months');
        $dateExpires = Carbon::now()->addDays($days); // Expires in specified days

        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement([
                CertificationUser::STATUS_APPROVED,
                CertificationUser::STATUS_SELF_CONFIRMED,
            ]),
            'date_completed' => $dateCompleted,
            'date_expires' => $dateExpires,
        ]);
    }

    /**
     * Create a perpetual certification (never expires).
     */
    public function perpetual(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement([
                CertificationUser::STATUS_APPROVED,
                CertificationUser::STATUS_SELF_CONFIRMED,
            ]),
            'date_completed' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'date_expires' => null,
        ]);
    }

    /**
     * Create a certification with notes.
     */
    public function withNotes(string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes ?? $this->faker->paragraph(),
        ]);
    }
}
