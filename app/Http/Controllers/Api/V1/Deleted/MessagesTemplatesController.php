<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Models\CustomTemplate;
use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class MessagesTemplatesController extends Controller
{
    /**
     * Get all message templates
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $templates = MessageTemplate::with('customTemplates', 'variables')->get();

        return response()->json([
            'error' => false,
            'message' => 'Message templates retrieved successfully',
            'data' => $templates
        ]);
    }

    /**
     * Store a new custom template
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
            'template_id' => 'required|exists:message_templates,id',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $customTemplate = new CustomTemplate;
            $customTemplate->body = $request->body;
            $customTemplate->template_id = $request->template_id;
            $customTemplate->client_id = auth()->user()->client_id;
            $customTemplate->is_active = $request->is_active ? '1' : '0';
            $customTemplate->save();

            if ($customTemplate->is_active) {
                CustomTemplate::where('id', '!=', $customTemplate->id)
                    ->where('template_id', $customTemplate->template_id)
                    ->update(['is_active' => '0']);
            }

            return response()->json([
                'error' => false,
                'message' => 'Custom template created successfully',
                'data' => $customTemplate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a custom template for editing
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:custom_templates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $customTemplate = CustomTemplate::with('messageTemplate.variables')->find($request->template_id);

            if (!$customTemplate) {
                return response()->json([
                    'error' => true,
                    'message' => 'Custom template not found'
                ], 404);
            }

            return response()->json([
                'error' => false,
                'message' => 'Custom template retrieved successfully',
                'data' => [
                    'id' => $customTemplate->id,
                    'title' => $customTemplate->messageTemplate->title,
                    'body' => $customTemplate->body,
                    'is_active' => $customTemplate->is_active,
                    'variables' => $customTemplate->messageTemplate->variables
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing custom template
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:custom_templates,id',
            'body' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $customTemplate = CustomTemplate::find($request->template_id);

            if (!$customTemplate) {
                return response()->json([
                    'error' => true,
                    'message' => 'Custom template not found'
                ], 404);
            }

            $customTemplate->body = $request->body;
            $customTemplate->save();

            return response()->json([
                'error' => false,
                'message' => 'Custom template updated successfully',
                'data' => $customTemplate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a custom template
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:custom_templates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $customTemplate = CustomTemplate::find($request->template_id);

            if (!$customTemplate) {
                return response()->json([
                    'error' => true,
                    'message' => 'Custom template not found'
                ], 404);
            }

            $templates = CustomTemplate::where('template_id', $customTemplate->template_id)
                ->whereNot('id', $request->template_id)
                ->count();

            if ($templates != 0) {
                $customTemplate->delete();

                return response()->json([
                    'error' => false,
                    'message' => 'Custom template deleted successfully'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'This template cannot be deleted'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Make a custom template active
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function makeActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:custom_templates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $customTemplate = CustomTemplate::find($request->template_id);

            if (!$customTemplate) {
                return response()->json([
                    'error' => true,
                    'message' => 'Custom template not found'
                ], 404);
            }

            CustomTemplate::where('template_id', $customTemplate->template_id)
                ->whereNot('id', $request->template_id)
                ->update(['is_active' => '0']);

            $customTemplate->is_active = 1;
            $customTemplate->save();

            return response()->json([
                'error' => false,
                'message' => 'Template has been activated',
                'data' => MessageTemplate::with('customTemplates', 'variables')->get()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template details
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:message_templates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $template = MessageTemplate::with('variables')->find($request->template_id);

            if (!$template) {
                return response()->json([
                    'error' => true,
                    'message' => 'Template not found'
                ], 404);
            }

            return response()->json([
                'error' => false,
                'message' => 'Template retrieved successfully',
                'data' => [
                    'title' => $template->title,
                    'variables' => $template->variables,
                    'id' => $template->id
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
