<?php

use App\Enums\DataHistoryEnum;
use App\Enums\Roles;
use App\Exceptions\ExportCancelledException;
use App\Models\DataHistory;
use App\Models\Role;
use App\Models\User;


// ✅ function مساعدة
function wrap_permissions(object $obj): object
{
    foreach ($obj as $key => $val) {
        if (is_object($val) && isset($val->granted)) {
            // ✅ رجّع الـ granted مباشرة بدل الـ object
            $obj->$key = (int) $val->granted;
        } elseif (is_array($val) && isset($val['granted'])) {
            // ✅ نفس الحل للـ array
            $obj->$key = (int) $val['granted'];
        }
        // لو رقم خليه زي ما هو
    }
    return $obj;
}

// ✅ استبدل get_with_approve بالـ function دي
/**
 * يرجع ['granted' => int, 'with_approve' => int] لصلاحية معينة لمستخدم معين.
 * بتدعم الـ structure القديمة (= 0 أو 1) والجديدة ({"granted": .., "with_approve": ..}) مع بعض.
 */
/**
 * يرجع كل قيم الصلاحية (granted + أي مفاتيح زيادة زي with_approve, show_all, ...)
 * بشكل ديناميكي بدون التقيد بـ with_approve بس.
 */
function get_permission_value(string $path, string $permission, $user): array
{
    $userPermissions = $user->permission->first();

    if (!$userPermissions) {
        return ['granted' => 0];
    }

    $pathValue = $userPermissions->{$path} ?? '{}';
    $decoded = is_array($pathValue) ? $pathValue : (json_decode($pathValue, true) ?? []);

    $val = $decoded[$permission] ?? null;

    if (is_array($val)) {
        // رجّع كل المفاتيح زي ما هي، بعد التأكد إنها أرقام صحيحة
        $result = [];
        foreach ($val as $key => $v) {
            $result[$key] = (int) $v;
        }
        // تأكد إن granted موجودة دايماً
        $result['granted'] = $result['granted'] ?? 0;
        return $result;
    }

    if (is_numeric($val)) {
        return ['granted' => (int) $val];
    }

    return ['granted' => 0];
}

/**
 * helper سريع: هل المستخدم محتاج موافقة مشرف لهذا الـ action؟
 * يرجع true لو: مفيش صلاحية خالص، أو عنده صلاحية بس with_approve مفعّلة.
 */
function needs_supervisor_approval(string $path, string $permission, $user): bool
{
    $perm = get_permission_value($path, $permission, $user);
    return ($perm['granted'] ?? 0) === 0 || ($perm['with_approve'] ?? 0) === 1;
}
// ✅ استبدل الـ safe_json_decode القديمة بالجديدة
function safe_json_decode($value): ?object
{
    if (is_object($value)) {
        return wrap_permissions($value);
    }

    if (is_array($value)) {
        return wrap_permissions((object) $value);
    }

    if (is_string($value)) {
        $decoded = json_decode($value);
        $obj = is_object($decoded) ? $decoded
            : (is_array($decoded) ? (object) $decoded : null);
        return $obj ? wrap_permissions($obj) : null;
    }

    return null;
}


function perm(int $granted, int $with_approve = 0): array
{
    return ['granted' => $granted, 'with_approve' => $with_approve];
}
/**
 * Safely decode a value that may be a JSON string, array, or object.
 * Returns an object for arrow-syntax access (->property), or null on failure.
 */
// function safe_json_decode($value): ?object
// {
//     if (is_object($value)) {
//         return $value;
//     }

//     if (is_array($value)) {
//         return (object) $value;
//     }

//     if (is_string($value)) {
//         $decoded = json_decode($value);
//         return is_object($decoded) ? $decoded : (is_array($decoded) ? (object) $decoded : null);
//     }

//     return null;
// }

function isEmployee($user): bool
{
    if (! empty($user)) {
        return $user->tokenCan('employee');
    }

    return false;
}

function isSupervisor($user): bool
{
    if (! empty($user)) {
        return $user->tokenCan('supervisor');
    }

    return false;
}

function isSuperadmin($user): bool
{
    if (! empty($user)) {
        return $user->tokenCan('superadmin');
    }

    return false;
}

function setView($view)
{
    $view = 'livewire.' . $view;

    return (View::exists($view)) ? view($view) : abort(404);
}

function uiMessage($body, $type = '')
{
    return [
        'body' => $body,
        'type' => $type,
    ];
}

//function custom_policy($user, $condition)
//{
//    $is_authorized = $condition;
//    if (request()->is('api/*')) {
//        return json_decode($user->permission->first()->claims)->create_claim == 1 ? Response::allow()
//            : throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
//                'success' => false,
//                'message' => 'You have not any permission',
//            ], 200));
//    }
//
//    return $is_authorized;
//}

function custom_policy($user, $is_authorized)
{
    return $is_authorized;
}

function can_user_access_claim(?\App\Models\Claim $claim)
{
    $user = auth()->user();

    if (!$claim || !$user) {
        return false;
    }

    return $claim->newQuery()
        ->whereKey($claim->getKey())
        ->visibleTo($user)
        ->exists();
}

