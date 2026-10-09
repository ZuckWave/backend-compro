<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateOwner extends Command
{
    protected $signature = 'compro:create-owner {--name=} {--email=}';

    protected $description = 'Membuat akun owner pertama tanpa password bawaan.';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Nama owner'),
            'email' => $this->option('email') ?: $this->ask('Email owner'),
            'password' => $this->secret('Password (minimal 8 karakter)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(fn () => User::create([...$validator->validated(), 'role' => 'owner']));
        $this->info('Akun owner berhasil dibuat.');

        return self::SUCCESS;
    }
}
