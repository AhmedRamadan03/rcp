@props(['subject'])

<!DOCTYPE html>
<html>
<head>
    <title>Complaint Submitted</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 20px 0 0; /* Add margin-top of 20px */
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
        }
        p {
            color: #555;
            line-height: 1.6;
        }
        .complaint-info {
            background-color: #f2f2f2;
            padding: 10px;
            border-radius: 5px;
        }
        .complaint-id {
            font-weight: bold;
        }

        .bg-red-200{
            background-color: #fecaca;
        }

        .border-red-300	{
            border-color: rgb(252 165 165);
        }

        .h-8 {
            height: 2rem; /* 32px */
        }

        .rounded {
            border-radius: 0.25rem; /* 4px */
        }

        .p-2 {
            padding: 0.5rem; /* 8px */
        }
        .border {
            border-width: 1px;
        }
        .flex {
            display: flex;
        }
        .items-center {
            justify-content: center;
        }

    </style>


</head>
<body>
<div class="container">
    <h1>{{ $subject }}</h1>
{{--    <p>Your complaint has been submitted successfully:</p>--}}
{{--    <div class="complaint-info">--}}
{{--        <p><span class="complaint-id">ID:</span> 11</p>--}}
{{--        <p><strong>Title:</strong> test</p>--}}
{{--        <p><strong>Description:</strong> test 2</p>--}}
{{--        {{ $slot }}--}}
{{--    </div>--}}

    {{ $slot }}


{{--    <p>Thank you for submitting your complaint. We have received it, and our team will review and assign it to a developer for further action. Please keep an eye on your email and our website for updates and notifications regarding your complaint.</p>--}}
    <!-- Add more content as needed -->
</div>
</body>
</html>