function is_user_delgated($claim)
{
    //    if(auth()->user()->role->id != Roles::COLLECTOR->value && auth()->user()->role->id != Roles::EXTERNAL_OFFICE->value) {
    //        return true;
    //    }
    //
    //    return $claim->assignee->to == auth()->user()->id && $claim->assignee->status == 1;

    $delegationList = Delegation::where('to', auth()->user())->where('is_active', 1)->pluck('from');

    return $claim->assignees->whereIn('to', $delegationList)->where('is_active', 1)->count() > 0;
}

function default_per($role)
{
    return [
        'claims' => json_encode([
            'create_claim' => perm(1),
            'show_claim' => perm(1),
            'update_claim' => perm(1),
            'delete_claim' => perm(1),
            'assign_claim' => perm(1),
            'approve_claim' => perm(1),
            'generate_discharge_letter' => perm(1),
            'canceled' => perm(1),
            'collected_out_of_system' => perm(1),
            'import' => perm(1),
            'export' => perm(1),
        ]),
        'claims_files' => json_encode([
            'create_claims_files' => perm(1),
            'show_claims_files' => perm(1),
            'delete_claims_files' => perm(1),
        ]),
        // 'payment_link' => json_encode([
        //     'create_payment_link' => perm(1),
        //     'update_payment_link' => perm(1),
        //     'activate_payment_link' => perm(1),
        //     'create_installment_payment_link' => perm(1),
        //     'update_installment_payment_link' => perm(1),
        // ]),
        //        'reject_claims' => json_encode([
        //            'create_reject_claims' => perm(1),
        //            'approve_reject_claims' => perm(1),
        //            'reject_reject_claims' => perm(1),
        //        ]),
        'settings' => json_encode([
            'show_settings' => perm(1),
            'create_settings' => perm(1),
            'update_settings' => perm(1),
            'delete_settings' => perm(1),
        ]),
        'third_parties' => json_encode([
            'create_third_parties' => perm(1),
            'update_third_parties' => perm(1),
            'delete_third_parties' => perm(1),
            'activate_third_parties' => perm(1),
        ]),
        'users' => json_encode([
            'create_users' => perm(1),
            'update_users' => perm(1),
            'show_users' => perm(1),
            //'activate_users' => perm(1),
            //'deactivate_users' => perm(1),
            'delete_users' => perm(1),
            //'auto_assign_users' => perm(1),
        ]),
        'permissions' => json_encode([
            //'create_permissions' => perm(1),
            //'show_permissions' => perm(1),
            'update_permissions' => perm(1),
        ]),
        'role' => json_encode([
            'create_role' => perm(1),
            'show_role' => perm(1),
            'update_role' => perm(1),
        ]),
        'transactions' => json_encode([
            'show_all_transactions' => perm(1),
        ]),
        'reports' => json_encode([
            'show_reports' => perm(1),
        ]),
        'payments' => json_encode([
            'delay_payments' => perm(1),
            'installment_payments' => perm(1),
        ]),
    ];
}

function default_permission($role_id)
{

    if (Roles::SUPER_ADMIN->value == intval($role_id)) {
        return default_permission_for_super_admin();
    } elseif (Roles::SUPERVISOR->value == intval($role_id)) {
        return default_permission_for_supervisor();
    } elseif (Roles::COLLECTOR->value == intval($role_id)) {
        return default_permission_for_collector();
    } elseif (Roles::SENIOR_COLLECTOR->value == intval($role_id)) {
        return default_permission_for_senior_collector();
    } else {
        return default_permission_for_external_office();
    }
}

