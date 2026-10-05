<table class="w-full table-auto text-sm text-start">
    <thead class="bg-gray-400 text-white font-medium border-b">
    <tr>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.delegated_from') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.delegated_to') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.start_date') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.end_date') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.duration_days') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.status') }}</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">{{ __('delegation.created_at') }}</th>
    </tr>
    </thead>
    <tbody class="text-gray-600 divide-y text-center">
    @foreach ($delegations as $delegation)
        @php
            $startDate = \Carbon\Carbon::parse($delegation->start_date);
            $durationDays = null;
            
            if ($delegation->end_date) {
                $endDate = \Carbon\Carbon::parse($delegation->end_date);
                $durationDays = $startDate->diffInDays($endDate);
            } else if ($delegation->is_active) {
                $durationDays = $startDate->diffInDays(now());
            }
            
            $status = $delegation->is_active ? 'Active' : 'Inactive';
        @endphp
        <tr>
            <td>{{ $delegation->fromUser->name ?? '-' }}</td>
            <td>{{ $delegation->toUser->name ?? '-' }}</td>
            <td>{{ $delegation->start_date->format('Y-m-d H:i:s') }}</td>
            <td>{{ $delegation->end_date ? $delegation->end_date->format('Y-m-d H:i:s') : 'Indefinite' }}</td>
            <td>{{ $durationDays ?? '-' }}</td>
            <td>{{ $status }}</td>
            <td>{{ $delegation->created_at->format('Y-m-d H:i:s') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>