<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    public function index()
    {
        try {
            // Permission checking - uncomment when permissions are properly set up
            // $canCreate = Auth::user()->can('create_email_templates');
            // $canUpdate = Auth::user()->can('update_email_templates');  
            // $canDelete = Auth::user()->can('delete_email_templates');

            // For now, assume user has all permissions
            $canCreate = true;
            $canUpdate = true;
            $canDelete = true;

            return view('email_templates.manage', compact('canCreate', 'canUpdate', 'canDelete'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading email templates view: ' . $e->getMessage());
            abort(500, 'Failed to load email templates');
        }
    }

    public function list(Request $request)
    {
        try {
            $query = EmailTemplate::query()->orderBy('created_at', 'desc');

            // Filter by confirmation status if provided
            if ($request->has('confirmed')) {
                if ($request->confirmed === '1') {
                    $query->confirmed();
                } elseif ($request->confirmed === '0') {
                    $query->unconfirmed();
                }
            }

            // Filter by type if provided
            if ($request->filled('type')) {
                $query->byType($request->type);
            }

            $templates = $query->get()->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'comtype' => $template->comtype,
                    'confirmed' => $template->confirmed,
                    'status' => $template->status,
                    'status_badge' => $template->status_badge,
                    'added_by' => $template->added_by,
                    'confirmed_by' => $template->confirmed_by,
                    'confirmed_date' => $template->confirmed_date ? $template->confirmed_date->format('Y-m-d H:i:s') : null,
                    'created_at' => $template->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $template->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json(['templates' => $templates]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading email templates list: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load templates'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:email_templates,name',
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'comtype' => 'nullable|string|max:50',
                'template_variable' => 'nullable|array',
                'template_variable.*' => 'string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template = EmailTemplate::create([
                'name' => $request->name,
                'subject' => $request->subject,
                'body' => $request->body,
                'comtype' => $request->comtype,
                'template_variable' => $request->template_variable ?? [],
                'added_by' => optional(Auth::user())->username ?? 'system',
                'confirmed' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email template created successfully',
                'template' => $template
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error creating email template: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create template'], 500);
        }
    }

    public function show($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json(['error' => 'Invalid template ID'], 400);
            }

            $template = EmailTemplate::find($id);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            return response()->json(['template' => $template]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading email template: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load template'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json(['error' => 'Invalid template ID'], 400);
            }

            $template = EmailTemplate::find($id);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:email_templates,name,' . $id,
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'comtype' => 'nullable|string|max:50',
                'template_variable' => 'nullable|array',
                'template_variable.*' => 'string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mark as unconfirmed when edited
            $template->update([
                'name' => $request->name,
                'subject' => $request->subject,
                'body' => $request->body,
                'comtype' => $request->comtype,
                'template_variable' => $request->template_variable ?? [],
                'confirmed' => false,
                'confirmed_by' => null,
                'confirmed_date' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email template updated successfully (now unconfirmed)',
                'template' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error updating email template: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update template'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json(['error' => 'Invalid template ID'], 400);
            }

            $template = EmailTemplate::find($id);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            $templateName = $template->name;
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => "Email template '{$templateName}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error deleting email template: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete template'], 500);
        }
    }

    public function confirm(Request $request, $id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json(['error' => 'Invalid template ID'], 400);
            }

            $template = EmailTemplate::find($id);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            if ($template->confirmed) {
                return response()->json(['error' => 'Template is already confirmed'], 400);
            }

            // Check if current user is trying to confirm their own template
            $currentUser = optional(Auth::user())->username ?? 'system';
            if ($template->added_by === $currentUser) {
                return response()->json(['error' => 'You cannot confirm templates you created yourself'], 403);
            }

            $template->markAsConfirmed($currentUser);

            return response()->json([
                'success' => true,
                'message' => 'Email template confirmed successfully',
                'template' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error confirming email template: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to confirm template'], 500);
        }
    }

    public function bulkConfirm(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['error' => 'No template IDs provided'], 400);
        }

        try {
            $currentUser = optional(Auth::user())->username ?? 'system';
            $confirmedCount = 0;
            $skippedCount = 0;
            $alreadyConfirmedCount = 0;

            $templates = EmailTemplate::whereIn('id', $ids)->get();

            foreach ($templates as $template) {
                if ($template->confirmed) {
                    $alreadyConfirmedCount++;
                    continue;
                }

                // Skip templates created by current user
                if ($template->added_by === $currentUser) {
                    $skippedCount++;
                    continue;
                }

                $template->markAsConfirmed($currentUser);
                $confirmedCount++;
            }

            // Build response message
            $messages = [];
            if ($confirmedCount > 0) {
                $messages[] = "{$confirmedCount} template(s) confirmed successfully";
            }
            if ($skippedCount > 0) {
                $messages[] = "{$skippedCount} template(s) skipped (self-confirmation not allowed)";
            }
            if ($alreadyConfirmedCount > 0) {
                $messages[] = "{$alreadyConfirmedCount} template(s) already confirmed";
            }

            return response()->json([
                'success' => true,
                'message' => implode('. ', $messages),
                'confirmed' => $confirmedCount,
                'skipped' => $skippedCount,
                'already_confirmed' => $alreadyConfirmedCount
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error bulk confirming email templates: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to confirm templates'], 500);
        }
    }

    public function getTemplateTypes()
    {
        try {
            $types = EmailTemplate::distinct()->pluck('comtype')->filter()->sort()->values();
            return response()->json(['types' => $types]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading email template types: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load template types'], 500);
        }
    }
}
