<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_no }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">

    <style type="text/css">
        #invoice {
            font-family: "Lato", sans-serif;
            background: url("{{ asset('invoice/bg.jpg') }}") no-repeat;
            background-size: cover;
            width: 3507px;
            height: 2480px;
            position: relative;
        }

        .abs {
            position: absolute;
        }

        .invoice-no {
            top: 435px;
            left: 2070px;
            font-size: 40px;
        }

        .invoice-type {
            font-weight: bold;
            font-size: 52px;
            top: 100px;
            left: 330px;
        }

        .invoice-date {
            top: 435px;
            left: 2780px;
            font-size: 40px;
        }

        .client {
            top: 500px;
            left: 625px;
            font-size: 50px;
        }

        .address {
            top: 595px;
            left: 625px;
            font-size: 50px;
        }

        .gstin {
            top: 500px;
            left: 2400px;
            font-size: 50px;
        }

        .work {
            top: 565px;
            left: 625px;
            font-size: 50px;
        }

        .work span {
            font-size: 62px;
            position: absolute;
            margin-top: -5px;
            margin-left: 25px;
            /* font-weight: bold; */
            width: 2000px;
        }

        #items {
            top: 800px;
            left: 275px;
            width: 3070px;
            /* background-color: black; */
        }

        .clearfix {
            clear: both;
        }

        .row {
            padding: 20px 0px;
            font-size: 50px;
        }

        .items-6 .row {
            font-size: 30px;
        }

        .row .col {
            /* background-color: red; */
            float: left;
        }

        .col.sr {
            width: 251px;
            /* background-color: purple; */
            text-align: center;
        }

        .col.item {
            width: 1446px;
            /* background-color: pink; */
            margin-left: 100px;
            /* padding-left: 30px; */
        }

        .col.duration {
            width: 320px;
            /* background-color: green; */
            margin-left: 4px;
            text-align: center;
        }

        .col.price {
            width: 400px;
            /* background-color: yellow; */
            margin-left: 4px;
            text-align: center;
        }

        .col.amount {
            width: 540px;
            /* background-color: blue; */
            margin-left: 4px;
            text-align: center;
        }

        p {
            margin: 15px 0px;
            /* border-bottom: 1px solid black; */
        }

        .total {
            top: 2000px;
            left: 2805px;
            font-size: 50px;
            width: 540px;
            text-align: center;
            font-weight: bold;
        }

        .footnote {
            top: 1850px;
            left: 630px;
            font-size: 40px;
            text-align: center;
            font-weight: bold;
        }

        .tax-area {
            top: 1700px;
            left: 1770px;
            font-size: 50px;
            line-height: 80px;
        }

        .tax-value {
            top: 1700px;
            left: 2805px;
            font-size: 50px;
            width: 540px;
            text-align: center;
            line-height: 80px;
        }

        .in-words {
            top: 2005px;
            left: 650px;
            font-size: 40px;
            text-align: center;
        }

        .warning {
            top: 2310px;
            left: 590px;
            font-size: 34px;
            line-height: 50px;
            color: red;
            width: 1785px;
        }

        .paid-stamp {
            top: 1990px;
            left: 2700px;
            width: 500px;
            height: 500px;
        }

        .ri-logo {
            top: 2240px;
            left: 2700px;
            width: 500px;
            height: 94px;
        }
        .ri-logo img {
            width: 100%;
        }

        .lut-no {
            font-weight: bold;
            font-size: 38px;
            top: 100px;
            left: 2770px;
        }
    </style>
</head>

<body>
    <div id="invoice">
        @if($invoice->date->lte(config('app.gstin_start_date')))
            @include('invoices.normal_format')
        @else
            @include('invoices.tax_format')
        @endif
    </div>
</body>

</html>