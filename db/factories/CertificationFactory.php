<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace Database\Factories\Engelsystem\Models;

use Engelsystem\Models\Certification;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationFactory extends Factory
{
    /** @var string */
    protected $model = Certification::class; // phpcs:ignore

    public function definition(): array
    {
        $certificationTypes = [
            'First Aid',
            'CPR Certification',
            'Fire Safety Training',
            'Food Hygiene Certificate',
            'Driving License B',
            'Driving License C',
            'Forklift Operation License',
            'Electrical Safety Training',
            'Health & Safety Induction',
            'Data Protection Training',
            'Manual Handling Training',
            'Working at Height Certification',
        ];

        $locations = [
            'Main Training Room',
            'Conference Hall A',
            'Safety Training Center',
            'Online Platform',
            'External Training Provider',
            'Headquarters Building 1',
            'Workshop Area',
            null, // Some certifications may not have a specific location
        ];

        $contactPersons = [
            'Sarah Miller',
            'John Smith',
            'Dr. Emily Johnson',
            'Mark Thompson',
            'Lisa Anderson',
            'Michael Brown',
            null, // Some certifications may not have a contact person
        ];

        $title = $this->faker->randomElement($certificationTypes);
        $isPerpetual = $this->faker->boolean(30); // 30% chance of being perpetual

        return [
            'title' => $title,
            'description' => $this->generateDescription($title),
            'contact_person' => $this->faker->randomElement($contactPersons),
            'contact_email' => $this->faker->optional(0.8)->email(), // 80% chance of having email
            'location' => $this->faker->randomElement($locations),
            'is_perpetual' => $isPerpetual,
            'validity_period_days' => $isPerpetual ? null : $this->faker->randomElement([30, 90, 180, 365, 730, 1095]), // 1 month to 3 years
            'allow_self_confirmation' => $this->faker->boolean(25), // 25% chance of allowing self-confirmation
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Generate a realistic description based on certification title.
     */
    private function generateDescription(string $title): string
    {
        $descriptions = [
            'First Aid' => 'Basic first aid training covering emergency response, wound care, and life-saving techniques. Includes practical demonstrations and certification upon completion.',
            'CPR Certification' => 'Cardiopulmonary resuscitation training for adults, children, and infants. Covers chest compressions, rescue breathing, and AED usage.',
            'Fire Safety Training' => 'Comprehensive fire safety awareness including evacuation procedures, fire extinguisher usage, and emergency response protocols.',
            'Food Hygiene Certificate' => 'Essential food safety and hygiene training covering HACCP principles, temperature control, and contamination prevention.',
            'Driving License B' => 'Standard driving license for passenger vehicles up to 3.5 tonnes. Valid government-issued driving permit required.',
            'Driving License C' => 'Commercial driving license for vehicles over 3.5 tonnes. Additional training and testing required for heavy goods vehicles.',
            'Forklift Operation License' => 'Certified training for safe forklift operation including pre-use checks, load handling, and workplace safety protocols.',
            'Electrical Safety Training' => 'Safety procedures for working with electrical equipment and installations. Covers risk assessment and protective measures.',
            'Health & Safety Induction' => 'General workplace health and safety orientation covering risk assessment, accident reporting, and safety procedures.',
            'Data Protection Training' => 'GDPR compliance training covering data handling, privacy rights, and information security best practices.',
            'Manual Handling Training' => 'Safe lifting and handling techniques to prevent workplace injuries. Includes risk assessment and ergonomic principles.',
            'Working at Height Certification' => 'Safety training for work at elevated positions including fall protection, equipment inspection, and rescue procedures.',
        ];

        return $descriptions[$title] ?? $this->faker->paragraph(3);
    }

    /**
     * Create a perpetual certification.
     */
    public function perpetual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_perpetual' => true,
            'validity_period_days' => null,
        ]);
    }

    /**
     * Create a time-limited certification.
     */
    public function timeLimited(int $days = 365): static
    {
        return $this->state(fn (array $attributes) => [
            'is_perpetual' => false,
            'validity_period_days' => $days,
        ]);
    }

    /**
     * Create a self-confirmable certification.
     */
    public function selfConfirmable(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_self_confirmation' => true,
        ]);
    }

    /**
     * Create an inactive certification.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a certification with all contact details.
     */
    public function withFullContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_person' => $this->faker->name(),
            'contact_email' => $this->faker->email(),
            'location' => $this->faker->randomElement([
                'Main Training Room',
                'Conference Hall A',
                'Safety Training Center',
                'Headquarters Building 1',
            ]),
        ]);
    }
}
