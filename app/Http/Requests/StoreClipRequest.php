<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'youtube_url'  => ['required', 'url', 'max:500', 'regex:/youtube\.com|youtu\.be/i'],
            'layout_mode'  => ['required', 'in:auto_magic,auto_split,gaussian_blur,auto_reframe'],
            'clip_count'   => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'youtube_url.regex' => 'URL harus link YouTube yang valid.',
        ];
    }
}
