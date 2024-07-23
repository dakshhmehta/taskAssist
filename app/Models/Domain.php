<?php

namespace App\Models;

use App\ResellerClub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    public function sync()
    {
        $rc = ResellerClub::fetch($this->tld);

        $this->expiry_date = date('Y-m-d H:i:s', $rc[1]['orders.endtime']);
        $this->save();
    }
}
