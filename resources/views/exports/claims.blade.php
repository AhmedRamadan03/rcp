<table>
    <thead>
    <tr>
        @if(auth()->user()->email == 'hazim@taheiya.sa')
            <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.id") }}</th>
        @endif
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.claim_number") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.sub_claim_number") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.accident_number") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.debtor_name") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.debtor_phone_number") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.debtor_id") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.accident_date") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.claim_amount") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.paid") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.remaining_amount") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.intimation_date") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.submission_date") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.assigned_to") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.created_by") }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __("claim.status.status") }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($claims as $claim)
        <tr>
            @if(auth()->user()->email == 'hazim@taheiya.sa')
                <td>{{ $claim->id }}</td>
            @endif
            <td>{{ $claim->claim_number }}</td>
            <td>{{ $claim->sub_claim_number }}</td>
            <td>{{ $claim->policy_number }}</td>
            <td>{{ $claim->debtor_name }}</td>
            <td>{{ $claim->debtor_phone }}</td>
            <td>{{ $claim->debtor_nid }}</td>
            <td>{{ \Carbon\Carbon::parse($claim->accident_date)->format('d-m-Y') }}</td>
            <td>{{ $claim->amount }}</td>
            <td>{{ $claim->payments?->first()?->paid_amount }}</td>
            <td>{{ $claim->amount - $claim->payments?->first()?->paid_amount }}</td>
            <td>{{ \Carbon\Carbon::parse($claim->intimation_date)->format('d-m-Y') }}</td>
            <td>{{ \Carbon\Carbon::parse($claim->created_at)->format('d-m-Y') }}</td>
            <td>{{ $claim->assignees?->where("is_active", 1)?->first()?->toUser?->name }}</td>
            <td>{{ $claim->user?->name }}</td>
            <td>{{ \App\Enums\ClaimStatus::getString($claim->status) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
