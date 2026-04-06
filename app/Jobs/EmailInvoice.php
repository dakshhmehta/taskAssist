<?php

namespace App\Jobs;

use App\Mail\InvoiceGenerated;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;

    /**
     * @var mixed
     */
    public $firstItem;

    /**
     * Create a new job instance.
     *
     * @param Invoice $invoice
     * @param mixed $firstItem
     */
    public function __construct(Invoice $invoice, $firstItem)
    {
        $this->invoice = $invoice;
        $this->firstItem = $firstItem;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $client = $this->invoice->client;
        $recipientEmail = $client->email ?? 'rominjoshi@yahoo.com';

        if ($recipientEmail) {
            Mail::to($recipientEmail)->send(new InvoiceGenerated($this->invoice, $this->firstItem));
        }
    }
}