function permissions($role = null)
{
    $per_arry = [
        'claims' => [
            [
                'key' => 'create_claim',
                'label' => 'create_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'show_claim',
                'label' => 'show_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'update_claim',
                'label' => 'update_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'assign_claim',
                'label' => 'assign_reassign',
                'value' => perm(1),
            ],
            [
                'key' => 'approve_claim',
                'label' => 'approve_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'generate_discharge_letter',
                'label' => 'generate_download_discharge_letter',
                'value' => perm(1),
            ],
            [
                'key' => 'canceled',
                'label' => 'canceled_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'collected_out_of_system',
                'label' => 'collected_out_of_system_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'import',
                'label' => 'import_claim',
                'value' => perm(1),
            ],
            [
                'key' => 'export',
                'label' => 'export_claim',
                'value' => perm(1),
            ],
        ],

        'claims_files' => [
            [
                'key' => 'create_claims_files',
                'label' => 'create_claims_files',
                'value' => perm(1),
            ],
            [
                'key' => 'show_claims_files',
                'label' => 'show_claims_files',
                'value' => perm(1),
            ],
            [
                'key' => 'delete_claims_files',
                'label' => 'delete_claims_files',
                'value' => perm(1),
            ],
        ],

        // 'payment_link' => [
        //     [
        //         'key' => 'create_payment_link',
        //         'label' => 'create_payment_link',
        //         'value' => perm(1),
        //     ],
        //     [
        //         'key' => 'update_payment_link',
        //         'label' => 'update_payment_link',
        //         'value' => perm(1),
        //     ],
        //     [
        //         'key' => 'activate_payment_link',
        //         'label' => 'activate_payment_link',
        //         'value' => perm(1),
        //     ],
        //     [
        //         'key' => 'create_installment_payment_link',
        //         'label' => 'create_installment_payment_link',
        //         'value' => perm(1),
        //     ],
        //     [
        //         'key' => 'update_installment_payment_link',
        //         'label' => 'update_installment_payment_link',
        //         'value' => perm(1),
        //     ],
        // ],

        'settings' => [
            [
                'key' => 'show_settings',
                'label' => 'show_settings',
                'value' => perm(1),
            ],
            [
                'key' => 'create_settings',
                'label' => 'create_settings',
                'value' => perm(1),
            ],
            [
                'key' => 'update_settings',
                'label' => 'update_settings',
                'value' => perm(1),
            ],
            [
                'key' => 'delete_settings',
                'label' => 'delete_settings',
                'value' => perm(1),
            ],
        ],

        'third_parties' => [
            [
                'key' => 'create_third_parties',
                'label' => 'create_third_parties',
                'value' => perm(1),
            ],
            [
                'key' => 'update_third_parties',
                'label' => 'update_third_parties',
                'value' => perm(1),
            ],
            [
                'key' => 'delete_third_parties',
                'label' => 'delete_third_parties',
                'value' => perm(1),
            ],
            [
                'key' => 'activate_third_parties',
                'label' => 'activate_third_parties',
                'value' => perm(1),
            ],
        ],
        'permissions' => [
            // [
            //     'key' => 'create_permissions',
            //     'label' => 'create_permissions',
            //     'value' => perm(1),
            // ],

            // [
            //     'key' => 'show_permissions',
            //     'label' => 'show_permissions',
            //     'value' => perm(1),
            // ],
            [
                'key' => 'update_permissions',
                'label' => 'update_permissions',
                'value' => perm(1),
            ],
        ],

        'users' => [
            [
                'key' => 'create_users',
                'label' => 'create_users',
                'value' => perm(1),
            ],
            [
                'key' => 'update_users',
                'label' => 'update_users',
                'value' => perm(1),
            ],
            [
                'key' => 'show_users',
                'label' => 'show_users',
                'value' => perm(1),
            ],
            // [
            //     'key' => 'activate_users',
            //     'label' => 'activate_users',
            //     'value' => perm(1),
            // ],
            // [
            //     'key' => 'deactivate_users',
            //     'label' => 'deactivate_users',
            //     'value' => perm(1),
            // ],
            [
                'key' => 'delete_users',
                'label' => 'delete_users',
                'value' => perm(1),
            ],
            // [
            //     'key' => 'auto_assign_users',
            //     'label' => 'auto_assign_users',
            //     'value' => perm(1),
            // ],
        ],

        'role' => [
            [
                'key' => 'create_role',
                'label' => 'create_role',
                'value' => perm(1),
            ],
            [
                'key' => 'show_role',
                'label' => 'show_role',
                'value' => perm(1),
            ],
            [
                'key' => 'update_role',
                'label' => 'update_role',
                'value' => perm(1),
            ],
        ],

        'transactions' => [
            [
                'key' => 'show_all_transactions',
                'label' => 'show_all_transactions',
                'value' => perm(1),
            ],
        ],

        'reports' => [
            [
                'key' => 'show_reports',
                'label' => 'show_reports',
                'value' => perm(1),
            ],
        ],
        'payments' => [
            [
                'key' => 'delay_payments',
                'label' => 'delay_payments',
                'value' => perm(1),
            ],
            [
                'key' => 'installment_payments',
                'label' => 'installment_payments',
                'value' => perm(1),
            ],

        ],
    ];

    return ($role) ? $per_arry[$role] : $per_arry;
}

