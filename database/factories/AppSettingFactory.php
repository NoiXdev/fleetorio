<?php

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    /**
     * Es gibt bewusst genau eine Zeile (AppSetting::SINGLETON_ID). Eine
     * feste ID hier ist wichtig: 'id' steht nicht in $fillable, ein
     * gewöhnliches AppSetting::query()->create(['id' => ...]) würde sie
     * deshalb still verwerfen (siehe Illuminate\Database\Eloquent\Model::
     * fill()) - Factory::create() läuft dagegen in Model::unguarded() und
     * darf sie setzen. Ohne feste ID wäre man auf den
     * AUTO_INCREMENT-Zähler angewiesen, der bei MariaDB/InnoDB nach einem
     * von RefreshDatabase zurückgerollten Test nicht zurückgesetzt wird.
     */
    public function definition(): array
    {
        return [
            'id' => AppSetting::SINGLETON_ID,
            'brand_name' => fake()->company(),
            'logo_path' => null,
            'primary_color' => '#2563eb',
        ];
    }
}
