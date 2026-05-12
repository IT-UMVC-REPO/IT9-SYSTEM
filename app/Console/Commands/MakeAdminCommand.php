<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('make:admin {name} {email} {password}')]
#[Description('Create a verified SukiMarket administrator account')]
class MakeAdminCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = [
            'name' => (string) $this->argument('name'),
            'email' => (string) $this->argument('email'),
            'password' => (string) $this->argument('password'),
            'password_confirmation' => (string) $this->argument('password'),
        ];

        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->components->info(__('Admin account created.'));
        $this->table(
            [__('ID'), __('Name'), __('Email'), __('Role'), __('Verified')],
            [[$user->getKey(), $user->name, $user->email, $user->role->value, $user->email_verified_at->toDateTimeString()]],
        );

        return self::SUCCESS;
    }
}
