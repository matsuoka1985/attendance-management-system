<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class EmailVerificationTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移する(): void
    {
        /* ① 未認証ユーザー & MailHog URL を用意 */
        $user    = User::factory()->create(['email_verified_at' => null]);
        // MailHog は docker-compose 内サービス名でアクセスされる
        $mailUrl = 'http://mailhog:8025';            // ※テスト用に固定
        config(['app.mail_client_url' => $mailUrl]); // view 側は env('MAIL_CLIENT_URL') を参照

        /* ② ブラウザ操作 */
        $this->browse(function (Browser $browser) use ($user, $mailUrl) {

            $browser->loginAs($user)
                ->visit(route('verification.notice'))
                ->assertSee('認証はこちらから');

            /* --- 新しいタブを開くリンクをクリック --- */
            $oldHandles = $browser->driver->getWindowHandles();     // 現在のタブ一覧
            $browser->clickLink('認証はこちらから');

            /* --- 新しいタブが開くのを待機 & そのハンドルを取得 --- */
            $browser->waitUsing(5, 100, function () use ($browser, $oldHandles, &$newHandle) {
                $handles = $browser->driver->getWindowHandles();
                $diff    = array_diff($handles, $oldHandles); // 新規ハンドル
                if ($diff) {
                    $newHandle = array_pop($diff);
                    return true;
                }
                return false;
            });

            /* --- 取得したハンドルへスイッチ --- */
            $browser->driver->switchTo()->window($newHandle);

            /* --- MailHog 画面に遷移しているか検証 --- */
            $browser->waitForLocation('/', 5)
                ->assertUrlIs($mailUrl . '/')
                ->assertSee('MailHog')             // ページタイトルに含まれるはず
                ->screenshot('mailhog_redirect_ok');
        });
    }
}
