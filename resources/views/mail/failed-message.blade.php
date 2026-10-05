<x-mail.base :subject="__('Failed Message Alert')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 ">
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <strong class="text-xs text-gray-400">
                        A message has failed to send for claim Number: {{ $claim->claim_number }}. Please investigate the issue.
                    </strong>
                    <br>
                    <div>
                        <strong>Error Message:</strong> {{ $errMessage }}
                    </div>
                    <br>
                    <br>
                    <a href="{{ config('app.frontend_url') . '/claims/' . $claim->id . '/info' }}"
                        style="background:#4a46d3;color:#fff;padding:10px 15px;text-decoration:none;border-radius:8px;">
                        View Claim Details
                    </a>
                    <br><br>

                </div>

            </div>

        </div>
    </div>
</x-mail.base>