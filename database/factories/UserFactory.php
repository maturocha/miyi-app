<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $gender = $this->faker->randomElement(['female', 'male']);

        $firstName = $gender === 'female' 
            ? $this->faker->firstNameFemale 
            : $this->faker->firstNameMale;
        
        $middleName = $this->faker->lastName;
        $lastName = $this->faker->lastName;

        return [
            'firstname' => $firstName,
            'middlename' => $middleName,
            'lastname' => $lastName,
            'gender' => $gender,
            'birthdate' => $this->faker->dateTimeThisCentury->format('Y-m-d'),
            'address' => $this->faker->address,
            'type' => $this->faker->randomElement(['superuser', 'user']),
            'name' => "{$firstName} {$middleName} {$lastName}",
            'username' => $this->faker->userName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('secret'),
            'remember_token' => Str::random(10),
        ];
    }
}
