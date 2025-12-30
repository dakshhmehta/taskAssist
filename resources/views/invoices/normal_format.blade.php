@if($invoice->paid_date != null)
<img class="abs paid-stamp" src="{{ asset('invoice/paid_stamp.png') }}" />
@endif

<div class="abs invoice-no">{{ $invoice->invoice_no }}</div>
<div class="abs invoice-date">{{ $invoice->date->format('d/F/Y') }}</div>

<div class="abs client"><b>Name:</b> <span>{{ $invoice->client->billing_name }}</span></div>

@php
$domains = $invoice->items()->where('itemable_type', App\Models\Domain::class)->get();
$hostings = $invoice->items()->where('itemable_type', App\Models\Hosting::class)->get();
$emails = $invoice->items()->where('itemable_type', App\Models\Email::class)->get();
$extras = $invoice->extras;

$totalRows = count($domains) + count($hostings) + count($emails);

$rowCount = 1;
@endphp

@if($totalRows > 0)
<!-- <div class="abs work">Work: <span>Registration/renewal of domain and hosting for the year {{ $invoice->date->format('Y').' - '.($invoice->date->format('Y')+1) }}</span></div> -->
@endif

<div id="items" class="abs {{ 'items-'.$totalRows }}">
    @foreach($domains as $i => $domain)
    <div class="row">
        <div class="col sr">
            <p>{{ $rowCount++ }}.</p>
        </div>
        <div class="col item">
            <p><b>DOMAIN:</b></p>
            <p>{{ $domain->itemable->tld }}</p>
            @if($domain->line_description)
            <p>{!! $domain->line_description !!}</p>
            @endif
        </div>
        <div class="col duration">
            <p>&nbsp;</p>
            <p>1 year</p>
        </div>
        <div class="col price">
            <p>&nbsp;</p>
            @if($domain->discount_value > 0)
                <p><s>Rs. {{ number_format($domain->price + $domain->discount_value) }}</s><br/>Rs. {{ number_format($domain->price) }}</p>
            @else
                <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
            @endif
        </div>
        <div class="col amount">
            <p>&nbsp;</p>
            <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
        </div>
        <div class="clearfix"></div>
    </div>
    @endforeach

    @foreach($hostings as $i => $hosting)
    <div class="row">
        <div class="col sr">
            <p>{{ $rowCount++ }}.</p>
        </div>
        <div class="col item">
            <p><b>WEB HOSTING: {{ $hosting->itemable->domain }}</b></p>
            @if($hosting->itemable->package)
            <table style="text-align: left;">
                <tr>
                    <th style="padding-right: 50px;">Web Space:</th>
                    <td>{{ optional($hosting->itemable->package)->storage_formatted }}</td>
                </tr>
                <tr>
                    <th>Server:</th>
                    <td>Shared Linux SSD Hosting</td>
                </tr>
                <tr>
                    <th>Emails:</th>
                    <td>{{ (($hosting->itemable->package->emails == -1) ? 'unlimited' :  $hosting->itemable->package->emails) }}</td>
                </tr>
                @if($hosting->line_description)
                <tr>
                    <th>Description:</th>
                    <td>{!! $hosting->line_description !!}</td>
                </tr>
                @endif
            </table>
            @endif
        </div>
        <div class="col duration">
            <p>&nbsp;</p>
            <p>1 year</p>
        </div>
        <div class="col price">
            <p>&nbsp;</p>
            @if($hosting->discount_value > 0)
                <p><s>Rs. {{ number_format($hosting->price + $hosting->discount_value) }}</s><br/>Rs. {{ number_format($hosting->price) }}</p>
            @else
                <p>Rs. {{ number_format($hosting->price, 2) }}/-</p>
            @endif
        </div>
        <div class="col amount">
            <p>&nbsp;</p>
            <p>Rs. {{ number_format($hosting->price, 2) }}/-</p>
        </div>
        <div class="clearfix"></div>
    </div>
    @endforeach

    @foreach($emails as $i => $email)
    <div class="row">
        <div class="col sr">
            <p>{{ $rowCount++ }}.</p>
        </div>
        <div class="col item">
            <p><b>Google Workspace: {{ $email->itemable->domain }}</b></p>
            <table style="text-align: left;">
                <tr>
                    <th style="padding-right: 50px;">Email Accounts:</th>
                    <td>{{ $email->itemable->accounts_count }}</td>
                </tr>
                @if($email->line_description)
                <tr>
                    <th>Description:</th>
                    <td>{!! $email->line_description !!}</td>
                </tr>
                @endif
            </table>
        </div>
        <div class="col duration">
            <p>&nbsp;</p>
            <p>1 year</p>
        </div>
        <div class="col price">
            <p>&nbsp;</p>
            @if($email->discount_value > 0)
                <p><s>Rs. {{ number_format($email->price + $email->discount_value) }}</s><br/>Rs. {{ number_format($email->price) }}</p>
            @else
                <p>Rs. {{ number_format($email->price, 2) }}/-</p>
            @endif
        </div>
        <div class="col amount">
            <p>&nbsp;</p>
            <p>Rs. {{ number_format($email->price, 2) }}/-</p>
        </div>
        <div class="clearfix"></div>
    </div>
    @endforeach

    @foreach($extras as $extra)
    <div class="row">
        <div class="col sr">
            <p>{{ $rowCount++ }}.</p>
        </div>
        <div class="col item">
            <p><b>{{ $extra->line_title }}</b></p>
            <p>{!! $extra->line_description !!}</p>
        </div>
        <div class="col duration">
            @if($extra->line_duration)
            <p>&nbsp;</p>
            <p>{{ $extra->line_duration }}</p>
            @else
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            @endif
        </div>
        <div class="col price">
            <p>&nbsp;</p>
            @if($extra->discount_value > 0)
                <p><s>Rs. {{ number_format($extra->price + $extra->discount_value) }}</s><br/>Rs. {{ number_format($extra->price) }}</p>
            @else
                <p>Rs. {{ number_format($extra->price, 2) }}/-</p>
            @endif
        </div>
        <div class="col amount">
            <p>&nbsp;</p>
            <p>Rs. {{ number_format($extra->price, 2) }}/-</p>
        </div>
        <div class="clearfix"></div>
    </div>
    @endforeach

</div>

<div class="abs total">Rs. {{ number_format($invoice->total, 2) }}/-</div>
@if($totalRows > 0)
<div class="abs footnote">Next domain and hosting renewal: {{ $invoice->date->format('F') }}, {{ $invoice->date->format('Y')+1 }}</div>
@endif

<div class="abs in-words"><b>Rupees: </b> {{ ucfirst($invoice->inWords()) }}</div>

<div class="abs ri-logo">
    <img src="{{ asset('images/ri_logo.png') }}" />
</div>