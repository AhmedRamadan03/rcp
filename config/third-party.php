<?php

use App\Enums\ThirdPartyExecutionType;
use App\Models\ThirdParty;

return [
    'sms' => [
        'unifonic' => [
            'vendor_name' => 'Unifonic',
            'vendor_label' => 'unifonic',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'AppSid',
                    'type' => 'text',
                    'label' => 'App SID',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string|max:255',
                    'placeholder' => 'Enter your App SID key'
                ],
                [
                    'name' => 'SenderID',
                    'type' => 'text',
                    'label' => 'Sender ID',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string',
                    'placeholder' => 'Enter your Sender ID'
                ],
                [
                    'name' => 'CorrelationID',
                    'type' => 'text',
                    'label' => 'Correlation ID',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string',
                    'placeholder' => 'Enter your Correlation ID'
                ],
            ]
        ],
        'mesgat' => [
            'vendor_name' => 'Mesgat',
            'vendor_label' => 'mesgat',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'api_key',
                    'type' => 'text',
                    'label' => 'API Key',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'username',
                    'type' => 'text',
                    'label' => 'User Name',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'sender_name',
                    'type' => 'text',
                    'label' => 'Sender Name',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|string'
                ],
            ]
        ],

        'gig' => [
            'vendor_name' => 'Gig',
            'vendor_label' => 'gig',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'client_id',
                    'type' => 'text',
                    'label' => 'Client ID',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'client_secret',
                    'type' => 'text',
                    'label' => 'Client Secret',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'url',
                    'type' => 'url',
                    'label' => 'URL',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|url'
                ],
                [
                    'name' => 'source',
                    'type' => 'text',
                    'label' => 'Source',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|string'
                ],
            ]
        ],
        'alrajhi_takful' => [
            'vendor_name' => 'AlRajhiTakful',
            'vendor_label' => 'alrajhi_takful',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::API_PROVIDER->value,
            ],
            'inputs' => [
                [
                    'name' => 'client_id',
                    'type' => 'text',
                    'label' => 'Client ID',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'client_secret',
                    'type' => 'text',
                    'label' => 'Client Secret',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
            ]
        ],

    ],
// we have not integration with any email provider yet but we will edit it later
    'email' => [
        'gmail' => [
            'vendor_name' => 'Gmail',
            'vendor_label' => 'gmail',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email Address',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|email'
                ],
                [
                    'name' => 'password',
                    'type' => 'password',
                    'label' => 'App Password',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'smtp_host',
                    'type' => 'text',
                    'label' => 'SMTP Host',
                    'required' => false,
                    'sensitive' => false,
                    'validation' => 'nullable|string',
                    'default' => 'smtp.gmail.com'
                ],
                [
                    'name' => 'smtp_port',
                    'type' => 'number',
                    'label' => 'SMTP Port',
                    'required' => false,
                    'sensitive' => false,
                    'validation' => 'nullable|integer',
                    'default' => '587'
                ],
            ]
        ],
        'hotmail' => [
            'vendor_name' => 'HotMail',
            'vendor_label' => 'hotmail',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'api_key',
                    'type' => 'text',
                    'label' => 'API Key',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'from_email',
                    'type' => 'email',
                    'label' => 'From Email',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|email'
                ],
                [
                    'name' => 'from_name',
                    'type' => 'text',
                    'label' => 'From Name',
                    'required' => false,
                    'sensitive' => false,
                    'validation' => 'nullable|string'
                ],
            ]
        ],
    ],

    'payment' => [
        // 'urway' => [
        //     'vendor_name' => 'Urway',
        //     'vendor_label' => 'urway',
        //     'config' => [
        //         ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
        //     ],
        //     'inputs' => [
        //         [
        //             'name' => 'terminalId',
        //             'type' => 'text',
        //             'label' => 'Terminal ID',
        //             'required' => true,
        //             'sensitive' => true,
        //             'validation' => 'required|string'
        //         ],
        //         [
        //             'name' => 'password',
        //             'type' => 'password',
        //             'label' => 'Password',
        //             'required' => true,
        //             'sensitive' => true,
        //             'validation' => 'required|string'
        //         ],
        //         [
        //             'name' => 'merchant_key',
        //             'type' => 'text',
        //             'label' => 'Merchant Key',
        //             'required' => true,
        //             'sensitive' => true,
        //             'validation' => 'required|string'
        //         ],
        //     ]
        // ],
        'amazon_payment' => [
            'vendor_name' => 'AmazonPayment',
            'vendor_label' => 'amazon_payment',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'merchant_identifier',
                    'type' => 'text',
                    'label' => 'Merchant Identifier',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'access_code',
                    'type' => 'text',
                    'label' => 'Access Code',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'sha_request_phrase',
                    'type' => 'text',
                    'label' => 'SHA Request Phrase',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'sha_response_phrase',
                    'type' => 'text',
                    'label' => 'SHA Response Phrase',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
                [
                    'name' => 'sandbox_mode',
                    'type' => 'checkbox',
                    'label' => 'Sandbox Mode',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|boolean'
                ],
            ]
        ],

    ],
    'ivr' => [
        'ivr' => [
            'vendor_name' => 'Unifonic',
            'vendor_label' => 'ivr',
            'config' => [
                ThirdParty::CONFIG_EXECUTION_TYPE => ThirdPartyExecutionType::INTERNAL->value,
            ],
            'inputs' => [
                [
                    'name' => 'AppSid',
                    'type' => 'text',
                    'label' => 'AppSid',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],

                [
                    'name' => 'CallerId',
                    'type' => 'text',
                    'label' => 'CallerId',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string'
                ],
            ]
        ],
    ]
];
