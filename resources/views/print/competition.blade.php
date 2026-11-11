@php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0'; @endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>قائمة الترتيب - {{ $competition->title }}</title>
    <link href="{{ asset('fonts/cairo/cairo.css') }}" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Cairo', Tahoma, sans-serif; margin: 0; padding: 24px; color: #111; background: #f1f5f9; }
        .sheet { background: #fff; max-width: 297mm; margin: auto; padding: 18mm 12mm; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .rep { text-align: center; font-weight: 700; line-height: 1.7; }
        .top { display: flex; justify-content: space-between; margin: 12px 0 18px; font-weight: 600; }
        h1 { text-align: center; font-size: 20px; margin: 10px 0 4px; }
        .sub { text-align: center; color: #444; margin-bottom: 16px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th, td { border: 1px solid #333; padding: 5px 4px; text-align: center; }
        th { background: #e2e8f0; }
        th small { display: block; font-weight: 400; color: #444; }
        td.name { text-align: right; white-space: nowrap; }
        td.total { font-weight: 700; background: #f8fafc; }
        tr.ok td { background: #ecfdf5; }
        tr.wait td { background: #fffbeb; }
        .legend { margin-top: 10px; font-size: 12px; display: flex; gap: 18px; }
        .legend .l-ok::before, .legend .l-wait::before { content: ''; display: inline-block; width: 12px; height: 12px; border: 1px solid #333; margin-left: 5px; vertical-align: middle; }
        .legend .l-ok::before { background: #ecfdf5; } .legend .l-wait::before { background: #fffbeb; }
        .signs { display: flex; justify-content: space-around; margin-top: 40px; font-weight: 700; }
        .signs div { min-height: 80px; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .toolbar button { font-family: inherit; font-size: 15px; padding: 8px 22px; border: 0; border-radius: 8px; background: #059669; color: #fff; cursor: pointer; }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; max-width: none; }
            .toolbar { display: none; }
            tr.ok td, tr.wait td, th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">🖨️ طباعة</button></div>
<div class="sheet">
    <div class="rep">الجمهورية الجزائرية الديمقراطية الشعبية</div>
    <div class="top">
        <div>{{ $competition->institution ?: '................................' }}</div>
        <div>بتاريخ: {{ now()->format('d/m/Y') }}</div>
    </div>

    <h1>محضر ترتيب المترشحين - مسابقة على أساس الشهادة</h1>
    <div class="sub">
        {{ $competition->title }} — الرتبة: <b>{{ $competition->grade }}</b>
        — عدد المناصب: <b>{{ $competition->positions }}</b>
        @if ($competition->scoringProfile) <br>سلم التنقيط: <b>{{ $competition->scoringProfile->name }}</b> @endif
        @if ($competition->exam_date) — تاريخ المسابقة: <b>{{ $competition->exam_date->format('d/m/Y') }}</b> @endif
    </div>

    <table>
        <thead>
        <tr>
            <th>الترتيب</th>
            <th>اللقب والاسم</th>
            <th>تاريخ الميلاد</th>
            <th>التخصص</th>
            @foreach ($labels as $key => $label)
                <th>{{ $label }}<small>/ {{ $fmt($maxima[$key]) }}</small></th>
            @endforeach
            <th>المجموع<small>/ {{ $fmt($maxTotal) }}</small></th>
            <th>النتيجة</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($candidates as $i => $c)
            @php
                $rank = $i + 1;
                $status = $rank <= $competition->positions ? 'ok' : ($rank <= 2 * $competition->positions ? 'wait' : '');
            @endphp
            <tr class="{{ $status }}">
                <td>{{ $rank }}</td>
                <td class="name">{{ $c->full_name }}</td>
                <td>{{ $c->birth_date?->format('d/m/Y') }}</td>
                <td>{{ $c->specialty }}</td>
                @foreach ($labels as $key => $label)
                    <td>{{ $fmt($c->$key) }}</td>
                @endforeach
                <td class="total">{{ number_format($c->total, 2) }}</td>
                <td>{{ $status === 'ok' ? 'ناجح' : ($status === 'wait' ? 'قائمة الانتظار' : 'غير ناجح') }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 6 + count($labels) }}">لا يوجد مترشحون</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="legend">
        <span class="l-ok">ناجح (ضمن عدد المناصب)</span>
        <span class="l-wait">قائمة الانتظار</span>
        <span>في حالة تساوي النقاط يُقدَّم المترشح الأكبر سناً</span>
    </div>

    <div class="signs">
        <div>رئيس اللجنة</div>
        <div>الأعضاء</div>
        <div>ممثل المؤسسة</div>
    </div>
</div>
</body>
</html>
