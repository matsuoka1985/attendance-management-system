<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CorrectionRequestItem;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CorrectionRequestItem>
 */
/**
 * 明細は親 Seeder から values を渡して生成する想定なので
 * ここでは「フィールドだけランダム、値はプレースホルダ」を返す
 * （Seeder で before / after を実データに合わせて上書き）
 */
class CorrectionRequestItemFactory extends Factory
{
    protected $model = CorrectionRequestItem::class;

    public function definition(): array
    {
        return [
            'field'        => $this->faker->randomElement([
                CorrectionRequestItem::FIELD_START_TIME,
                CorrectionRequestItem::FIELD_END_TIME,
                CorrectionRequestItem::FIELD_BREAK_START,
                CorrectionRequestItem::FIELD_BREAK_END,
                CorrectionRequestItem::FIELD_NOTE,
            ]),
            'before_value' => '00:00',
            'after_value'  => '00:00',
        ];
    }
}
