<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\KycVerificationService;

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
        $allowedDomain = config('app.allowed_student_email_domain');

        return [
            'student_email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) use ($allowedDomain) {
                    if (!str_ends_with($value, '@' . $allowedDomain)) {
                        $fail('The student email must be from ' . $allowedDomain);
                    }
                },
            ],
            'student_id' => 'required|string|max:50',
            'student_name' => 'required|string|max:255',
            'vehicle_type' => 'required|string|in:motorcycle,car',
            'vehicle_plate' => 'required|string|max:20',
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
            'student_id.required' => 'Student ID is required',
            'student_name.required' => 'Student name is required',
            'vehicle_type.required' => 'Vehicle type is required',
            'vehicle_type.in' => 'Vehicle type must be either motorcycle or car',
            'vehicle_plate.required' => 'Vehicle license plate is required',
            'vehicle_color.required' => 'Vehicle color is required',
            'ktm_photo.required' => 'KTM photo is required',
            'ktm_photo.image' => 'KTM photo must be an image',
            'ktm_photo.mimes' => 'KTM photo must be in JPEG, JPG, or PNG format',
            'ktm_photo.max' => 'KTM photo size must not exceed 5MB',
        ];
    }
}
