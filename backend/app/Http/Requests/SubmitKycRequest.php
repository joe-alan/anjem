<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitKycRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated and have driver token abilities
        return $this->user() && $this->user()->tokenCan('driver:go-online');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedDomains = config('app.allowed_student_email_domains', []);
        $userId = $this->user()->id;

        return [
            'student_email' => [
                'required',
                'email',
                'max:255',
                // Unique email constraint (ignore current user's profile if updating)
                Rule::unique('driver_profiles', 'student_email')->ignore($userId, 'user_id'),
                // Domain validation
                function ($attribute, $value, $fail) use ($allowedDomains) {
                    $valid = false;
                    foreach ($allowedDomains as $domain) {
                        if (str_ends_with($value, '@'.$domain)) {
                            $valid = true;
                            break;
                        }
                    }
                    if (! $valid) {
                        $domainList = implode(', ', array_map(fn ($d) => '@'.$d, $allowedDomains));
                        $fail('The student email must be from one of: '.$domainList);
                    }
                },
            ],
            'student_id' => [
                'required',
                'string',
                'max:50',
                // Unique student ID constraint (ignore current user's profile if updating)
                Rule::unique('driver_profiles', 'student_id')->ignore($userId, 'user_id'),
            ],
            'student_name' => 'required|string|max:255',
            'vehicle_type' => 'required|string|in:motorcycle,car',
            'vehicle_plate' => [
                'required',
                'string',
                'max:20',
                // Unique vehicle plate constraint (ignore current user's profile if updating)
                Rule::unique('driver_profiles', 'vehicle_plate')->ignore($userId, 'user_id'),
            ],
            'vehicle_color' => 'required|string|max:50',
            'ktm_photo' => 'required|image|mimes:jpeg,jpg,png|max:5120', // 5MB max
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'student_email.required' => 'Student email is required',
            'student_email.email' => 'Student email must be a valid email address',
            'student_email.unique' => 'This student email is already registered',
            'student_id.required' => 'Student ID is required',
            'student_id.unique' => 'This student ID is already registered',
            'student_name.required' => 'Student name is required',
            'vehicle_type.required' => 'Vehicle type is required',
            'vehicle_type.in' => 'Vehicle type must be either motorcycle or car',
            'vehicle_plate.required' => 'Vehicle license plate is required',
            'vehicle_plate.unique' => 'This vehicle license plate is already registered',
            'vehicle_color.required' => 'Vehicle color is required',
            'ktm_photo.required' => 'KTM photo is required',
            'ktm_photo.image' => 'KTM photo must be an image',
            'ktm_photo.mimes' => 'KTM photo must be in JPEG, JPG, or PNG format',
            'ktm_photo.max' => 'KTM photo size must not exceed 5MB',
        ];
    }
}
