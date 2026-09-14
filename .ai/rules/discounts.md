---
paths:
  - 'app/Services/Discounts/**'
---

# Discounts

## Discount combining lives in strategy classes
Discount combining is a strategy, not inline logic: DiscountCombiner interface with Single/Waterfall/BestPerLine implementations chosen by config('discounts.combination_mode'). bestDiscountForOrder only builds candidates and dispatches; per-item math stays shared in DiscountService. New combining behavior = new class, never edits to the dispatcher or other modes.

## Discount combining lives in strategy classes
Discount combining is a strategy, not inline logic: DiscountCombiner interface with Single/Waterfall/BestPerLine implementations chosen by config('discounts.combination_mode'). bestDiscountForOrder only builds candidates and dispatches; per-item math stays shared in DiscountService. New combining behavior = new class, never edits to the dispatcher or other modes.
