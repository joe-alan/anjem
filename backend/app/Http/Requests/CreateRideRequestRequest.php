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
            // Location ID path (existing beacon-based flow)
            'pickup_beacon_id' => 'required_without_all:pickup_location_id,pickup_latitude|integer|exists:locations,id',
            'pickup_location_id' => 'required_without_all:pickup_beacon_id,pickup_latitude|integer|exists:locations,id',
            // Coordinate path (P2P flow)
            'pickup_latitude' => 'required_without_all:pickup_beacon_id,pickup_location_id|numeric|between:-90,90',
            'pickup_longitude' => 'required_with:pickup_latitude|numeric|between:-180,180',
            'pickup_name' => 'required_with:pickup_latitude|string|max:255',
            'destination_beacon_id' => 'required_without_all:destination_location_id,destination_latitude|integer|exists:locations,id',
            'destination_location_id' => 'required_without_all:destination_beacon_id,destination_latitude|integer|exists:locations,id',
            'destination_latitude' => 'required_without_all:destination_beacon_id,destination_location_id|numeric|between:-90,90',
            'destination_longitude' => 'required_with:destination_latitude|numeric|between:-180,180',
            'destination_name' => 'required_with:destination_latitude|string|max:255',
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
            'pickup_beacon_id.required_without_all' => 'Pickup location is required',
            'pickup_location_id.required_without_all' => 'Pickup location is required',
            'pickup_latitude.required_without_all' => 'Pickup location is required',
            'pickup_beacon_id.exists' => 'Invalid pickup location',
            'pickup_location_id.exists' => 'Invalid pickup location',
            'destination_beacon_id.required_without_all' => 'Destination location is required',
            'destination_location_id.required_without_all' => 'Destination location is required',
            'destination_latitude.required_without_all' => 'Destination location is required',
            'destination_beacon_id.exists' => 'Invalid destination location',
            'destination_location_id.exists' => 'Invalid destination location',
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

        // Coordinate path: pass lat/lng through directly for RideService
        if (isset($validated['pickup_latitude'])) {
            // P2P pickup — no beacon ID normalization needed
            unset($validated['pickup_beacon_id'], $validated['pickup_location_id']);

            // Still normalize destination if using location ID mode (mixed-mode)
            if (isset($validated['destination_beacon_id']) && ! isset($validated['destination_location_id'])) {
                $validated['destination_location_id'] = $validated['destination_beacon_id'];
            }
            unset($validated['destination_beacon_id']);

            return $validated;
        }

        // Location ID path: normalize beacon → location_id
        if (isset($validated['pickup_beacon_id']) && ! isset($validated['pickup_location_id'])) {
            $validated['pickup_location_id'] = $validated['pickup_beacon_id'];
        }
        unset($validated['pickup_beacon_id']);

        if (isset($validated['destination_beacon_id']) && ! isset($validated['destination_location_id'])) {
            $validated['destination_location_id'] = $validated['destination_beacon_id'];
        }
        unset($validated['destination_beacon_id']);

        return $validated;
    }
}
