<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UpcomingRenewalsService
{
    public function getRenewals(?string $domainFilter = null, Carbon|string|null $tillDate = null): Collection
    {
        $today = now()->startOfDay();
        $tillDate = $this->resolveTillDate($tillDate);

        return collect()
            ->merge($this->getDomains($domainFilter, $tillDate, $today))
            ->merge($this->getHostings($domainFilter, $tillDate, $today))
            ->merge($this->getEmails($domainFilter, $tillDate, $today))
            ->sortBy([
                ['is_expired', 'desc'],
                ['expiry_date', 'asc'],
                ['domain', 'asc'],
            ])
            ->values();
    }

    protected function resolveTillDate(Carbon|string|null $tillDate): Carbon
    {
        if ($tillDate instanceof Carbon) {
            return $tillDate->copy()->endOfDay();
        }

        if (is_string($tillDate) && $tillDate !== '') {
            return Carbon::parse($tillDate)->endOfDay();
        }

        return now()->addDays(7)->endOfDay();
    }

    protected function getDomains(?string $domainFilter, Carbon $tillDate, Carbon $today): Collection
    {
        $query = Domain::query()
            ->whereNotNull('expiry_date')
            ->with('client.account')
            ->excludeIgnored();

        if ($domainFilter) {
            $query->where('tld', 'like', "%{$domainFilter}%");
        } else {
            $query->where('expiry_date', '<=', $tillDate);
        }

        return $query
            ->get()
            ->map(fn (Domain $domain) => $this->formatRenewal(
                type: 'Domain',
                domain: $domain->tld,
                expiryDate: $domain->expiry_date,
                today: $today,
                client: $domain->client,
            ));
    }

    protected function getHostings(?string $domainFilter, Carbon $tillDate, Carbon $today): Collection
    {
        $query = Hosting::query()
            ->whereNotNull('expiry_date')
            ->with('client.account')
            ->excludeIgnored();

        if ($domainFilter) {
            $query->where('domain', 'like', "%{$domainFilter}%");
        } else {
            $query->where('expiry_date', '<=', $tillDate);
        }

        return $query
            ->get()
            ->map(fn (Hosting $hosting) => $this->formatRenewal(
                type: 'Hosting',
                domain: $hosting->domain,
                expiryDate: $hosting->expiry_date,
                today: $today,
                client: $hosting->client,
            ));
    }

    protected function getEmails(?string $domainFilter, Carbon $tillDate, Carbon $today): Collection
    {
        $query = Email::query()
            ->whereNotNull('expiry_date')
            ->with('client.account')
            ->excludeIgnored();

        if ($domainFilter) {
            $query->where('domain', 'like', "%{$domainFilter}%");
        } else {
            $query->where('expiry_date', '<=', $tillDate);
        }

        return $query
            ->get()
            ->map(fn (Email $email) => $this->formatRenewal(
                type: 'GSuite',
                domain: $email->domain,
                expiryDate: $email->expiry_date,
                today: $today,
                client: $email->client,
                accounts: $email->accounts_count,
            ));
    }

    protected function formatRenewal(
        string $type,
        string $domain,
        Carbon $expiryDate,
        Carbon $today,
        mixed $client = null,
        ?int $accounts = null,
    ): array {
        $isExpired = $expiryDate->lt($today);

        return [
            'type' => $type,
            'domain' => $domain,
            'expiry_date' => $expiryDate->format('Y-m-d H:i:s'),
            'is_expired' => $isExpired,
            'days_overdue' => $isExpired ? $expiryDate->diffInDays($today) : null,
            'days_until_expiry' => $isExpired ? null : $today->diffInDays($expiryDate, false),
            'client' => $client,
            'accounts' => $accounts,
        ];
    }
}