function default_permission_for_collector()
{
    try {
        $role = Role::findOrFail(Roles::COLLECTOR->value);
        if ($role->default_permissions == null) {
            $role->default_permissions = json_encode([
                'claims' => json_encode([
                    'create_claim' => perm(1),
                    'show_claim' => perm(1),
                    'update_claim' => perm(1),
                    'assign_claim' => perm(0),
                    'approve_claim' => perm(1),
                    'generate_discharge_letter' => perm(1),
                    'canceled' => perm(1),
                    'collected_out_of_system' => perm(1),
                    'import' => perm(0),
                    'export' => perm(0),
                ]),
                'claims_files' => json_encode([
                    'create_claims_files' => perm(1),
                    'show_claims_files' => perm(1),
                    'delete_claims_files' => perm(1),
                ]),
                // 'payment_link' => json_encode([
                //     'create_payment_link' => perm(1),
                //     'update_payment_link' => perm(1),
                //     'activate_payment_link' => perm(1),
                //     'create_installment_payment_link' => perm(1),
                //     'update_installment_payment_link' => perm(1),
                // ]),
                'settings' => json_encode([
                    'show_settings'   => perm(0),
                    'create_settings' => perm(0),
                    'update_settings' => perm(0),
                    'delete_settings' => perm(0),
                ]),
                'third_parties' => json_encode([
                    'create_third_parties' => perm(0),
                    'update_third_parties' => perm(0),
                    'delete_third_parties' => perm(0),
                    'activate_third_parties' => perm(0),
                ]),
                'users' => json_encode([
                    'create_users' => perm(0),
                    'update_users' => perm(0),
                    'show_users' => perm(0),
                    // 'activate_users' => perm(0),
                    // 'deactivate_users' => perm(0),
                    'delete_users' => perm(0),
                    //'auto_assign_users' => perm(0),
                ]),
                'permissions' => json_encode([
                    //'create_permissions' => perm(0),
                    //'show_permissions' => perm(0),
                    'update_permissions' => perm(0),
                ]),
                'role' => json_encode([
                    'create_role' => perm(0),
                    'show_role' => perm(0),
                    'update_role' => perm(0),
                ]),
                'transactions' => json_encode([
                    'show_all_transactions' => perm(0),
                ]),
                'reports' => json_encode([
                    'show_reports' => perm(0),
                ]),
                'payments' => json_encode([
                    'delay_payments' => perm(1),
                    'installment_payments' => perm(1),
                ]),
            ]);
            $role->save();
        }

        return json_decode($role->default_permissions, true);
    } catch (Exception $exception) {
        return
            [
                'claims' => json_encode([
                    'create_claim' => perm(1),
                    'show_claim' => perm(1),
                    'update_claim' => perm(1),
                    'assign_claim' => perm(0),
                    'approve_claim' => perm(1),
                    'generate_discharge_letter' => perm(1),
                    'canceled' => perm(1),
                    'import' => perm(0),
                    'export' => perm(0),
                ]),
                'claims_files' => json_encode([
                    'create_claims_files' => perm(1),
                    'show_claims_files' => perm(1),
                    'delete_claims_files' => perm(1),
                ]),
                // 'payment_link' => json_encode([
                //     'create_payment_link' => perm(1),
                //     'update_payment_link' => perm(1),
                //     'activate_payment_link' => perm(1),
                //     'create_installment_payment_link' => perm(1),
                //     'update_installment_payment_link' => perm(1),
                // ]),
                'settings' => json_encode([
                    'show_settings'   => perm(0),
                    'create_settings' => perm(0),
                    'update_settings' => perm(0),
                    'delete_settings' => perm(0),
                ]),
                'third_parties' => json_encode([
                    'create_third_parties' => perm(0),
                    'update_third_parties' => perm(0),
                    'delete_third_parties' => perm(0),
                    'activate_third_parties' => perm(0),
                ]),
                'users' => json_encode([
                    'create_users' => perm(0),
                    'update_users' => perm(0),
                    'show_users' => perm(0),
                    // 'activate_users' => perm(0),
                    // 'deactivate_users' => perm(0),
                    'delete_users' => perm(0),
                    //'auto_assign_users' => perm(0),
                ]),
                'permissions' => json_encode([
                    //'create_permissions' => perm(0),
                    //'show_permissions' => perm(0),
                    'update_permissions' => perm(0),
                ]),
                'role' => json_encode([
                    'create_role' => perm(0),
                    'show_role' => perm(0),
                    'update_role' => perm(0),
                ]),
                'transactions' => json_encode([
                    'show_all_transactions' => perm(0),
                ]),
                'reports' => json_encode([
                    'show_reports' => perm(0),
                ]),
                'payments' => json_encode([
                    'delay_payments' => perm(1),
                    'installment_payments' => perm(1),
                ]),
            ];
    }
}

function default_permission_for_external_office()
{
    try {
        $role = Role::findOrFail(Roles::EXTERNAL_OFFICE->value);
        if ($role->default_permissions == null) {
            $role->default_permissions = json_encode(
                [
                    'claims' => json_encode([
                        'create_claim' => perm(1),
                        'show_claim' => perm(1),
                        'update_claim' => perm(1),
                        'assign_claim' => perm(0),
                        'approve_claim' => perm(1),
                        'generate_discharge_letter' => perm(1),
                        'canceled' => perm(1),
                        'collected_out_of_system' => perm(1),
                        'import' => perm(0),
                        'export' => perm(0),
                    ]),
                    'claims_files' => json_encode([
                        'create_claims_files' => perm(1),
                        'show_claims_files' => perm(1),
                        'delete_claims_files' => perm(1),
                    ]),
                    // 'payment_link' => json_encode([
                    //     'create_payment_link' => perm(1),
                    //     'update_payment_link' => perm(1),
                    //     'activate_payment_link' => perm(1),
                    //     'create_installment_payment_link' => perm(1),
                    //     'update_installment_payment_link' => perm(1),
                    // ]),
                    // 'reject_claims' => json_encode([
                    //     'create_reject_claims' => perm(0),
                    //     'approve_reject_claims' => perm(0),
                    //     'reject_reject_claims' => perm(0),
                    // ]),
                    'settings' => json_encode([
                        'show_settings'   => perm(0),
                        'create_settings' => perm(0),
                        'update_settings' => perm(0),
                        'delete_settings' => perm(0),
                    ]),
                    'third_parties' => json_encode([
                        'create_third_parties' => perm(0),
                        'update_third_parties' => perm(0),
                        'delete_third_parties' => perm(0),
                        'activate_third_parties' => perm(0),
                    ]),
                    'users' => json_encode([
                        'create_users' => perm(0),
                        'update_users' => perm(0),
                        'show_users' => perm(0),
                        // 'activate_users' => perm(0),
                        // 'deactivate_users' => perm(0),
                        'delete_users' => perm(0),
                        //'auto_assign_users' => perm(0),
                    ]),
                    'permissions' => json_encode([
                        //'create_permissions' => perm(0),
                        //'show_permissions' => perm(0),
                        'update_permissions' => perm(0),
                    ]),
                    'role' => json_encode([
                        'create_role' => perm(0),
                        'show_role' => perm(0),
                        'update_role' => perm(0),
                    ]),
                    'transactions' => json_encode([
                        'show_all_transactions' => perm(0),
                    ]),
                    'reports' => json_encode([
                        'show_reports' => perm(0),
                    ]),
                    'payments' => json_encode([
                        'delay_payments' => perm(1),
                        'installment_payments' => perm(1),
                    ]),
                ]
            );
            $role->save();
        }

        return json_decode($role->default_permissions, true);
    } catch (Exception $exception) {
        return [
            'claims' => json_encode([
                'create_claim' => perm(1),
                'show_claim' => perm(1),
                'update_claim' => perm(1),
                'assign_claim' => perm(0),
                'approve_claim' => perm(1),
                'generate_discharge_letter' => perm(1),
                'canceled' => perm(1),
                'import' => perm(0),
                'export' => perm(0),
            ]),
            'claims_files' => json_encode([
                'create_claims_files' => perm(1),
                'show_claims_files' => perm(1),
                'delete_claims_files' => perm(1),
            ]),
            // 'payment_link' => json_encode([
            //     'create_payment_link' => perm(1),
            //     'update_payment_link' => perm(1),
            //     'activate_payment_link' => perm(1),
            //     'create_installment_payment_link' => perm(1),
            //     'update_installment_payment_link' => perm(1),
            // ]),
            'settings' => json_encode([
                'show_settings'   => perm(0),
                'create_settings' => perm(0),
                'update_settings' => perm(0),
                'delete_settings' => perm(0),
            ]),
            'third_parties' => json_encode([
                'create_third_parties' => perm(0),
                'update_third_parties' => perm(0),
                'delete_third_parties' => perm(0),
                'activate_third_parties' => perm(0),
            ]),
            'users' => json_encode([
                'create_users' => perm(0),
                'update_users' => perm(0),
                'show_users' => perm(0),
                // 'activate_users' => perm(0),
                // 'deactivate_users' => perm(0),
                'delete_users' => perm(0),
                //'auto_assign_users' => perm(0),
            ]),
            'permissions' => json_encode([
                //'create_permissions' => perm(0),
                //'show_permissions' => perm(0),
                'update_permissions' => perm(0),
            ]),
            'role' => json_encode([
                'create_role' => perm(0),
                'show_role' => perm(0),
                'update_role' => perm(0),
            ]),
            'transactions' => json_encode([
                'show_all_transactions' => perm(0),
            ]),
            'reports' => json_encode([
                'show_reports' => perm(0),
            ]),
            'payments' => json_encode([
                'delay_payments' => perm(1),
                'installment_payments' => perm(1),
            ]),
        ];
    }
}

