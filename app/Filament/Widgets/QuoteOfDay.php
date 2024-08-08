<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class QuoteOfDay extends Widget
{
    protected static string $view = 'filament.widgets.quote-of-day';

    protected int | string | array $columnSpan = 12;

    protected function getQuote()
    {
        $_key = 'quote';
        $quote = Cache::get($_key, null);

        if (! $quote) {
            $quote = file_get_contents('https://zenquotes.io/api/today');

            $quote = json_decode($quote);

            Cache::put($_key, $quote, now()->endOfDay());
        }

        return $quote[0];
    }

    protected function getViewData(): array
    {
        return [
            'quote' => $this->getQuote()
        ];
    }
}
