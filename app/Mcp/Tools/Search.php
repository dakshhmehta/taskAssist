<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use App\Models\Task;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class Search extends Tool
{
    protected string $title = 'Search';

    /**
     * Same per-type cap Filament uses for global search
     * (Resource::$globalSearchResultsLimit).
     */
    protected int $limit = 50;

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Search clients, domains, hosting, emails, invoices and tasks in one call. Returns one flat result set where every row has a type label, a title and Filament-style detail fields. Matching mirrors Filament global search: every word in the query must match at least one searchable field.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search term. Every word must match.')->required(),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $query = trim((string) $request->get('query'));

        if ($query === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Search query cannot be empty.',
            ]);
        }

        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        $results = collect()
            ->merge($this->clients($words))
            ->merge($this->domains($words))
            ->merge($this->hostings($words))
            ->merge($this->emails($words))
            ->merge($this->invoices($words))
            ->merge($this->tasks($words))
            ->values();

        return Response::json([
            'status' => 'success',
            'query' => $query,
            'count' => $results->count(),
            'results' => $results->toArray(),
        ]);
    }

    /**
     * Apply Filament's matching rules: every word must match at least one
     * attribute (query-wide AND, attribute-wide OR), then cap the results.
     */
    private function matches(Builder $query, array $words, array $attributes): Builder
    {
        foreach ($words as $word) {
            $query->where(function (Builder $query) use ($word, $attributes) {
                foreach ($attributes as $attribute) {
                    if (str_contains($attribute, '.')) {
                        [$relation, $column] = explode('.', $attribute, 2);

                        $query->orWhereHas(
                            $relation,
                            fn (Builder $relation) => $relation->where($column, 'like', "%{$word}%")
                        );

                        continue;
                    }

                    $query->orWhere($attribute, 'like', "%{$word}%");
                }
            });
        }

        return $query->limit($this->limit);
    }

    private function row(string $type, int $id, ?string $title, array $details): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'details' => $details,
        ];
    }

    private function clients(array $words)
    {
        return $this->matches(Client::query(), $words, ['billing_name', 'nickname', 'email'])
            ->get()
            ->map(fn (Client $client) => $this->row('client', $client->id, $client->billing_name, [
                'Nickname' => $client->nickname,
                'Email' => $client->email,
                'Receivable' => $client->getReceivable(),
            ]));
    }

    private function domains(array $words)
    {
        return $this->matches(Domain::query(), $words, ['tld'])
            ->get()
            ->map(fn (Domain $domain) => $this->row('domain', $domain->id, $domain->tld, [
                'Expiry' => $domain->expiry_date?->format(config('app.date_format')),
                'Ignored' => $domain->isIgnored() ? 'Yes' : 'No',
            ]));
    }

    private function hostings(array $words)
    {
        return $this->matches(Hosting::query()->with('package'), $words, ['domain'])
            ->get()
            ->map(fn (Hosting $hosting) => $this->row('hosting', $hosting->id, $hosting->domain, [
                'Expiry' => $hosting->expiry_date?->format(config('app.date_format')),
                'Server' => $hosting->server,
                'Web Space' => optional($hosting->package)->storage_formatted,
            ]));
    }

    private function emails(array $words)
    {
        return $this->matches(Email::query(), $words, ['domain'])
            ->get()
            ->map(fn (Email $email) => $this->row('email', $email->id, $email->domain, [
                'Accounts' => $email->accounts_count,
                'Expiry' => $email->expiry_date?->format(config('app.date_format')),
                'Ignored' => $email->isIgnored() ? 'Yes' : 'No',
            ]));
    }

    private function invoices(array $words)
    {
        // shortcut: total uses the existing accessor (2 queries per invoice, at most 50)
        // — swap to withSum('items'/'extras', 'price') if search latency ever matters.
        return $this->matches(Invoice::query()->with('client'), $words, ['invoice_no', 'client.billing_name'])
            ->get()
            ->map(fn (Invoice $invoice) => $this->row('invoice', $invoice->id, $invoice->invoice_no, [
                'Client' => $invoice->client?->billing_name,
                'Date' => $invoice->date?->format(config('app.date_format')),
                'Total' => $invoice->total,
                'Status' => $invoice->display_status,
            ]));
    }

    private function tasks(array $words)
    {
        return $this->matches(
            Task::query()
                ->with('assignee')
                ->orderByRaw('completed_at IS NULL DESC')
                ->orderBy('completed_at', 'DESC'),
            $words,
            ['title']
        )
            ->get()
            ->map(fn (Task $task) => $this->row('task', $task->id, $task->title, [
                'Tag' => $task->tag,
                'Assignee' => $task->assignee?->name,
                'Estimate' => $task->estimate_label,
            ]));
    }
}
