<?php

namespace App\Services\Discounts;

use App\Services\DiscountService;
use InvalidArgumentException;

class DiscountCombinerFactory
{
    public const MODE_SINGLE_WINNER = 'single_winner';

    public const MODE_WATERFALL = 'waterfall';

    public const MODE_BEST_PER_LINE = 'best_per_line';

    public static function make(?string $mode, DiscountService $discounts): DiscountCombiner
    {
        return match ($mode) {
            self::MODE_SINGLE_WINNER => new SingleWinnerCombiner($discounts),
            self::MODE_WATERFALL => new WaterfallCombiner($discounts),
            self::MODE_BEST_PER_LINE => new BestPerLineCombiner($discounts),
            default => throw new InvalidArgumentException(
                "Unknown discount combination mode [{$mode}]. Expected one of: "
                .implode(', ', [self::MODE_SINGLE_WINNER, self::MODE_WATERFALL, self::MODE_BEST_PER_LINE])
            ),
        };
    }
}
