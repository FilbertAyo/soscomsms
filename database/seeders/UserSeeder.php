<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->orWhere('email', 'soscomtechnologies@gmail.com')
            ->orWhere('email', 'admin@soscom.co.tz')
            ->first();

        if ($admin) {
            $admin->forceFill([
                'name' => 'Soscom Admin',
                'email' => 'soscomtechnologies@gmail.com',
                'role' => 'admin',
                'password' => 'Soscom@2026',
                'email_verified_at' => now(),
            ])->save();
        } else {
            (new User)->forceFill([
                'name' => 'Soscom Admin',
                'email' => 'soscomtechnologies@gmail.com',
                'role' => 'admin',
                'password' => 'Soscom@2026',
                'email_verified_at' => now(),
            ])->save();
        }

        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@soscom.co.tz'],
            [
                'name' => 'Amina Juma',
                'role' => 'customer',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $profile = $customer->customer()->updateOrCreate(
            ['user_id' => $customer->id],
            [
                'business_name' => 'Linkadi Ltd',
                'phone' => '+255754221890',
                'job_title' => 'Owner',
                'status' => CustomerStatus::Active,
            ]
        );

        app(CreditLedger::class)->grantWelcome($profile);
    }
}
