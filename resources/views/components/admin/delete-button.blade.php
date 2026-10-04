@props(['action', 'confirm' => 'Are you sure?', 'label' => 'Delete'])
<form method="post" action="{{ $action }}" data-confirm="{{ $confirm }}" class="inline">
    @csrf @method('DELETE')
    <button type="submit" {{ $attributes->class(['text-xs font-semibold text-red-600 hover:underline']) }}>{{ $label }}</button>
</form>
