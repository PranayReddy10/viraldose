{{--
  Designed file input: "Choose file" button, selected file name(s), optional current-image preview.
  Props: name, accept, multiple, button, help, preview (URL of the current file), previewClass, icon, required
--}}
@props(['name', 'accept' => null, 'multiple' => false, 'button' => 'Choose file', 'help' => null, 'preview' => null, 'previewClass' => 'aspect-video', 'icon' => 'image', 'required' => false, 'label' => null])
@php $id = 'file-'.preg_replace('/[^a-z0-9]+/i', '-', $name).'-'.substr(md5($name.$button), 0, 5); @endphp
<div {{ $attributes->class(['mb-4']) }} data-file-field>
    @if($label)<label class="label" for="{{ $id }}">{{ $label }}@if($required) <span class="text-red-600">*</span>@endif</label>@endif
    <label for="{{ $id }}" class="group block cursor-pointer rounded-lg border-2 border-dashed border-ink-300 bg-ink-100/40 p-3 transition hover:border-brand-600 hover:bg-brand-50/40 has-[:focus-visible]:border-brand-600">
        @if($preview)
            <img src="{{ $preview }}" alt="" data-file-preview class="mx-auto mb-3 max-h-52 w-full rounded object-cover {{ $previewClass }}">
        @else
            <img src="" alt="" data-file-preview class="mx-auto mb-3 hidden max-h-52 w-full rounded object-cover {{ $previewClass }}">
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <span class="btn-secondary pointer-events-none !py-1.5 text-xs"><x-admin.icon :name="$icon" class="h-4 w-4" /> {{ $button }}</span>
            <span class="min-w-0 flex-1 truncate text-sm text-ink-500" data-file-name>{{ $preview ? 'Current file shown · choose another to replace' : ($multiple ? 'No files chosen' : 'No file chosen') }}</span>
            <span class="hidden text-xs font-semibold text-green-700" data-file-count></span>
        </div>
        <input id="{{ $id }}" type="file" name="{{ $name }}" @if($accept) accept="{{ $accept }}" @endif @if($multiple) multiple @endif @required($required) class="sr-only">
    </label>
    @if($help)<p class="mt-1 text-xs text-ink-500">{{ $help }}</p>@endif
    @error(rtrim($name, '[]'))<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error(rtrim($name, '[]').'.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