function default_permission_for_senior_collector()
{
    try {
        $role = Role::findOrFail(Roles::SENIOR_COLLECTOR->value);
        if ($role->default_permissions == null) {
            $role->default_permissions = json_encode([
                'claims' => json_encode([
                    'create_claim' => perm(1),
                    'show_claim' => perm(1),
                    'update_claim' => perm(1),
                    'assign_claim' => perm(1),
                    'approve_claim' => perm(1),
                    'generate_discharge_letter' => perm(1),
                    'canceled' => perm(1),
                    'collected_out_of_system' => perm(1),
                    'import' => perm(1),
                    'export' => perm(1),
                ]),
                'claims_files' => json_encode([
                    'create_claims_files' => perm(1),
                    'show_claims_files' => perm(1),
                    'delete_claims_files' => perm(1),
                ]),
                // 'payment_link' => json_encode([
                //     'create_payment_link' => perm(1),
                //     'update_payment_link' => perm(1),
                //     'activate_payment_link' => perm(1),
                //     'create_installment_payment_link' => perm(1),
                //     'update_installment_payment_link' => perm(1),
                // ]),
                'settings' => json_encode([
                    'show_settings'   => perm(0),
                    'create_settings' => perm(0),
                    'update_settings' => perm(0),
                    'delete_settings' => perm(0),
                ]),
                'third_parties' => json_encode([
                    'create_third_parties' => perm(0),
                    'update_third_parties' => perm(0),
                    'delete_third_parties' => perm(0),
                    'activate_third_parties' => perm(0),
                ]),
                'users' => json_encode([
                    'create_users' => perm(0),
                    'update_users' => perm(0),
                    'show_users' => perm(1),
                    // 'activate_users' => perm(0),
                    // 'deactivate_users' => perm(0),
                    'delete_users' => perm(0),
                    //'auto_assign_users' => perm(0),
                ]),
                'permissions' => json_encode([
                    //'create_permissions' => perm(0),
                    //'show_permissions' => perm(0),
                    'update_permissions' => perm(0),
                ]),
                'role' => json_encode([
                    'create_role' => perm(0),
                    'show_role' => perm(0),
                    'update_role' => perm(0),
                ]),
                'transactions' => json_encode([
                    'show_all_transactions' => perm(0),
                ]),
                'reports' => json_encode([
                    'show_reports' => perm(0),
                ]),
                'payments' => json_encode([
                    'delay_payments' => perm(1),
                    'installment_payments' => perm(1),
                ]),
            ]);
            $role->save();
        }

        return json_decode($role->default_permissions, true);
    } catch (Exception $exception) {
        return
            [
                'claims' => json_encode([
                    'create_claim' => perm(1),
                    'show_claim' => perm(1),
                    'update_claim' => perm(1),
                    'assign_claim' => perm(1),
                    'approve_claim' => perm(1),
                    'generate_discharge_letter' => perm(1),
                    'canceled' => perm(1),
                    'import' => perm(1),
                    'export' => perm(1),
                ]),
                'claims_files' => json_encode([
                    'create_claims_files' => perm(1),
                    'show_claims_files' => perm(1),
                    'delete_claims_files' => perm(1),
                ]),
                // 'payment_link' => json_encode([
                //     'create_payment_link' => perm(1),
                //     'update_payment_link' => perm(1),
                //     'activate_payment_link' => perm(1),
                //     'create_installment_payment_link' => perm(1),
                //     'update_installment_payment_link' => perm(1),
                // ]),
                'settings' => json_encode([
                    'show_settings'   => perm(0),
                    'create_settings' => perm(0),
                    'update_settings' => perm(0),
                    'delete_settings' => perm(0),
                ]),
                'third_parties' => json_encode([
                    'create_third_parties' => perm(0),
                    'update_third_parties' => perm(0),
                    'delete_third_parties' => perm(0),
                    'activate_third_parties' => perm(0),
                ]),
                'users' => json_encode([
                    'create_users' => perm(0),
                    'update_users' => perm(0),
                    'show_users' => perm(1),
                    // 'activate_users' => perm(0),
                    // 'deactivate_users' => perm(0),
                    'delete_users' => perm(0),
                    //'auto_assign_users' => perm(0),
                ]),
                'permissions' => json_encode([
                    //'create_permissions' => perm(0),
                    //'show_permissions' => perm(0),
                    'update_permissions' => perm(0),
                ]),
                'role' => json_encode([
                    'create_role' => perm(0),
                    'show_role' => perm(0),
                    'update_role' => perm(0),
                ]),
                'transactions' => json_encode([
                    'show_all_transactions' => perm(0),
                ]),
                'reports' => json_encode([
                    'show_reports' => perm(0),
                ]),
                'payments' => json_encode([
                    'delay_payments' => perm(1),
                    'installment_payments' => perm(1),
                ]),
            ];
    }
}

