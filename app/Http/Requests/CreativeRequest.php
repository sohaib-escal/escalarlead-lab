<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'product_id' => ['nullable', 'exists:products,id'],
            'creative_status_id' => ['required', 'exists:creative_statuses,id'],
            'landing_page_id' => ['nullable', 'exists:landing_pages,id'],
            'cta_option_id' => ['nullable', 'exists:cta_options,id'],
            'format' => ['required', 'string', 'in:'.implode(',', array_keys(config('creative.formats')))],

            'asset_url' => ['nullable', 'url', 'max:500'],
            'asset_filename' => ['nullable', 'string', 'max:255'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'asset' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm', 'max:51200'],

            'hook' => ['nullable', 'string'],
            'primary_text' => ['nullable', 'string'],
            'headline' => ['nullable', 'string', 'max:255'],
            'ad_description' => ['nullable', 'string', 'max:255'],
            'concept' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'performance_override' => ['nullable', 'in:winner,promising,average,poor'],

            'channels' => ['array'],
            'channels.*' => ['exists:channels,id'],
            'campaigns' => ['array'],
            'campaigns.*' => ['exists:campaigns,id'],

            'parameters' => ['array'],
            'parameters.*' => ['array'],
            'parameters.*.*' => ['nullable', 'exists:parameter_values,id'],

            'utm' => ['array'],
            'utm.base_url' => ['nullable', 'string', 'max:500'],
            'utm.utm_source' => ['nullable', 'string', 'max:120'],
            'utm.utm_medium' => ['nullable', 'string', 'max:120'],
            'utm.utm_campaign' => ['nullable', 'string', 'max:180'],
            'utm.utm_content' => ['nullable', 'string', 'max:180'],
            'utm.utm_term' => ['nullable', 'string', 'max:180'],
            'utm.auto_sync' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom de la créa',
            'creative_status_id' => 'statut',
            'format' => 'format',
        ];
    }
}
