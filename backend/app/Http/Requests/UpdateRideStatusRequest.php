<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRideStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && (
            $this->user()->tokenCan('driver:accept-ride') ||
            $this->user()->tokenCan('driver:complete-ride') ||
            $this->user()->tokenCan('rider:cancel-ride')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:accepted,driver_arrived,in_progress,completed,cancelled',
            'actual_distance_km' => 'nullable|numeric|min:0|max:1000',
            'actual_duration_minutes' => 'nullable|integer|min:0|max:1440',
            'actual_fare_rp' => 'nullable|integer|min:0|max:100000',
            'driver_notes' => 'nullable|string|max:500',
            'cancel_reason' => 'nullable|string|max:255',
        ];
    }
}
