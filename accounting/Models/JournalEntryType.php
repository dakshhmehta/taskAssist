<?php

namespace Ri\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntryType extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function entries()
    {
        return $this->hasMany(JournalEntry::class, 'type_id');
    }

    public function getNextSerialNo()
    {
        $journalEntry = JournalEntry::where('sr_no', 'LIKE', $this->code.'/%')
            ->orderBy('id', 'desc')
            ->first();

        if (! $journalEntry) {
            return $this->code.'/1';
        }

        $id = explode($this->code.'/', $journalEntry->sr_no);

        if (isset($id[1])) {
            return $this->code.'/'.($id[1]+1);
        }
    }
}
