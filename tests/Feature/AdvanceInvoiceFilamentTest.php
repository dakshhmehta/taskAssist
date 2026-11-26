<?php

namespace Tests\Feature;

use App\Filament\Resources\DomainResource\Pages\ListDomains;
use App\Filament\Resources\EmailResource\Pages\ListEmails;
use App\Filament\Resources\HostingResource\Pages\ListHostings;
use App\Jobs\GenerateInvoice;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class AdvanceInvoiceFilamentTest extends TestCase
{
    use RefreshDatabase;

    private function makeDomain(): Domain
    {
        $client = Client::create([
            'billing_name' => 'Filament Term Client',
            'nickname' => 'FTC',
        ]);

        return Domain::create([
            'tld' => 'filamentterm.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addDays(10),
        ]);
    }

    public function test_the_generate_invoice_action_passes_the_term_to_the_job(): void
    {
        Bus::fake();

        $this->actingAs(User::factory()->create(['id' => 1]));

        Livewire::test(ListDomains::class)
            ->callTableAction('generateInvoice', $this->makeDomain(), data: ['term' => 1]);

        Bus::assertDispatched(GenerateInvoice::class, fn (GenerateInvoice $job) => $job->term === 1);
    }

    public function test_the_bulk_generate_invoices_action_passes_the_term_to_the_job(): void
    {
        Bus::fake();

        $this->actingAs(User::factory()->create(['id' => 1]));

        Livewire::test(ListDomains::class)
            ->callTableBulkAction('generateInvoices', [$this->makeDomain()], data: ['term' => 2]);

        Bus::assertDispatched(GenerateInvoice::class, fn (GenerateInvoice $job) => $job->term === 2);
    }

    public function test_the_hosting_generate_invoice_action_passes_the_term_to_the_job(): void
    {
        Bus::fake();

        $this->actingAs(User::factory()->create(['id' => 1]));

        $hosting = Hosting::create([
            'domain' => 'filamenthosting.com',
            'client_id' => Client::create(['billing_name' => 'Hosting Term', 'nickname' => 'HT'])->id,
            'expiry_date' => now()->addDays(10),
        ]);

        Livewire::test(ListHostings::class)
            ->callTableAction('generateInvoice', $hosting, data: ['term' => 1]);

        Bus::assertDispatched(GenerateInvoice::class, fn (GenerateInvoice $job) => $job->term === 1);
    }

    public function test_the_email_generate_invoice_action_passes_the_term_to_the_job(): void
    {
        Bus::fake();

        $this->actingAs(User::factory()->create(['id' => 1]));

        $email = Email::create([
            'domain' => 'filamentemail.com',
            'provider' => 'google',
            'client_id' => Client::create(['billing_name' => 'Email Term', 'nickname' => 'ET'])->id,
            'expiry_date' => now()->addDays(10),
        ]);

        Livewire::test(ListEmails::class)
            ->callTableAction('generateInvoice', $email, data: ['term' => 1]);

        Bus::assertDispatched(GenerateInvoice::class, fn (GenerateInvoice $job) => $job->term === 1);
    }
}
