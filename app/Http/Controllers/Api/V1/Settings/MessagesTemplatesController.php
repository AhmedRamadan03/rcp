<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Requests\V1\Settings\CreateRequests\CustomTemplateStoreRequest;
use App\Http\Requests\V1\Settings\UpdateRequests\CustomTemplateUpdateRequest;
use App\Http\Requests\V1\Settings\UpdateRequests\CustomTemplateStatusToggleRequest;
use App\Http\Resources\Api\V1\Settings\CustomTemplateResource;
use App\Http\Resources\Api\V1\Settings\MessageTemplateResource;
use App\Services\Settings\MessagesTemplatesService;
use App\Enums\CustomTemplateStatus;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MessagesTemplatesController extends Controller
{
    use ApiResponse;

    protected MessagesTemplatesService $messagesTemplatesService;

    public function __construct(MessagesTemplatesService $messagesTemplatesService)
    {
        $this->messagesTemplatesService = $messagesTemplatesService;
    }

    /**
     * Get all message templates
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {

            $perPage = $request->integer('per_page', 20);
            $templates = $this->messagesTemplatesService->listAllTemplates($perPage);

            return $this->successResponse(
                'Message templates retrieved successfully',
                MessageTemplateResource::collection($templates)
            );

        } catch (\Exception $e) {
            return $this->apiErrorResponse(
                'Failed to retrieve message templates: ' . $e->getMessage()
            );
        }

    }


    public function show($id)
    {
        try {
            $customTemplate = $this->messagesTemplatesService->findCustomTemplateWithVariables($id);
            return $this->successResponse(
                'Custom template retrieved successfully',
                new CustomTemplateResource($customTemplate)
            );

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Custom template not found');
        } catch (\Exception $e) {
            return $this->apiErrorResponse(
                'Failed to retrieve custom template: ' . $e->getMessage(),
                400
            );
        }
    }

    public function store(CustomTemplateStoreRequest $request)
    {
        try {
            $user = auth()->user();

            $validated = $request->validated();
            $validated['client_id'] = $user->client_id;

            $customTemplate = $this->messagesTemplatesService->createCustomTemplate($validated);

            return $this->createdResponse(
                'Custom template created successfully',
                new CustomTemplateResource($customTemplate)
            );

        } catch (\Exception $e) {
            return $this->apiErrorResponse(
                'Failed to create custom template: ' . $e->getMessage(),
                400
            );
        }
    }

    public function update(CustomTemplateUpdateRequest $request)
    {
        try {

            $data = $request->validated();
            $CurrentCustomTemplate = $this->messagesTemplatesService->findCustomTemplateById($data['custom_template_id']);

            if (!$CurrentCustomTemplate) {
                return $this->notFoundResponse('Custom template not found');
            }

            $updatedCustomTemplate = $this->messagesTemplatesService->updateCustomTemplate($CurrentCustomTemplate, $data);

            return $this->successResponse(
                'Custom template updated successfully',
                new CustomTemplateResource($updatedCustomTemplate)
            );
        } catch (\Exception $e) {
            return $this->apiErrorResponse(
                'Failed to update custom template: ' . $e->getMessage(),
                400
            );
        }
    }

    public function destroy($id)
    {
        try {
            $customTemplate = $this->messagesTemplatesService->findCustomTemplateById($id);

            $deleted = $this->messagesTemplatesService->deleteCustomTemplate($customTemplate);

            if (!$deleted) {
                return $this->apiErrorResponse('Failed to delete custom template', 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Custom template deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Custom template not found');
        } catch (\Exception $e) {
            return $this->apiErrorResponse(
                'Failed to delete custom template: ' . $e->getMessage(),
                400
            );
        }
    }

    public function activeCustomTemplate(CustomTemplateStatusToggleRequest $request)
    {

        try {

            $customTemplateMessage = $this->messagesTemplatesService->findCustomTemplateById($request->custom_template_id);
            $current_active_custom_template = $this->messagesTemplatesService->updateCustomTemplateActiveStatus($customTemplateMessage);

            return $this->successResponse(
                'Current Active Custom template ',
                [
                    'id' => $current_active_custom_template->id,
                    'name' => $current_active_custom_template->name,
                    'body' => $current_active_custom_template->body,
                    'is_active' => (int) $current_active_custom_template->is_active,
                ]
            );

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Custom template not found');
        } catch (\Exception $e) {
            return $this->apiErrorResponse(
                'Failed to update custom template status : ' . $e->getMessage(),
                400
            );
        }

    }

}
