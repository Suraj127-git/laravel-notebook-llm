<?php

namespace Database\Factories;

use App\Models\Notebook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notebook_id' => Notebook::factory(),
            'title' => $this->faker->sentence(3),
            'filename' => $this->faker->uuid().'_test.txt',
            'mime_type' => 'text/plain',
            'status' => 'ready',
            'content' => $this->faker->paragraphs(3, true),
        ];
    }
}
