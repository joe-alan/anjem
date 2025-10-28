<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateRideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Allow both riders and drivers to rate each other
     */
    public function authorize(): bool
    {
        return $this->user() && (
            $this->user()->tokenCan('rider:rate-driver') ||
            $this->user()->tokenCan('driver:rate-rider')
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
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|in:friendly,professional,safe_driving,clean_vehicle,on_time,helpful',
        ];
    }
}
