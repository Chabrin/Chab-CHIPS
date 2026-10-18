<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingMaterialRequest extends FormRequest
{
    /**
     * Only users allowed to manage staff may upload training material.
     */
    public function authorize(): bool {
        return (bool) $this->user()?->hasPermission('update_staff');
    }

    /**
     * Validation rules for an uploaded training file.
     */
    public function rules(): array {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:20',
            'is_mandatory' => 'nullable|boolean',
            'document' => [
                'required',
                'file',
                'max:51200', // 50MB max limit to accommodate PDFs, slide decks, and MP4 videos
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,mp4,webm,zip',
            ],
        ];
    }
}
