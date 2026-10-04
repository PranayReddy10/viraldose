@props(['label', 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null])
<div class="mb-4">
    <label class="label" for="f-{{ $name }}">{{ $label }}</label>
    <select id="f-{{ $name }}" name="{{ $name }}" class="input">
        @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @if($help)<p class="mt-1 text-xs text-ink-500">{{ $help }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
