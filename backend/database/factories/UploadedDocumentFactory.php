<?php

namespace Database\Factories;

use App\Models\UploadedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UploadedDocumentFactory extends Factory
{
    protected $model = UploadedDocument::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => $this->faker->word() . '.pdf',
            'stored_filename' => $this->faker->uuid() . '.pdf',
            'file_path' => 'uploads/users/' . $this->faker->numberBetween(1, 100) . '/' . $this->faker->uuid() . '.pdf',
            'mime_type' => $this->faker->randomElement([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'text/plain',
            ]),
            'file_size' => $this->faker->numberBetween(1024, 5 * 1024 * 1024), // 1KB to 5MB
            'file_hash' => hash('sha256', $this->faker->text()),
            'document_type' => $this->faker->randomElement(['document', 'image', 'spreadsheet', 'text']),
            'is_verified' => $this->faker->boolean(),
            'verified_at' => $this->faker->optional()->dateTime(),
            'verified_by' => $this->faker->optional()->numberBetween(1, 10),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => User::factory(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_filename' => $this->faker->word() . '.pdf',
            'stored_filename' => $this->faker->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'document_type' => 'document',
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_filename' => $this->faker->word() . '.jpg',
            'stored_filename' => $this->faker->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'document_type' => 'image',
        ]);
    }

    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(1024, 100 * 1024), // 1KB to 100KB
        ]);
    }

    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(1024 * 1024, 5 * 1024 * 1024), // 1MB to 5MB
        ]);
    }
}