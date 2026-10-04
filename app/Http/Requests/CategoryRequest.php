<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageAllPosts() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$id])],
            'description' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'show_in_menu' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->slug ? Str::slug($this->slug) : null,
            'parent_id' => $this->parent_id ?: null,
            'show_in_menu' => $this->boolean('show_in_menu'),
            'show_on_home' => $this->boolean('show_on_home'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->sort_order,
            'color' => $this->color ?: '#dc2626',
        ]);
    }
}
