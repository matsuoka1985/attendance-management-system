<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── 開発者用ログインアカウント ──────────────
        User::updateOrCreate(
            ['email' => 'dev@example.com'],
            [
                'name'              => '開発者アカウント',
                'password'          => Hash::make('password'),
                'role'              => 'user',
                'email_verified_at' => now(),
            ]
        );

        // ── 管理者アカウント ─────────────────────
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'              => '管理者アカウント',
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // ── デザインに登場するスタッフ6名 ─────────
        $dummyStaffs = [
            ['西 玲奈',   'reina.n@coachtech.com'],
            ['山田 太郎', 'taro.y@coachtech.com'],
            ['増田 一世', 'issei.m@coachtech.com'],
            ['山本 敬吉', 'keikichi.y@coachtech.com'],
            ['秋田 朋美', 'tomomi.a@coachtech.com'],
            ['中西 敦夫', 'norio.n@coachtech.com'],
        ];

        foreach ($dummyStaffs as [$name, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => Hash::make('password'), // 全員共通パス
                    'role'              => 'user',
                    'email_verified_at' => now(),
                ]
            );
        }

    }
}
