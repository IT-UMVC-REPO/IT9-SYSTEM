<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\TagumCoordinate;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'agree_terms' => ['required', 'accepted'],
        ], [
            'agree_terms.required' => 'You must agree to the Terms and Conditions and Privacy Policy.',
            'agree_terms.accepted' => 'You must agree to the Terms and Conditions and Privacy Policy.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => UserRole::Customer,
            'is_active' => true,
            'phone' => $input['phone'] ?? null,
            'address' => $input['address'] ?? null,
            ...TagumCoordinate::random(),
        ]);
    }
}
