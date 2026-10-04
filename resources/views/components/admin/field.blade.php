@props(['label', 'name', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'rows' => 3, 'max' => null, 'counter' => false, 'slugSource' => false, 'slugTarget' => false])
<div {{ $attributes->class(['mb-4']) }}>
    <div class="flex items-center justify-between">
        <label class="label" for="f-{{ $name }}">{{ $label }}@if($required) <span class="text-red-600">*</span>@endif</label>
        @if($counter)<span class="text-xs text-ink-500" data-counter="f-{{ $name }}" data-max="{{ $max }}"></span>@endif
    </div>
    @if($type === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" class="input" @if($max) maxlength="{{ $max }}" @endif @required($required)>{{ old($name, $value) }}</textarea>
    @else
        <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" class="input" @if($max) maxlength="{{ $max }}" @endif @required($required) @if($slugSource) data-slug-source @endif @if($slugTarget) data-slug-target @endif>
    @endif
    @if($help)<p class="mt-1 text-xs text-ink-500">{{ $help }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
