<?php

namespace App\Http\Requests;

use App\Support\EventDesignBrief;
use App\Support\InspirationPhotos;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'booking_type' => ['nullable', 'string', 'in:custom_ai,preset'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'event_type' => ['required', 'string', 'in:wedding,birthday,corporate,delivery,other'],
            'other_event_type' => ['nullable', 'string', 'max:255', 'required_if:event_type,other'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['required', 'string', 'max:500'],
            'venue_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:venue_longitude'],
            'venue_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:venue_latitude'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'inspiration_image' => ['nullable', 'image', 'mimes:' . implode(',', InspirationPhotos::MIMES), 'max:' . InspirationPhotos::MAX_KILOBYTES],
            'analysis_tokens' => ['nullable'],
            'analysis_token' => ['nullable', 'string'],
            'analysis_temp_path' => ['nullable', 'string'],
            'analysis_data' => ['nullable', 'json'],
            'analysis_nonce' => ['nullable', 'string'],
            'table_count' => ['nullable', 'integer', 'min:1'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
        ] + EventDesignBrief::rules();
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'event_type.required' => 'Please select the type of event.',
            'event_type.in' => 'Please choose a valid event type.',
            'other_event_type.required_if' => 'Please specify the event type when "Other" is selected.',
            'other_event_type.max' => 'The custom event type may not exceed 255 characters.',
            'event_date.required' => 'The event date is required.',
            'event_date.date' => 'The event date must be a valid date.',
            'event_date.after_or_equal' => 'The event date cannot be in the past.',
            'event_time.required' => 'The event time is required.',
            'event_time.date_format' => 'The event time must be a valid time in 24-hour format (HH:MM).',
            'venue.required' => 'Event venue is required.',
            'venue.max' => 'The venue description may not exceed 500 characters.',
            'venue_latitude' => 'The pinned venue location is invalid. Please pin the venue on the map again.',
            'venue_longitude' => 'The pinned venue location is invalid. Please pin the venue on the map again.',
            'special_requests.max' => 'Special requests may not exceed 2000 characters.',
            'inspiration_image.image' => 'The inspiration file must be an image.',
            'inspiration_image.max' => 'The inspiration image may not exceed 5MB.',
            'inspiration_image.mimes' => 'The inspiration image must be a JPG, PNG or WEBP photo.',
        ] + EventDesignBrief::messages();
    }
}
