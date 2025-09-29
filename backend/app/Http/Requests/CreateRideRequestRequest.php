<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRideRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->tokenCan('rider:request-ride');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pickup_location_id' => 'required|integer|exists:locations,id',
            'destination_location_id' => 'required|integer|exists:locations,id|different:pickup_location_id',
            'passenger_count' => 'required|integer|min:1|max:4',
            'special_requests' => 'nullable|array',
            'special_requests.*' => 'string|max:255',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'pickup_location_id.required' => 'Pickup location is required',
            'pickup_location_id.exists' => 'Invalid pickup location',
            'destination_location_id.required' => 'Destination location is required',
            'destination_location_id.exists' => 'Invalid destination location',
            'destination_location_id.different' => 'Pickup and destination must be different',
            'passenger_count.required' => 'Number of passengers is required',
            'passenger_count.min' => 'At least 1 passenger is required',
            'passenger_count.max' => 'Maximum 4 passengers allowed',
        ];
    }
}
