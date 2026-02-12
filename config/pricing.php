<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Pricing by TLD
    |--------------------------------------------------------------------------
    |
    | Define the default pricing for different domain TLDs.
    | Prices should include GST (18%).
    |
    */
    'domains' => [
        'com' => 1460,
        'in' => 960,
        'org' => 1599,
        'net' => 9000,
        'co.in' => 9000,
        'info' => 9000,
        'biz' => 9000,
        'org.in' => 885,
        'com.in' => 885,
        'net.in' => 885,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Workspace Pricing
    |--------------------------------------------------------------------------
    |
    | Default price per account per year.
    | The job will calculate the total based on the number of years
    | between invoice date and expiry date.
    | Price should include GST (18%).
    |
    */
    'workspace' => [
        'price_per_account_per_month' => 463, // 236 × 12
        'price_per_account_per_year' => 3270.00, // 236 × 12
    ],

    /*
    |--------------------------------------------------------------------------
    | Hosting Pricing
    |--------------------------------------------------------------------------
    |
    | Hosting prices are determined by the package relationship.
    | If no package is found, the default price below will be used.
    |
    */
    'hosting' => [
        'default_price' => 0,
    ],
];
