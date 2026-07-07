<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'youtube_url'  => ['required', 'string', 'regex:/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)[A-Za-z0-9_-]{6,}/'],
            'layout_type'  => ['required', 'in:reframe,gaussian'],
            'clip_count'   => ['required', 'integer', 'in:1,3,5'],
            'duration_mode' => ['required', 'in:auto,manual'],
            'min_duration' => ['nullable', 'integer', 'min:5', 'max:300'],
            'max_duration' => ['nullable', 'integer', 'min:10', 'max:600'],

            'subtitle'                   => ['sometimes', 'array'],
            'subtitle.enabled'           => ['sometimes', 'boolean'],
            'subtitle.font_family'       => ['sometimes', 'string', 'in:Arial,Roboto,Montserrat,Oswald,Bebas Neue'],
            'subtitle.font_size'         => ['sometimes', 'integer', 'min:16', 'max:100'],
            'subtitle.text_color'        => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'subtitle.highlight_color'   => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'subtitle.position'          => ['sometimes', 'in:top,center,bottom'],
            'subtitle.background_style'  => ['sometimes', 'in:none,semi,full'],

            'hook'                       => ['sometimes', 'array'],
            'hook.enabled'               => ['sometimes', 'boolean'],
            'hook.hook_text'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'hook.is_ai_generated'       => ['sometimes', 'boolean'],
            'hook.duration_seconds'      => ['sometimes', 'numeric', 'min:2', 'max:5'],
            'hook.position'              => ['sometimes', 'in:top,center,bottom'],
            'hook.text_color'            => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'hook.background_style'      => ['sometimes', 'in:none,semi,full'],
        ];
    }
}