function default_permission_for_supervisor()
{
    try {
        $role = Role::findOrFail(Roles::SUPERVISOR->value);
        if ($role->default_permissions == null) {
            $role->default_permissions = json_encode(
                [
                    'claims' => json_encode([
                        'create_claim' => perm(1),
                        'show_claim' => perm(1),
                        'update_claim' => perm(1),
                        'assign_claim' => perm(1),
                        'approve_claim' => perm(1),
                        'generate_discharge_letter' => perm(1),
                        'canceled' => perm(1),
                        'collected_out_of_system' => perm(1),
                        'import' => perm(1),
                        'export' => perm(1),
                    ]),
                    'claims_files' => json_encode([
                        'create_claims_files' => perm(1),
                        'show_claims_files' => perm(1),
                        'delete_claims_files' => perm(1),
                    ]),
                    // 'payment_link' => json_encode([
                    //     'create_payment_link' => perm(1),
                    //     'update_payment_link' => perm(1),
                    //     'activate_payment_link' => perm(1),
                    //     'create_installment_payment_link' => perm(1),
                    //     'update_installment_payment_link' => perm(1),
                    // ]),
                    'settings' => json_encode([
                        'show_settings'   => perm(1),
                        'create_settings' => perm(1),
                        'update_settings' => perm(1),
                        'delete_settings' => perm(1),
                    ]),
                    'third_parties' => json_encode([
                        'create_third_parties' => perm(0),
                        'update_third_parties' => perm(0),
                        'delete_third_parties' => perm(0),
                        'activate_third_parties' => perm(0),
                    ]),
                    'users' => json_encode([
                        'create_users' => perm(1),
                        'update_users' => perm(1),
                        'show_users' => perm(1),
                        // 'activate_users' => perm(1),
                        // 'deactivate_users' => perm(1),
                        'delete_users' => perm(1),
                        //'auto_assign_users' => perm(1),
                    ]),
                    'permissions' => json_encode([
                        //'create_permissions' => perm(1),
                        //'show_permissions' => perm(1),
                        'update_permissions' => perm(1),
                    ]),
                    'role' => json_encode([
                        'create_role' => perm(1),
                        'show_role' => perm(1),
                        'update_role' => perm(1),
                    ]),
                    'transactions' => json_encode([
                        'show_all_transactions' => perm(1),
                    ]),
                    'reports' => json_encode([
                        'show_reports' => perm(1),
                    ]),
                    'payments' => json_encode([
                        'delay_payments' => perm(1),
                        'installment_payments' => perm(1),
                    ]),
                ]
            );
            $role->save();
        }

        return json_decode($role->default_permissions, true);
    } catch (Exception $exception) {
        return [
            'claims' => json_encode([
                'create_claim' => perm(1),
                'show_claim' => perm(1),
                'update_claim' => perm(1),
                'assign_claim' => perm(1),
                'approve_claim' => perm(1),
                'generate_discharge_letter' => perm(1),
                'canceled' => perm(1),
                'import' => perm(1),
                'export' => perm(1),
            ]),
            'claims_files' => json_encode([
                'create_claims_files' => perm(1),
                'show_claims_files' => perm(1),
                'delete_claims_files' => perm(1),
            ]),
            // 'payment_link' => json_encode([
            //     'create_payment_link' => perm(1),
            //     'update_payment_link' => perm(1),
            //     'activate_payment_link' => perm(1),
            //     'create_installment_payment_link' => perm(1),
            //     'update_installment_payment_link' => perm(1),
            // ]),
            'settings' => json_encode([
                'show_settings'   => perm(1),
                'create_settings' => perm(1),
                'update_settings' => perm(1),
                'delete_settings' => perm(1),
            ]),
            'third_parties' => json_encode([
                'create_third_parties' => perm(0),
                'update_third_parties' => perm(0),
                'delete_third_parties' => perm(0),
                'activate_third_parties' => perm(0),
            ]),
            'users' => json_encode([
                'create_users' => perm(1),
                'update_users' => perm(1),
                'show_users' => perm(1),
                // 'activate_users' => perm(1),
                // 'deactivate_users' => perm(1),
                'delete_users' => perm(1),
                //'auto_assign_users' => perm(1),
            ]),
            'permissions' => json_encode([
                //'create_permissions' => perm(1),
                //'show_permissions' => perm(1),
                'update_permissions' => perm(1),
            ]),
            'role' => json_encode([
                'create_role' => perm(1),
                'show_role' => perm(1),
                'update_role' => perm(1),
            ]),
            'transactions' => json_encode([
                'show_all_transactions' => perm(1),
            ]),
            'reports' => json_encode([
                'show_reports' => perm(1),
            ]),
            'payments' => json_encode([
                'delay_payments' => perm(1),
                'installment_payments' => perm(1),
            ]),
        ];
    }
}

