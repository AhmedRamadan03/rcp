<table>
    <thead>
    <tr>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">policy_number</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">claim_number</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">sub_claim_number</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">id_number</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">od_mobile_no</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">outstanding_recovery</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">driver_name</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">claim_type</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">debtor_type</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">od_plate_number</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">policy_holder</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">date_of_loss</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">claim_registration_date</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">accident_number</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">recovered_amount</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">od_vehicle_vehicle_make</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">od_vehicle_vehicle_model</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">liability</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">area</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">remarks</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">employee_email</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">claim_status</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">error_message</th>
        <th style="background-color: #AAAAAA; color: #000000; padding: 1rem;">row_number</th>

    </tr>
    </thead>
    <tbody>
    @foreach($failedClaims as $claim)
        <tr>
            <td> {{ $claim["policy_number"] ?? '' }} </td>
            <td> {{ $claim["claim_number"] ?? '' }} </td>
            <td> {{ $claim["sub_claim_number"] ?? '' }} </td>
            <td> {{ $claim["id_number"] ?? '' }} </td>
            <td> {{ $claim["od_mobile_no"] ?? '' }} </td>
            <td> {{ $claim["outstanding_recovery"] ?? '' }} </td>
            <td> {{ $claim["driver_name"] ?? '' }} </td>
            <td> {{ $claim["claim_type"] ?? '' }} </td>
            <td> {{ $claim["debtor_type"] ?? '' }} </td>
            <td> {{ $claim["od_plate_number"] ?? '' }} </td>
            <td> {{ $claim["policy_holder"] ?? '' }} </td>
            <td> {{ $claim["date_of_loss"] ?? '' }} </td>
            <td> {{ $claim["claim_registration_date"] ?? '' }} </td>
            <td> {{ $claim["accident_number"] ?? '' }} </td>
            <td> {{ $claim["recovered_amount"] ?? '' }} </td>
            <td> {{ $claim["od_vehicle_vehicle_make"] ?? '' }} </td>
            <td> {{ $claim["od_vehicle_vehicle_model"] ?? '' }} </td>
            <td> {{ $claim["liability"] ?? '' }} </td>
            <td> {{ $claim["area"] ?? '' }} </td>
            <td> {{ $claim["remarks"] ?? '' }} </td>
            <td> {{ $claim["employee_email"] ?? '' }} </td>
            <td> {{ $claim["claim_status"] ?? '' }} </td>
            <td> {{ $claim["error_message"] ?? '' }} </td>
            <td> {{ $claim["row_number"] ?? '' }} </td>
        </tr>
    @endforeach
    </tbody>
</table>
