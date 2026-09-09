<?php

namespace App\Mail;

use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use App\Support\FinancialYear;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $firstItem;
    public $firstExtraTitle;

    /**
     * Create a new message instance.
     *
     * @param Invoice $invoice
     * @param Domain|Hosting|Email|null $firstItem
     */
    public function __construct(Invoice $invoice, $firstItem, $firstExtraTitle = null)
    {
        $this->invoice = $invoice;
        $this->firstItem = $firstItem;
        $this->firstExtraTitle = $firstExtraTitle;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        if ($this->firstExtraTitle) {
            return new Envelope(
                subject: $this->subjectFor($this->firstExtraTitle),
            );
        }

        $domain = match (get_class($this->firstItem)) {
            Domain::class => $this->firstItem->tld,
            Hosting::class => $this->firstItem->domain,
            Email::class => $this->firstItem->domain,
            default => 'Service',
        };

        return new Envelope(
            subject: $this->subjectFor($domain),
        );
    }

    protected function subjectFor(string $lineItem): string
    {
        return 'Invoice No. '.$this->invoice->invoice_no.' - '.$lineItem.' - '.FinancialYear::label($this->invoice->date);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice_generated',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
