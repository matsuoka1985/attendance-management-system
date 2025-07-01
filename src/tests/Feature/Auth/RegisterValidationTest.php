<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;


class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 名前が未入力の場合はバリデーションメッセージが表示される()
    {
        $response = $this->post('/register', [
            'name' => '', // 名前を未入力に
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertInvalid(['name']);

        // オプション: メッセージが必要であれば下記も追加
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    #[Test]
    public function メールアドレス未入力で登録しようとするとバリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => '', // ← メールアドレス未入力
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertInvalid(['email']);
        $response->assertRedirect(); // バリデーションエラーでリダイレクトされる
    }

    #[Test]
    public function パスワードが8文字未満の場合、バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'shortpass@example.com',
            'password' => 'short77',
            'password_confirmation' => 'short77',
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertInvalid(['password']);
        $response->assertRedirect();
    }

    #[Test]
    public function パスワードが一致しない場合、バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123', // ← 一致させない
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertInvalid(['password']);
        $response->assertRedirect(); // バリデーションエラーでリダイレクトされる
    }

    #[Test]
    public function パスワードが未入力の場合、バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => '', // 未入力
            'password_confirmation' => '', // 一致させる
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertInvalid(['password']);
        $response->assertRedirect(); // バリデーション失敗時はリダイレクト
    }

    #[Test]
    public function フォームに内容が入力されていた場合、データが正常に保存される(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
            'password' => 'securePassword123',
            'password_confirmation' => 'securePassword123',
        ]);

        $response->assertRedirect('/email/verify'); // 登録後のリダイレクト先
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
        ]);
    }
}
