<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageAllPosts() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('page')?->id;

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($id)],
            'content' => ['nullable', 'string', 'max:2000000'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'is_active' => ['nullable', 'boolean'],
            'show_in_footer' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->slug ? Str::slug($this->slug) : null,
            'is_active' => $this->boolean('is_active'),
            'show_in_footer' => $this->boolean('show_in_footer'),
            'noindex' => $this->boolean('noindex'),
            'sort_order' => (int) $this->sort_order,
        ]);
    }
}
