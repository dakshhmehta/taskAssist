<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use Carbon\Carbon;

class InvoicePricingService
{
    public static function getDomainPrice(string $tld, Carbon $invoiceDate, ?Carbon $expiryDate): float
    {
        $tldExtension = self::extractTldExtension($tld);
        $tldKey = ltrim($tldExtension, '.');
        $domainPricing = config('pricing.domains', []);
        $basePrice = $domainPricing[$tldKey] ?? 0;
        $years = self::calculateYears($invoiceDate, $expiryDate);

        return $basePrice * $years;
    }

    public static function getHostingPrice(Hosting $hosting): float
    {
        return $hosting->package?->price ?? config('pricing.hosting.default_price', 0);
    }

    public static function getEmailPrice(Email $email, Carbon $invoiceDate, ?Carbon $expiryDate): float
    {
        $years = self::calculateYears($invoiceDate, $expiryDate);

        if ($years > 0) {
            $pricePerAccountPerYear = config('pricing.workspace.price_per_account_per_year', 0);
            return $pricePerAccountPerYear * ($email->accounts_count ?? 0) * $years;
        }

        $months = self::calculateMonths($invoiceDate, $expiryDate);
        $pricePerAccountPerMonth = config('pricing.workspace.price_per_account_per_month', 0);
        return $pricePerAccountPerMonth * ($email->accounts_count ?? 0) * $months;
    }

    private static function extractTldExtension(string $domain): string
    {
        $multiPartTlds = ['.co.in', '.co.uk', '.com.au', '.org.uk', '.net.au', '.org.in', '.com.in', '.net.in'];

        foreach ($multiPartTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                return $tld;
            }
        }

        $parts = explode('.', $domain);
        if (count($parts) >= 2) {
            return '.' . end($parts);
        }

        return '';
    }

    private static function calculateYears(Carbon $invoiceDate, ?Carbon $expiryDate): int
    {
        if (! $expiryDate) {
            return 1;
        }

        return max(1, $invoiceDate->diffInYears($expiryDate));
    }

    private static function calculateMonths(Carbon $invoiceDate, ?Carbon $expiryDate): int
    {
        if (! $expiryDate) {
            return 12;
        }

        return max(1, $invoiceDate->diffInMonths($expiryDate));
    }
}
