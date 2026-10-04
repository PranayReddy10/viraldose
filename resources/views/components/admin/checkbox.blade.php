@props(['label', 'name', 'checked' => false, 'help' => null])
<label class="flex items-start gap-2 text-sm mb-2">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="mt-0.5 rounded border-ink-300 text-brand-600 focus:ring-brand-600" @checked(old($name, $checked))>
    <span>{{ $label }}@if($help)<span class="block text-xs text-ink-500">{{ $help }}</span>@endif</span>
</label>
