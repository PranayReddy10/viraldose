<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('post')?->id;

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('posts', 'slug')->ignore($id)->whereNull('deleted_at')],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:2000000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'remove_image' => ['nullable', 'boolean'],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'image_caption' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::in(Post::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            'is_breaking' => ['nullable', 'boolean'],
            'is_slider' => ['nullable', 'boolean'],
            'is_recommended' => ['nullable', 'boolean'],
            'allow_comments' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->slug ? Str::slug($this->slug) : null,
            'is_featured' => $this->boolean('is_featured'),
            'is_breaking' => $this->boolean('is_breaking'),
            'is_slider' => $this->boolean('is_slider'),
            'is_recommended' => $this->boolean('is_recommended'),
            'allow_comments' => $this->boolean('allow_comments'),
            'noindex' => $this->boolean('noindex'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
