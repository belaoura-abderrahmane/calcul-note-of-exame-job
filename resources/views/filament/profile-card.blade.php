@php /** @var \App\Models\ScoringProfile|null $profile */ @endphp
@if ($profile)
    @php
        $calc = $profile->calculator();
        $labels = \App\Services\ScoreCalculator::labels();
        $fmt = fn ($v) => \App\Services\ScoreCalculator::format((float) $v);
    @endphp
    <div class="profile-card">
        <h4>📘 {{ $profile->name }}</h4>
        @if (filled($profile->legal_reference))
            <div class="ref"><b>المرجع القانوني:</b> {{ $profile->legal_reference }}</div>
        @endif
        @if (filled($profile->description))
            <div class="desc">{{ $profile->description }}</div>
        @endif
        <div class="chips">
            @foreach ($calc->maxima() as $key => $max)
                @continue($max <= 0)
                <span class="chip">{{ $labels[$key] }}: <b>{{ $fmt($max) }}</b></span>
            @endforeach
            <span class="chip">المجموع: <b>{{ $fmt($calc->maxTotal()) }}</b> نقطة</span>
        </div>
    </div>
@endif