function default_permission_for_super_admin()
{
    try {
        $role = Role::findOrFail(Roles::SUPER_ADMIN->value);
        if ($role->default_permissions == null) {
            $role->default_permissions = json_encode(
                [
                    'claims' => json_encode([
                        'create_claim' => perm(1),
                        'show_claim' => perm(1),
                        'update_claim' => perm(1),
                        'assign_claim' => perm(0),
                        'approve_claim' => perm(1),
                        'generate_discharge_letter' => perm(1),
                        'canceled' => perm(1),
                        'collected_out_of_system' => perm(1),
                        'import' => perm(1),
                        'export' => perm(1),
                    ]),
                    'claims_files' => json_encode([
                        'create_claims_files' => perm(1),
                        'show_claims_files' => perm(1),
                        'delete_claims_files' => perm(1),
                    ]),
                    // 'payment_link' => json_encode([
                    //     'create_payment_link' => perm(1),
                    //     'update_payment_link' => perm(1),
                    //     'activate_payment_link' => perm(1),
                    //     'create_installment_payment_link' => perm(1),
                    //     'update_installment_payment_link' => perm(1),
                    // ]),
                    'settings' => json_encode([
                        'show_settings'   => perm(1),
                        'create_settings' => perm(1),
                        'update_settings' => perm(1),
                        'delete_settings' => perm(1),
                    ]),
                    'third_parties' => json_encode([
                        'create_third_parties' => perm(1),
                        'update_third_parties' => perm(1),
                        'delete_third_parties' => perm(1),
                        'activate_third_parties' => perm(1),
                    ]),
                    'users' => json_encode([
                        'create_users' => perm(1),
                        'update_users' => perm(1),
                        'show_users' => perm(1),
                        // 'activate_users' => perm(1),
                        // 'deactivate_users' => perm(1),
                        'delete_users' => perm(1),
                        //'auto_assign_users' => perm(1),
                    ]),
                    'permissions' => json_encode([
                        //'create_permissions' => perm(1),
                        //'show_permissions' => perm(1),
                        'update_permissions' => perm(1),
                    ]),
                    'role' => json_encode([
                        'create_role' => perm(1),
                        'show_role' => perm(1),
                        'update_role' => perm(1),
                    ]),
                    'transactions' => json_encode([
                        'show_all_transactions' => perm(1),
                    ]),
                    'reports' => json_encode([
                        'show_reports' => perm(1),
                    ]),
                    'payments' => json_encode([
                        'delay_payments' => perm(1),
                        'installment_payments' => perm(1),
                    ]),
                ]
            );
            $role->save();
        }

        return json_decode($role->default_permissions, true);
    } catch (Exception $exception) {
        return [
            'claims' => json_encode([
                'create_claim' => perm(1),
                'show_claim' => perm(1),
                'update_claim' => perm(1),
                'assign_claim' => perm(1),
                'approve_claim' => perm(1),
                'generate_discharge_letter' => perm(1),
                'canceled' => perm(1),
                'import' => perm(1),
                'export' => perm(1),
            ]),
            'claims_files' => json_encode([
                'create_claims_files' => perm(1),
                'show_claims_files' => perm(1),
                'delete_claims_files' => perm(1),
            ]),
            // 'payment_link' => json_encode([
            //     'create_payment_link' => perm(1),
            //     'update_payment_link' => perm(1),
            //     'activate_payment_link' => perm(1),
            //     'create_installment_payment_link' => perm(1),
            //     'update_installment_payment_link' => perm(1),
            // ]),
            'settings' => json_encode([
                'show_settings'   => perm(1),
                'create_settings' => perm(1),
                'update_settings' => perm(1),
                'delete_settings' => perm(1),
            ]),
            'third_parties' => json_encode([
                'create_third_parties' => perm(1),
                'update_third_parties' => perm(1),
                'delete_third_parties' => perm(1),
                'activate_third_parties' => perm(1),
            ]),
            'users' => json_encode([
                'create_users' => perm(1),
                'update_users' => perm(1),
                'show_users' => perm(1),
                // 'activate_users' => perm(1),
                // 'deactivate_users' => perm(1),
                'delete_users' => perm(1),
                //'auto_assign_users' => perm(1),
            ]),
            'permissions' => json_encode([
                //'create_permissions' => perm(1),
                //'show_permissions' => perm(1),
                'update_permissions' => perm(1),
            ]),
            'role' => json_encode([
                'create_role' => perm(1),
                'show_role' => perm(1),
                'update_role' => perm(1),
            ]),
            'transactions' => json_encode([
                'show_all_transactions' => perm(1),
            ]),
            'reports' => json_encode([
                'show_reports' => perm(1),
            ]),
            'payments' => json_encode([
                'delay_payments' => perm(1),
                'installment_payments' => perm(1),
            ]),
        ];
    }
}

