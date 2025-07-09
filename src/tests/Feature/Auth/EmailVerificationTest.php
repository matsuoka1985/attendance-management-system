<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 会員登録後認証メールが送信される(): void
    {
        // ① 通知をフェイク（実メール送信をブロック & ログ用意）
        Notification::fake();

        // ② 会員登録リクエストを発行（例：Fortify デフォルトの /register）
        $response = $this->post('/register', [
            'name'                  => 'テスト太郎',
            'email'                 => 'taro@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        // ③ 登録自体が成功しているか軽く確認（リダイレクト先など）
        $response->assertRedirect('/email/verify');

        // ④ データベースにユーザが出来ている
        $user = User::where('email', 'taro@example.com')->firstOrFail();

        // ⑤ そのユーザ宛に VerifyEmail 通知が送信されたかを検証
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function メール認証を完了すると勤怠登録画面へリダイレクトされる(): void
    {
        /* ---------- 1. 未認証ユーザー ---------- */
        $user = User::factory()->create(['email_verified_at' => null]);

        /* ---------- 2. 署名付き URL 作成 ----------
           - verification.verify ルートは
             /email/verify/{id}/{hash} 形式     */
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->email),
            ]
        );

        /* ---------- 3. リクエスト実行 ----------
           actingAs で auth ミドルウェアを通過          */
        $response = $this->actingAs($user)->get($signedUrl);

        /* ---------- 4. 検証 ---------- */
        $response
            ->assertRedirect(route('attendance.stamp'))     // 勤怠登録画面へ
            ->assertSessionHas('mail_status', 'メール認証が完了しました。ご確認ありがとうございます。');

        $this->assertNotNull($user->fresh()->email_verified_at, 'email_verified_at が更新されていない');
    }
}
