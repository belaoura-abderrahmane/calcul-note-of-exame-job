@php
    $total = $result['total'];
    $pct = $result['percentage'];
    $grade = match (true) {
        $pct >= 75 => ['ممتاز', '#16a34a'],
        $pct >= 60 => ['جيد جداً', '#0d9488'],
        $pct >= 50 => ['جيد', '#2563eb'],
        $pct >= 35 => ['متوسط', '#d97706'],
        default => ['ضعيف', '#dc2626'],
    };
@endphp

<div class="score-card">
    <div class="score-card__head">
        <div class="score-card__title">النتيجة النهائية</div>
        <div class="score-card__total">
            <span class="score-card__value">{{ number_format($total, 2) }}</span>
            <span class="score-card__max">/ {{ \App\Services\ScoreCalculator::format($result['max_total']) }}</span>
        </div>
        <div class="score-card__pct">
            <span>{{ number_format($pct, 2) }}%</span>
            <span class="score-card__badge" style="background: {{ $grade[1] }}">{{ $grade[0] }}</span>
        </div>
        <div class="score-bar score-bar--lg"><span style="width: {{ min(100, $pct) }}%"></span></div>
    </div>

    <ul class="score-card__list">
        @foreach ($labels as $key => $label)
            @continue(($maxima[$key] ?? 0) <= 0)
            @php $v = $result[$key]; $m = $maxima[$key] ?: 1; @endphp
            <li>
                <div class="score-card__row">
                    <span>{{ $label }}</span>
                    <strong>{{ rtrim(rtrim(number_format($v, 2), '0'), '.') }} <small>/ {{ rtrim(rtrim(number_format($maxima[$key], 2), '0'), '.') }}</small></strong>
                </div>
                <div class="score-bar"><span style="width: {{ min(100, $v / $m * 100) }}%"></span></div>
            </li>
        @endforeach
    </ul>
</div>
