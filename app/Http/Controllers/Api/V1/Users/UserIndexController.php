<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Enums\DataHistoryEnum;
use App\Enums\Roles;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ExportUsersRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\Users\UserResource;
use App\Models\DataHistory;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class UserIndexController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    public function index(ExportUsersRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $users = $this->getPaginatedUsers($request->filters())->paginate($perPage);

        return response()->json(
            new Collection($users, 'users', JsonResource::class)
        );
    }
    public function openSpoutExportUsers(ExportUsersRequest $request)
    {
        $fileName = 'users_export_' . time() . '.xlsx';
        
        $filters = $request->filters();
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_USERS->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportUsersOpenSpout::dispatch(user:auth()->user(), fileName: $fileName, historyRef: $history->ref, columns: $request->columns() ?? [], filters: $filters);

       return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');

       
    }

    // 
    public function listUsersInfo(ExportUsersRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $users = $this->getPaginatedUsers($request->filters())->paginate($perPage);
        return response()->json(
            new Collection($users, 'users', UserResource::class)
        );
    }


    public function trashed(ExportUsersRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $users = $this->getPaginatedUsers($request->filters(), onlyTrashed: true)->paginate($perPage);

        return response()->json(
            new Collection($users, 'trashed users', JsonResource::class)
        );
    }
        public function openSpoutExportTrashedUsers(ExportUsersRequest $request)
    {
        $fileName = 'trashed_users_export_' . time() . '.xlsx';
        
        $filters = $request->filters();
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_TRASHED_USERS->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportUsersOpenSpout::dispatch(user:auth()->user(), fileName: $fileName, historyRef: $history->ref, columns: $request->columns() ?? [], filters: $filters, onlyTrashed: true);

       return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');

       
    }

    private function getPaginatedUsers($filters, bool $onlyTrashed = false)
    {
        $query = $onlyTrashed
            ? User::onlyTrashed()->filter($filters)->with(['role'])
            : User::filter($filters)->with(['role']);

        return $query->orderBy('name');
    }
}
