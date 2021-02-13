<?php namespace Kanban\Custom\Updates;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Models\Team;
use RainLab\User\Models\User;
use Seeder;
use Faker;

class Seeder1020 extends Seeder
{
    public function run()
    {
        $faker = Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            Team::forceCreate([
                'name' => ucfirst($faker->words(2, true)) . " Team",
                'avatar' => null,
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            $password = Hash::make('12345678');

            User::forceCreate([
                'team_id'               => $faker->numberBetween(1, 2),
                'name'                  => $faker->firstName,
                'surname'               => $faker->lastName,
                'email'                 => $faker->unique()->safeEmail,
                'is_activated'          => 1,
                'activated_at'          => now(),
                'password'              => $password,
                'password_confirmation' => $password,
                'picture'               => 'https://i.pravatar.cc/60?img=' . $faker->numberBetween(1, 70),
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            $title = $faker->word;
            $isActive = $faker->boolean($chanceOfGettingTrue = 50);

            Project::forceCreate([
                'team_id'        => $faker->numberBetween(1, 2),
                'title'          => "Project " . ucfirst($title),
                'slug'           => Str::slug($title),
                'description'    => $faker->sentence(10),
                'picture'        => "logos/logo.png",
                'client_name'    => $faker->name(),
                'client_company' => $faker->company,
                'client_email'   => $faker->companyEmail,
                'client_phone'   => $faker->phoneNumber,
                'start_date'     => $faker->dateTimeBetween($startDate = '-30 days', $endDate = 'now', $timezone = null),
                'due_date'       => $faker->dateTimeBetween($startDate = '+50 days', $endDate = '+100 days', $timezone = null),
                'sort_order'     => $faker->numberBetween(1, 10),
                'is_started'     => $isActive,
                'is_active'      => $isActive,
                'is_completed'   => !$isActive,
                'is_default'     => $i == 0 ? true : false,
            ]);
        }
    }
}