<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendAdminNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can send notifications
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'action_url' => 'nullable|url|max:500',
            'action_text' => 'nullable|string|max:100',
            'recipient_type' => 'required|string|in:all,selected,filtered,single',
            'user_ids' => 'required_if:recipient_type,selected|array',
            'user_ids.*' => 'required_if:recipient_type,selected|uuid|exists:users,id',
            'user_id' => 'required_if:recipient_type,single|uuid|exists:users,id',
            'filters' => 'required_if:recipient_type,filtered|array',
            'filters.role' => 'nullable|string|in:investor,wholesaler,admin',
            'filters.search' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'The subject field is required.',
            'subject.max' => 'The subject may not be greater than 255 characters.',
            'message.required' => 'The message field is required.',
            'message.max' => 'The message may not be greater than 5000 characters.',
            'action_url.url' => 'The action URL must be a valid URL.',
            'recipient_type.required' => 'The recipient type is required.',
            'recipient_type.in' => 'The recipient type must be one of: all, selected, filtered, or single.',
            'user_ids.required_if' => 'User IDs are required when recipient type is selected.',
            'user_ids.array' => 'User IDs must be an array.',
            'user_ids.*.uuid' => 'Each user ID must be a valid UUID.',
            'user_ids.*.exists' => 'One or more user IDs do not exist.',
            'user_id.required_if' => 'User ID is required when recipient type is single.',
            'user_id.uuid' => 'User ID must be a valid UUID.',
            'user_id.exists' => 'The selected user does not exist.',
            'filters.required_if' => 'Filters are required when recipient type is filtered.',
            'filters.array' => 'Filters must be an array.',
            'filters.role.in' => 'The role filter must be one of: investor, wholesaler, or admin.',
        ];
    }
}

