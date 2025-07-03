@if($invoice->paid_date != null)
        <img class="abs paid-stamp" src="{{ asset('invoice/paid_stamp.png') }}" />
        @endif

        <div class="abs invoice-type">
            @if($invoice->client->account?->country == 'India')
                @if($invoice->type == 'PROFORMA')
                PROFORMA INVOICE
                @elseif($invoice->type == 'TAX')
                    TAX INVOICE
                @endif
            @else
                EXPORT INVOICE
            @endif
        </div>
        @if($invoice->client->account?->country != 'India')
        <div class="abs lut-no">
            LUT ARN: {{ config('app.gstin_lut_no') }}
        </div>
        @endif
        <div class="abs invoice-no">{{ $invoice->invoice_no }}</div>
        <div class="abs invoice-date">{{ $invoice->date->format('d/F/Y') }}</div>

        <div class="abs client"><b>Name:</b> <span>{{ $invoice->client->account?->billing_name ?? $invoice->client->billing_name }}</span></div>
        @if($invoice->client->account?->billing_address)
        <div class="abs address"><b>Address:</b> <span>{{ $invoice->client->account?->billing_address }}</span></div>
        @endif

        @if($invoice->client->account?->gstin != null)
        <div class="abs gstin"><b>GSTIN:</b> <span>{{ $invoice->client->account?->gstin }}</span></div>
        @endif

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
                    <p><b>HSN/SAC:</b> 998315</p>
                </div>
                <div class="col duration">
                    <p>&nbsp;</p>
                    <p>1 year</p>
                </div>
                <div class="col price">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        @if($domain->discount_value > 0)
                            <p><s>Rs. {{ number_format(($domain->price + $domain->discount_value) / 1.18) }}</s><br/>Rs. {{ number_format($domain->price / 1.18, 2) }}</p>
                        @else
                            <p>Rs. {{ number_format($domain->price / 1.18, 2) }}/-</p>
                        @endif
                    @else
                        <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
                    @endif
                </div>
                <div class="col amount">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        <p>Rs. {{ number_format($domain->price / 1.18, 2) }}/-</p>
                    @else
                        <p>Rs. {{ number_format($domain->price, 2) }}/-</p>
                    @endif
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
                    <p><b>HSN/SAC:</b> 998315</p>
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
                    </table>
                    @endif
                </div>
                <div class="col duration">
                    <p>&nbsp;</p>
                    <p>1 year</p>
                </div>
                <div class="col price">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        @if($hosting->discount_value > 0)
                            <p><s>Rs. {{ number_format(($hosting->price + $hosting->discount_value)) }}</s><br/>Rs. {{ number_format($hosting->price / 1.18, 2) }}</p>
                        @else
                            <p>Rs. {{ number_format($hosting->price / 1.18, 2) }}/-</p>
                        @endif
                    @else
                        <p>Rs. {{ number_format($hosting->price, 2) }}/-</p>
                    @endif
                </div>
                <div class="col amount">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        <p>Rs. {{ number_format($hosting->price / 1.18, 2) }}/-</p>
                    @else
                        <p>Rs. {{ number_format($hosting->price, 2) }}/-</p>
                    @endif
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
                    <p><b>HSN/SAC:</b> 998315</p>
                    <table style="text-align: left;">
                        <tr>
                            <th style="padding-right: 50px;">Email Accounts:</th>
                            <td>{{ $email->itemable->accounts_count }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col duration">
                    <p>&nbsp;</p>
                    <p>1 year</p>
                </div>
                <div class="col price">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        @if($email->discount_value > 0)
                            <p><s>Rs. {{ number_format(($email->price + $email->discount_value)) }}</s><br/>Rs. {{ number_format($email->price / 1.18, 2) }}</p>
                        @else
                            <p>Rs. {{ number_format($email->price / 1.18, 2) }}/-</p>
                        @endif
                    @else
                        <p>Rs. {{ number_format($email->price, 2) }}/-</p>
                    @endif
                </div>
                <div class="col amount">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        <p>Rs. {{ number_format($email->price / 1.18, 2) }}/-</p>
                    @else
                        <p>Rs. {{ number_format($email->price, 2) }}/-</p>
                    @endif
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
                    <p><b>HSN/SAC:</b> 998314</p>
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
                    @if($invoice->client->account?->country == 'India')
                        @if($extra->discount_value > 0)
                            <p><s>Rs. {{ number_format(($extra->price + $extra->discount_value)) }}</s><br/>Rs. {{ number_format($extra->price / 1.18, 2) }}</p>
                        @else
                            <p>Rs. {{ number_format($extra->price / 1.18, 2) }}/-</p>
                        @endif
                    @else
                        <p>Rs. {{ number_format($extra->price, 2) }}/-</p>
                    @endif
                </div>
                <div class="col amount">
                    <p>&nbsp;</p>
                    @if($invoice->client->account?->country == 'India')
                        <p>Rs. {{ number_format($extra->price / 1.18, 2) }}/-</p>
                    @else
                        <p>Rs. {{ number_format($extra->price, 2) }}/-</p>
                    @endif
                </div>
                <div class="clearfix"></div>
            </div>
            @endforeach

        </div>

        <div class="abs total">Rs. {{ number_format($invoice->total, 2) }}/-</div>
        @if($totalRows > 0)
        <div class="abs footnote">Next domain and hosting renewal: {{ $invoice->date->format('F') }}, {{ $invoice->date->format('Y')+1 }}
            @if($invoice->client->account?->country != 'India')
                <br/>(Export of services without payment of IGST)
            @endif
        </div>
        @endif

        @if($invoice->client->account?->country == 'India')
        <div class="abs tax-area">
            <p>
                @if($invoice->cgst > 0)
                <span class="tax">CGST (9%):</span><br />
                @endif
                @if($invoice->sgst > 0)
                <span class="tax">SGST (9%):</span><br />
                @endif
                @if($invoice->igst > 0)
                <span class="tax">IGST (18%):</span><br />
                @endif
            </p>
        </div>
        <div class="abs tax-value">
            <p>
                @if($invoice->cgst > 0)
                Rs. {{ number_format($invoice->cgst, 2) }}/-<br />
                @endif
                @if($invoice->sgst > 0)
                Rs. {{ number_format($invoice->sgst, 2) }}/-<br />
                @endif
                @if($invoice->igst > 0)
                Rs. {{ number_format($invoice->igst, 2) }}/-<br />
                @endif
            </p>
        </div>
        @endif

        <div class="abs in-words"><b>Rupees: </b> {{ ucfirst($invoice->inWords()) }}</div>

        <div class="abs ri-logo">
            <img src="{{ asset('images/ri_logo.png') }}" />
        </div>

        <!-- <div class="abs warning">
            Due to a steady increase in the value of the US dollar, we will be increasing the prices of our Domains and Hosting services from March 2020. 
            While we were able to bear the Forex losses in the weeks before, the current and expected exchange rates have left us with no other option but 
            to adjust our prices. Thank you for your understanding.
        </div> -->