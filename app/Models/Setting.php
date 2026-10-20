<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const KEY_LONG_TERM_THRESHOLD = 'long_term_booking_threshold_days';
    public const DEFAULT_LONG_TERM_THRESHOLD = 90;

    public const KEY_PRICE_RECONFIRMATION_THRESHOLD = 'price_reconfirmation_threshold_days';
    public const DEFAULT_PRICE_RECONFIRMATION_THRESHOLD = 30;

    public const KEY_DOWNPAYMENT_PERCENTAGE = 'downpayment_percentage';
    public const DEFAULT_DOWNPAYMENT_PERCENTAGE = 50.0;

    /**
     * Markup applied to Gemini's raw material prices in AI estimates (never to official
     * quotations, which use the booking's own markup). The default is the 3× the estimates
     * used before this was a setting. Bounds: at least 1× (the booking markup's lower bound,
     * so an estimate is never below cost) and at most 10× (a sanity limit well above the 3×
     * in use; raise it here if Raflora ever needs more). Two decimals, like bookings.multiplier.
     */
    public const KEY_AI_ESTIMATE_MARKUP = 'ai_estimate_markup';
    public const DEFAULT_AI_ESTIMATE_MARKUP = 3.0;
    public const MIN_AI_ESTIMATE_MARKUP = 1.0;
    public const MAX_AI_ESTIMATE_MARKUP = 10.0;

    /**
     * Labour & setup allowance added once to an AI estimate: a fixed peso amount, or a percentage
     * of the AI material cost before markup (the basis official quotations use for percentage
     * labour). Not set means estimates exclude labour & setup.
     */
    public const KEY_AI_LABOUR_MODE = 'ai_estimate_labour_mode';
    public const KEY_AI_LABOUR_VALUE = 'ai_estimate_labour_value';
    public const AI_LABOUR_MODES = ['fixed' => 'Fixed amount (₱)', 'percentage' => 'Percentage of material cost (%)'];
    /** Largest fixed amount: what a decimal(10,2) price column such as final_quoted_price holds. */
    public const MAX_AI_LABOUR_FIXED = 99999999.99;
    public const MAX_AI_LABOUR_PERCENTAGE = 100.0;

    /**
     * Indicates if the model has timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Get a setting value by key.
     */
    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setSetting(string $key, mixed $value, string $type = 'string'): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    public static function getLongTermBookingThresholdDays(): int
    {
        return (int) static::getSetting(self::KEY_LONG_TERM_THRESHOLD, self::DEFAULT_LONG_TERM_THRESHOLD);
    }

    public static function getPriceReconfirmationThresholdDays(): int
    {
        return (int) static::getSetting(self::KEY_PRICE_RECONFIRMATION_THRESHOLD, self::DEFAULT_PRICE_RECONFIRMATION_THRESHOLD);
    }

    /**
     * The AI estimate markup, or null when the stored value is unusable (not a finite number
     * within the bounds), so a corrupted setting stops estimates instead of skewing them.
     */
    public static function aiEstimateMarkup(): ?float
    {
        $value = static::getSetting(self::KEY_AI_ESTIMATE_MARKUP, self::DEFAULT_AI_ESTIMATE_MARKUP);
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return null;
        }

        $markup = round((float) $value, 2);

        return $markup >= self::MIN_AI_ESTIMATE_MARKUP && $markup <= self::MAX_AI_ESTIMATE_MARKUP ? $markup : null;
    }

    /**
     * The labour & setup allowance: ['mode' => null] when Admin has not set one, the mode and
     * value when valid, or ['mode' => 'invalid'] when stored values are unusable.
     *
     * @return array{mode: ?string, value: ?float}
     */
    public static function aiLabourAllowance(): array
    {
        $mode = static::getSetting(self::KEY_AI_LABOUR_MODE);
        if ($mode === null || $mode === '') {
            return ['mode' => null, 'value' => null];
        }

        $value = static::getSetting(self::KEY_AI_LABOUR_VALUE);
        $max = $mode === 'percentage' ? self::MAX_AI_LABOUR_PERCENTAGE : self::MAX_AI_LABOUR_FIXED;
        if (!isset(self::AI_LABOUR_MODES[$mode]) || !is_numeric($value) || !is_finite((float) $value)
            || (float) $value < 0 || (float) $value > $max) {
            return ['mode' => 'invalid', 'value' => null];
        }

        return ['mode' => $mode, 'value' => round((float) $value, 2)];
    }
}
