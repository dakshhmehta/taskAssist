<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JournalEntryTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            ['Journal Entry', 'JE', '#FFA500'], // Orange
            ['Adjusting Entry', 'AE', '#FF6347'], // Tomato
            ['Cash Receipt', 'CR', '#FFB6C1'], // Light Pink
            ['Bank Receipt', 'BR', '#FFD700'], // Gold
            ['Cash Payment', 'CP', '#90EE90'], // Light Green
            ['Bank Payment', 'BP', '#ADD8E6'], // Light Blue
            ['Contra', 'CN', '#FF69B4'], // Hot Pink
            ['Purchase Entry', 'PE', '#DDA0DD'], // Plum
            ['Sales Entry', 'SE', '#20B2AA'], // Light Sea Green
            ['Purchase Return', 'PR', '#FFA07A'], // Light Salmon
            ['Sales Return', 'SR', '#7B68EE'], // Medium Slate Blue
            ['Opening Entry', 'OE', '#40E0D0'], // Turquoise
            ['Closing Entry', 'CE', '#4682B4'], // Steel Blue
            ['Depreciation Entry', 'DE', '#778899'], // Light Slate Gray
            ['Accrual Entry', 'AE', '#32CD32'], // Lime Green
            ['Prepaid Entry', 'PP', '#B0C4DE'], // Light Steel Blue
            ['Provision Entry', 'PV', '#CD5C5C'], // Indian Red
        ];

        foreach ($types as $type) {
            try {
                DB::table('journal_entry_types')->updateOrInsert([
                    'label' => $type[0],
                    'code' => $type[1],
                    'color' => $type[2],
                ]);
            }
            catch(\Exception $e){}
        }
    }
}