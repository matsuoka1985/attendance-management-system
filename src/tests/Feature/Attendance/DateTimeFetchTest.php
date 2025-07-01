<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Carbon\Carbon;

class DateTimeFetchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 現在の日時情報がUIと同じ形式で出力されている()
    {
        // 現在時刻取得
        $now = Carbon::now();

        // ユーザ作成＋認証状態
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('attendance.stamp'));

        $response->assertStatus(200);

        // 画面と同じ形式で日付と時刻を整形
        $expectedDate = $now->isoFormat('YYYY年M月D日(ddd)');
        $expectedTime = $now->format('H:i');

        // 表示内容検証
        $response->assertSeeText($expectedDate);
        $response->assertSeeText($expectedTime);
    }
}
