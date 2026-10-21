<?php

namespace App\Console\Commands;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates (or promotes) the first administrator on a fresh production
 * database. `db:seed` must never run there -- DatabaseSeeder creates demo
 * accounts whose password is literally "password" -- so this is the only
 * supported way in:
 *
 *     php artisan ecenter:create-admin it@arucad.edu.tr --name="IT Yönetici"
 *
 * The password is asked interactively (never echoed, never in shell
 * history). Also makes sure the portal settings row exists and that the
 * admin's email domain is accepted, so the account can actually sign in.
 */
#[Signature('ecenter:create-admin {email : Admin e-mail address} {--name= : Display name}')]
#[Description('Create the first administrator account (or promote an existing user to admin)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid e-mail address: '.$email);

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min. 12 characters)');
        if (mb_strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }
        if ($password !== (string) $this->secret('Repeat password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], [
            'name' => $this->option('name') ?: User::where('email', $email)->value('name') ?: 'Yönetici',
            'password' => $password,
            'role' => 'admin',
            'deactivated_at' => null,
        ]);

        $settings = PortalSetting::current();
        $domain = substr(strrchr($email, '@'), 1);
        $domains = array_map('strtolower', $settings->accepted_domains ?: []);
        if (! in_array($domain, $domains, true)) {
            $settings->update(['accepted_domains' => array_values(array_unique([...$domains, $domain]))]);
            $this->line('Added '.$domain.' to accepted domains.');
        }

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' administrator '.$email.'. Sign in at '.rtrim((string) config('app.url'), '/').'/admin');

        return self::SUCCESS;
    }
}
