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
            width: 3310px;
            height: 2482px;
            position: relative;
        }

        .abs {
            position: absolute;
        }

        .invoice-no {
            top: 402px;
            left: 1950px;
            font-size: 40px;
        }

        .invoice-date {
            top: 402px;
            left: 2655px;
            font-size: 40px;
        }

        .client {
            top: 490px;
            left: 625px;
            font-size: 50px;
        }

        .client span {
            font-size: 62px;
            position: absolute;
            margin-top: -5px;
            margin-left: 25px;
            /* font-weight: bold; */
            width: 2000px;
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
            top: 850px;
            left: 275px;
            width: 2965px;
            /* background-color: black; */
        }

        .clearfix {
            clear: both;
        }

        .row {
            padding: 20px 0px;
            font-size: 50px;
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
            margin-left: 38px;
            padding-left: 30px;
        }

        .col.duration {
            width: 261px;
            /* background-color: green; */
            margin-left: 4px;
            text-align: center;
        }

        .col.price {
            width: 356px;
            /* background-color: yellow; */
            margin-left: 4px;
            text-align: center;
        }

        .col.amount {
            width: 571px;
            /* background-color: blue; */
            margin-left: 4px;
            text-align: center;
        }

        p {
            margin: 15px 0px;
            /* border-bottom: 1px solid black; */
        }

        .total {
            top: 1935px;
            left: 2670px;
            font-size: 50px;
            width: 571px;
            text-align: center;
            font-weight: bold;
        }

        .footnote {
            top: 1850px;
            left: 590px;
            font-size: 40px;
            text-align: center;
            font-weight: bold;
        }

        .in-words {
            top: 1945px;
            left: 590px;
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
    </style>
</head>

<body>
    <div id="invoice">
        <div class="abs invoice-no">{{ $invoice->invoice_no }}</div>
        <div class="abs invoice-date">{{ $invoice->date->format('d/F/Y') }}</div>

        <div class="abs client">Name: <span>{{ $invoice->client->billing_name }}</span></div>
        <div class="abs work">Work: <span>Registration/renewal of domain and hosting for the year {{ $invoice->date->format('Y').' - '.($invoice->date->format('Y')+1) }}</span></div>

        @php
            $domains = $invoice->items()->where('itemable_type', App\Models\Domain::class)->get();
        @endphp

        <!-- <div id="items" class="abs">
            <div class="row">
                <div class="col sr"><p>1.</p></div>
                <div class="col item">
                    <p><b>DOMAINS/s:</b></p>
                    @foreach($domains as $i => $domain)
                        <p>{{ $domain->itemable->tld }}</p>
                    @endforeach
                </div>
                <div class="col duration">
                    <p>&nbsp;</p>
                    @foreach($domains as $i => $domain)
                        <p>1 year</p>
                    @endforeach
                </div>
                <div class="col price">
                    <p>&nbsp;</p>
                    @foreach($domains as $i => $domain)
                        <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
                    @endforeach
                </div>
                <div class="col amount">
                    <p>&nbsp;</p>
                    @foreach($domains as $i => $domain)
                        <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
                    @endforeach
                </div>
                <div class="clearfix"></div>
            </div>
        </div> -->

        <div id="items" class="abs">
            @foreach($domains as $i => $domain)
            <div class="row">
                <div class="col sr"><p>{{ $i+1 }}.</p></div>
                <div class="col item">
                    <p><b>DOMAIN:</b></p>
                    <p>{{ $domain->itemable->tld }}</p>
                </div>
                <div class="col duration">
                    <p>&nbsp;</p>
                    <p>1 year</p>
                </div>
                <div class="col price">
                    <p>&nbsp;</p>
                    <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
                </div>
                <div class="col amount">
                    <p>&nbsp;</p>
                    <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
                </div>
                <div class="clearfix"></div>
            </div>
            @endforeach
        </div>

        <div class="abs total">Rs. {{ number_format($invoice->total, 2) }}/-</div>
        <div class="abs footnote">Next domain and hosting renewal: {{ $invoice->date->format('F') }}, {{ $invoice->date->format('Y')+1 }}</div>

        <div class="abs in-words"><b>Rupees: </b> {{ ucfirst($invoice->inWords()) }}</div>

        <div class="abs warning">
            Due to a steady increase in the value of the US dollar, we will be increasing the prices of our Domains and Hosting services from March 2020. 
            While we were able to bear the Forex losses in the weeks before, the current and expected exchange rates have left us with no other option but 
            to adjust our prices. Thank you for your understanding.
        </div>
    </div>
</body>

</html>