<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Browsershot\Browsershot;

class InvoicesController extends Controller
{
    public function getPrint($id, Request $request)
    {
        $invoice = Invoice::with('client.account')->findOrFail($id);

        if ($request->has('view')) {
            return view('invoices.print', compact('invoice'));
        }

        $invoicePath = storage_path('app/public/invoice_' . $invoice->id . '.pdf');

        if (file_exists($invoicePath) && !$request->has('force')) {
            return redirect()->to('storage/invoice_' . $invoice->id . '.pdf');
        }

        Browsershot::url(route('invoices.print', [$invoice->id, 'view' => 1]))
            ->setNodeBinary("'/Users/dakshhmehta/Library/Application Support/Herd/config/nvm/versions/node/v20.12.2/bin/node'")
            ->setNpmBinary("'/Users/dakshhmehta/Library/Application Support/Herd/config/nvm/versions/node/v20.12.2/bin/npm'")
            ->showBackground()
            ->paperSize(3510, 2482, 'px')
            // ->format('A4')
            // ->landscape()
            ->scale(0.97)
            ->save($invoicePath);

        return redirect()->to('storage/invoice_' . $invoice->id . '.pdf');
    }
}
