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
            // Accept both old and new field names for backward compatibility
            'pickup_beacon_id' => 'required_without:pickup_location_id|integer|exists:locations,id',
            'pickup_location_id' => 'required_without:pickup_beacon_id|integer|exists:locations,id',
            'destination_beacon_id' => 'required_without:destination_location_id|integer|exists:locations,id|different:pickup_beacon_id,pickup_location_id',
            'destination_location_id' => 'required_without:destination_beacon_id|integer|exists:locations,id|different:pickup_beacon_id,pickup_location_id',
            'passenger_count' => 'required|integer|min:1|max:4',
            'special_requests' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'pickup_beacon_id.required_without' => 'Pickup location is required',
            'pickup_location_id.required_without' => 'Pickup location is required',
            'pickup_beacon_id.exists' => 'Invalid pickup location',
            'pickup_location_id.exists' => 'Invalid pickup location',
            'destination_beacon_id.required_without' => 'Destination location is required',
            'destination_location_id.required_without' => 'Destination location is required',
            'destination_beacon_id.exists' => 'Invalid destination location',
            'destination_location_id.exists' => 'Invalid destination location',
            'destination_beacon_id.different' => 'Pickup and destination must be different',
            'destination_location_id.different' => 'Pickup and destination must be different',
            'passenger_count.required' => 'Number of passengers is required',
            'passenger_count.min' => 'At least 1 passenger is required',
            'passenger_count.max' => 'Maximum 4 passengers allowed',
        ];
    }

    /**
     * Get validated data with field name mapping for internal use
     * Normalizes both old and new field names to internal format
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Map mobile field names to internal field names if they exist
        // Prefer the new names if both are present
        if (isset($validated['pickup_beacon_id']) && !isset($validated['pickup_location_id'])) {
            $validated['pickup_location_id'] = $validated['pickup_beacon_id'];
        }
        unset($validated['pickup_beacon_id']);

        if (isset($validated['destination_beacon_id']) && !isset($validated['destination_location_id'])) {
            $validated['destination_location_id'] = $validated['destination_beacon_id'];
        }
        unset($validated['destination_beacon_id']);

        return $validated;
    }
}
