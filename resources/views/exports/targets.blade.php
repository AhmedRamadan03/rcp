<table class="table-auto">
    <thead>
    <tr>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;"></th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.officer_name') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.total_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.remaining_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.canceled_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.total_targets') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.achieved_targets') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.remaining_targets') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.outstanding_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.pending_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.collected_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.partial_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.delay_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.legal_claims') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('report.closed_claims') }}</th>

    </tr>
    </thead>
    <tbody>
    @foreach ($targets as $target)
        <tr class="text-md">
            <td >{{ $loop->iteration }}</td>
            <td > {{ $target->name }} </td>
            <td > {{ $target->all_claims }} </td>
            <td > {{ $target->remaining__claims ?? 0 }} </td>
            <td > {{ $target->cancelled__claims ?? 0 }} </td>
            <td > {{ $target->target_sum ?? 0 }} </td>
            <td > {{ $target->target_achieved ?? 0 }} </td>
            <td > {{ $target->target_remaining ?? 0 }} </td>
            <td > {{ $target->outstanding_claims ?? 0 }} ( {{ $target->outstanding_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>
            <td > {{ $target->pending_claims ?? 0 }} ( {{ $target->pending_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>
            <td > {{ $target->collected__claims ?? 0 }} ( {{ $target->collected_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>
            <td > {{ $target->partial_claims ?? 0 }} ( {{ $target->partial_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>
            <td > {{ $target->delay_claims ?? 0 }} ( {{ $target->delay_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>
            <td > {{ $target->legal_claims ?? 0 }} ( {{ $target->legal_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>
            <td > {{ $target->closed_claims ?? 0 }} ( {{ $target->closed_sum ?? 0 }}
                ) {{ __('claim.sar') }} </td>

        </tr>
    @endforeach
    </tbody>
</table>
