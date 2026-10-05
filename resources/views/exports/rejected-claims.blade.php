<table id="table" class="table-auto">
    <thead>
    <tr>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.claim_number') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.reject_comment') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('claim.status.status') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($rejected_claims as $rejected_claim)
        <tr>
            <td> {{ $rejected_claim->claim?->claim_number }} </td>
            <td> {{ $rejected_claim->reason }} </td>
            <td>
                {{ __('report.' . str_replace(' ', '_',  $rejected_claim->status)) }}
            </td>
        </tr>


    @endforeach
    </tbody>
</table>
