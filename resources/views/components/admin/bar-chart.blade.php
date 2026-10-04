{{--
  Single-series daily bar chart as inline SVG (no library). One hue, thin bars,
  rounded data-ends, recessive gridlines, per-bar hover tooltip + table view.
  $series: array of ['date' => 'Y-m-d', 'value' => number]
--}}
@props(['series' => [], 'label' => 'Value', 'color' => '#dc2626', 'height' => 160, 'id' => null])
@php
    $id = $id ?: 'chart-'.\Illuminate\Support\Str::random(6);
    $n = max(1, count($series));
    $max = max(1, (int) ceil(max(array_column($series, 'value') ?: [0]) * 1.1));
    $w = 600; $h = $height; $padL = 36; $padB = 22; $padT = 8;
    $plotW = $w - $padL - 8; $plotH = $h - $padB - $padT;
    $slot = $plotW / $n; $bar = max(2, min(18, $slot - 2));
    $ticks = [0, round($max / 2), $max];
    $fmt = fn ($v) => $v >= 1000000 ? round($v / 1000000, 1).'M' : ($v >= 1000 ? round($v / 1000, 1).'k' : (string) $v);
@endphp
<div class="chart" data-chart="{{ $id }}">
    <div class="relative">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img" aria-labelledby="{{ $id }}-title">
            <title id="{{ $id }}-title">{{ $label }} by day</title>
            @foreach($ticks as $t)
                @php $y = $padT + $plotH - ($t / $max) * $plotH; @endphp
                <line x1="{{ $padL }}" x2="{{ $w - 8 }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e5e7eb" stroke-width="1" />
                <text x="{{ $padL - 6 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="10" fill="#6b7280">{{ $fmt($t) }}</text>
            @endforeach
            @foreach($series as $i => $point)
                @php
                    $v = (float) $point['value'];
                    $bh = $max > 0 ? ($v / $max) * $plotH : 0;
                    $x = $padL + $i * $slot + ($slot - $bar) / 2;
                    $y = $padT + $plotH - $bh;
                    $date = \Illuminate\Support\Carbon::parse($point['date']);
                @endphp
                <g class="bar-group">
                    <rect x="{{ $padL + $i * $slot }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent" />
                    @if($bh > 0)
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $bar }}" height="{{ $bh }}" rx="{{ min(4, $bar / 2) }}" ry="{{ min(4, $bar / 2) }}" fill="{{ $color }}" />
                        @if($bh > 4)<rect x="{{ $x }}" y="{{ $y + min(4, $bar / 2) }}" width="{{ $bar }}" height="{{ max(0, $bh - min(4, $bar / 2)) }}" fill="{{ $color }}" />@endif
                    @endif
                    <title>{{ $date->format('D, d M') }}: {{ number_format($v) }} {{ $label }}</title>
                </g>
                @if($i === 0 || $i === $n - 1 || ($n > 10 && $i % (int) ceil($n / 6) === 0))
                    <text x="{{ $padL + $i * $slot + $slot / 2 }}" y="{{ $h - 6 }}" text-anchor="middle" font-size="10" fill="#6b7280">{{ $date->format('d M') }}</text>
                @endif
            @endforeach
        </svg>
    </div>
    <details class="mt-1 text-xs text-ink-500">
        <summary class="cursor-pointer select-none">Table view</summary>
        <div class="max-h-48 overflow-auto"><table class="mt-1 w-full text-left"><thead><tr><th class="py-0.5">Date</th><th class="py-0.5 text-right">{{ $label }}</th></tr></thead><tbody>
            @foreach(array_reverse($series) as $point)<tr><td class="py-0.5">{{ $point['date'] }}</td><td class="py-0.5 text-right tabular-nums">{{ number_format($point['value']) }}</td></tr>@endforeach
        </tbody></table></div>
    </details>
</div>
