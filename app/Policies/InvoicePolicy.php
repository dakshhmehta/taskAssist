<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->is_admin && $invoice->paid_date == null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->is_admin;
    }

    public function markAsPaid(User $user, Invoice $invoice): bool
    {
        if($invoice->date->lte(config('app.gstin_start_date'))){
            return $user->is_admin && $invoice->paid_date == null;
        }

        return $user->is_admin && $invoice->type == 'TAX' && $invoice->paid_date == null;
    }

    public function convertToTaxInvoice(User $user, Invoice $invoice): bool
    {
        if($invoice->date->gte(config('app.gstin_start_date'))){
            return $user->is_admin && $invoice->paid_date == null && $invoice->type == 'PROFORMA';
        }

        return false;
    }
}
