<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;

class LoginValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function メールアドレスが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // 事前にユーザを登録
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => '', // メアド未入力
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
        $response->assertInvalid(['email']);
        $response->assertRedirect();
    }


    #[Test]
    public function パスワードが未入力の場合、バリデーションメッセージが表示される()
    {
        // 準備：ユーザ作成
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 実行：パスワード以外を入力してログイン試行
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        // 検証：リダイレクト & エラーメッセージ確認
        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    #[Test]
    public function 登録内容と一致しない場合、バリデーションメッセージが表示される()
    {
        // 登録済みユーザ
        $user = User::factory()->create([
            'email' => 'correct@example.com',
            'password' => bcrypt('correctpassword'),
        ]);

        // 誤ったメールアドレスでログインを試行
        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'correctpassword',
        ]);

        // セッションにエラーメッセージが格納されているか確認
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
