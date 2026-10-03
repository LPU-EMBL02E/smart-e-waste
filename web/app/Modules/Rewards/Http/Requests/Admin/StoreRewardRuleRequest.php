<?php

namespace App\Modules\Rewards\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /admin/points-rate — save a new points rule.
 * API_Design.md §7.6, System_Plan.md §5.7.
 *
 * The three constraints between the variables are what stop a rule that looks
 * valid but behaves wrongly:
 *
 *   minimum_weight_g >= 1 / points_per_gram
 *       so an ACCEPTED deposit can never earn zero points, which would let a
 *       student deposit something, be accepted, and get nothing;
 *   maximum_weight_g > minimum_weight_g
 *       so the accepted range is not empty;
 *   maximum_weight_g <= the hardware limit
 *       so the load cell, platform and actuator are never asked for more than
 *       they can take.
 *
 * TODO(team): config('ewaste.hardware_max_weight_g') has no value yet
 * (API_Design.md P37, §10 question 7). While it is null the third check is
 * skipped — set it once the actuator is chosen and the platform is built.
 */
class StoreRewardRuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'points_per_gram' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'minimum_weight_g' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'maximum_weight_g' => ['nullable', 'numeric', 'gt:minimum_weight_g', 'decimal:0,2'],
            'daily_points_cap' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rate = (float) $this->input('points_per_gram');
                $minimum = (float) $this->input('minimum_weight_g');

                // An accepted deposit must never earn zero points.
                if ($rate > 0 && $minimum < 1 / $rate) {
                    $validator->errors()->add(
                        'minimum_weight_g',
                        sprintf(
                            'With this rate the minimum weight must be at least %s g, or an accepted deposit would earn zero points.',
                            rtrim(rtrim(number_format(1 / $rate, 2, '.', ''), '0'), '.')
                        )
                    );
                }

                $hardwareLimit = config('ewaste.hardware_max_weight_g');
                $maximum = $this->input('maximum_weight_g');

                if ($hardwareLimit !== null && $maximum !== null && (float) $maximum > (float) $hardwareLimit) {
                    $validator->errors()->add(
                        'maximum_weight_g',
                        "The maximum weight cannot exceed the hardware limit of {$hardwareLimit} g."
                    );
                }
            },
        ];
    }
}
