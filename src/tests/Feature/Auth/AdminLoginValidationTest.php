<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;

class AdminLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function メールアドレスが未入力の場合、バリデーションメッセージが表示される()
    {
        // 管理者ユーザ作成（本テストでは実際には使わない）
        User::factory()->create(['role' => 'admin']);

        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    #[Test]
    public function パスワードが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // 管理者ユーザ作成
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin', // 管理者と判定される条件に応じて修正
        ]);

        // パスワードなしでログインリク
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '', // パスワ未入力
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertInvalid(['password']);
        $response->assertRedirect(); // バリデーション失敗時のリダイレクト確認

        // 必要ならエラーメッセージも確認
        $this->assertStringContainsString(
            'パスワードを入力してください',
            session('errors')->first('password')
        );
    }

    #[Test]
    public function 登録内容と一致しない場合、バリデーションメッセージが表示される(): void
    {
        // 管理者ユーザを作成
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'admin',
        ]);

        // メールアドレスは間違った値でログイン試行
        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }
}