/*
 * "claims" => [
            [
                "key" => "create_claim",
                "label" => "create_claim",
                "value" => 1
            ],
 */
function add_new_permission($permission, $category_name)
{
    $permissions_array = permissions();
    $permission_category = json_decode($permissions_array[$category_name]);
    $permission_category[] = $permission;

    $permission[$category_name] = json_encode($permission_category);

    return $permission;
}

function add_new_permission_category($category_name)
{
    $permissions_array = permissions();
    $permissions_array[$category_name] = json_encode([]);

    return $permissions_array;
}

function getCompanyLogo()
{
    if (config('app.taheiya_demo_mode') == true) {
        return asset('RCP.svg');
    } elseif (config('app.client_name') == \App\Enums\Clients::GIG->value) {
        return asset('GIG.png');
    } elseif (config('app.client_name') == \App\Enums\Clients::MIC->value) {
        return asset('MIC.svg');
    } elseif (config('app.client_name') == \App\Enums\Clients::GGI->value ) {
        return asset('logo.png'); 
    } elseif (config('app.client_name') == \App\Enums\Clients::ART->value) {
        return asset('ART.svg');
    }
}

function getCompanyLogoName()
{
    if (config('app.taheiya_demo_mode') == true) {
        return 'RCP.svg';
    } elseif (config('app.client_name') == \App\Enums\Clients::GIG->value) {
        return 'GIG.png';
    } elseif (config('app.client_name') == \App\Enums\Clients::MIC->value) {
        return 'MIC.png';
    } elseif (config('app.client_name') == \App\Enums\Clients::GGI->value) {
        return 'logo-new.png';
    } elseif (config('app.client_name') == \App\Enums\Clients::ART->value) {
        return 'ART.svg';
    }
}

function getCompanyStampName()
{
    if (config('app.client_name') == \App\Enums\Clients::ART->value) {
        return 'art_stamp.png';
    } else {
        return '';
    }
}

function hasMessages()
{
    if (config('app.client_name') == \App\Enums\Clients::GIG->value) {
        return false;
    }

    return true;
}

function getTechSupportCompanyId(): int
{
    if (config('app.client_name') == \App\Enums\Clients::GIG->value) {
        return 1;
    } elseif (config('app.client_name') == \App\Enums\Clients::MIC->value) {
        return 2;
    } elseif (config('app.client_name') == \App\Enums\Clients::GGI->value) {
        return 3;
    } elseif (config('app.client_name') == \App\Enums\Clients::ART->value) {
        return 4;
    }

    return 0;
}

function techSupportUrl(): string
{
    return 'https://techsupport.taheiya.sa/api/redirect-in/';
}


/**
 * يتحقق من DataHistory — لو CANCELLED يرمي exception لوقف الـ chunk loop.
 * يُستدعى مرة واحدة في بداية كل chunk (مش على كل row).
 */
function abortIfCancelled($ref)
{
    $status = DataHistory::where('ref', $ref)
        ->value('status');

    if ($status === DataHistoryEnum::CANCELLED->value) {
        throw new ExportCancelledException();
    }
}


/**
 * Generates a cryptographically-random alphanumeric code of the given length,
 * guaranteed to be unique within the given table/column by retrying on collision.
 *
 * Example usage:
 *   $link = generateUniqueCode(PaymentLink::class, 'link', 7);
 *
 * @param  string  $modelClass   Fully-qualified Eloquent model class, e.g. PaymentLink::class
 * @param  string  $column       Column to check uniqueness against, e.g. 'link'
 * @param  int     $length       Code length (default 7)
 * @param  int     $maxAttempts  Safety cap to avoid an infinite loop in a pathological case
 * @return string
 *
 * @throws \RuntimeException if no unique code could be generated within $maxAttempts
 */
function generateUniqueCode(string $modelClass, string $column, int $length = 7, int $maxAttempts = 20): string
{
    $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = strlen($charset) - 1;
 
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $charset[random_int(0, $max)];
        }
 
        // Check uniqueness against the database — this is what actually
        // guarantees no duplicate, not the randomness itself.
        $exists = $modelClass::where($column, $code)->exists();
 
        if (! $exists) {
            return $code;
        }
    }
 
    // Extremely unlikely to ever be reached at 62^7 possibilities,
    // but fail loudly instead of silently returning a duplicate.
    throw new \RuntimeException("Could not generate a unique {$column} after {$maxAttempts} attempts.");
}