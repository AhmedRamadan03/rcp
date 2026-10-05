<table class="w-full table-auto text-sm text-start">
    <thead class="bg-gray-400 text-white font-medium border-b">
    <tr>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.claim_number') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.installment_number') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.payment_id') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.result') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.response_code') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.collector_name') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.rrn') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.amount') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.card_brand') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.masked_pan') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.payment_type') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.date_time') }}</th>
    </tr>
    </thead>
    <tbody class="text-gray-600 divide-y text-center">
    @foreach ($transactions as $transaction)
        {!! $transaction !!}
    @endforeach
    </tbody>
</table>
