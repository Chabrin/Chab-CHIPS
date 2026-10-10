<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSystemUser extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:create-user';

    /**
     * The console command description.
     */
    protected $description = 'Interactively create a system user (email, names, role); username and password are generated';

    /**
     * Execute the console command.
     */
    public function handle() {
        $email = strtolower(trim($this->ask('Email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return self::FAILURE;
        }

        if (DB::table('josya_users')->where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');
            return self::FAILURE;
        }

        $first = trim($this->ask('First name'));
        $middle = trim((string) $this->ask('Middle name (leave blank if none)'));
        $last = trim($this->ask('Last name'));

        if ($first === '' || $last === '') {
            $this->error('First and last name are required.');
            return self::FAILURE;
        }

        $roles = DB::table('cb_roles')->orderBy('display_name')->get(['id', 'display_name', 'name']);

        if ($roles->isEmpty()) {
            $this->error('No roles found in cb_roles.');
            return self::FAILURE;
        }

        $labels = $roles->mapWithKeys(fn($r) => [$r->id => $r->display_name ?: $r->name])->all();
        $roleLabel = $this->choice('Role', array_values($labels));
        $roleId = array_search($roleLabel, $labels);

        $name = trim(preg_replace('/\s+/', ' ', "$first $middle $last"));
        $username = $this->uniqueUsername($email);
        $password = (string) $this->secret('Password (min 8 chars, leave blank to generate one)');
        $generated = $password === '';

        if ($generated) {
            $password = Str::password(12, true, true, false, false);
        } elseif (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        } elseif ($password !== (string) $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        $this->table(['Email', 'Name', 'Username', 'Role'], [[$email, $name, $username, $roleLabel]]);

        if (!$this->confirm('Create this user?', true)) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($name, $username, $email, $password, $roleId) {
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'block' => 0,
                'sendEmail' => 0,
                'registerDate' => now(),
                'lastvisitDate' => now(),
                'activation' => '',
                'params' => '',
                'resetCount' => 0,
                'otpKey' => '',
                'otep' => '',
                'requireReset' => 1,
            ]);

            DB::table('cb_role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
            ]);
        });

        $this->info('User created. Password change is forced on first login.');
        $this->line("  Username: $username");
        $this->line($generated ? "  Password: $password (generated, shown once)" : '  Password: as entered');

        return self::SUCCESS;
    }

    /**
     * Build a username from the email's local part, suffixing a number until it is unique.
     */
    private function uniqueUsername(string $email): string {
        $base = Str::of(Str::before($email, '@'))->lower()->replaceMatches('/[^a-z0-9._-]/', '')->limit(90, '')->toString() ?: 'user';
        $username = $base;
        $i = 1;

        while (DB::table('josya_users')->where('username', $username)->exists()) {
            $username = $base . $i++;
        }

        return $username;
    }
}
