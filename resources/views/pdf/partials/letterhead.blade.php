{{-- Letterhead shared by the statement-style PDFs. Expects $title, $period and optionally $metaLines (array of strings). --}}
<table class="head">
    <tr>
        <td class="left">
            <img class="logo" src="{{ public_path('assets/images/logo/logo_statement.png') }}" alt="Chabrin Agencies Limited">
            <div class="company-line">
                Property Managing Agents<br>
                5th Floor, Room 517, NACICO Plaza, Landhies Road<br>
                P.O. Box 16659-00620 Nairobi, Kenya
            </div>
        </td>
        <td class="right">
            <div class="doc-title">{{ $title }}</div>
            <div class="doc-period">{{ $period }}</div>
            @foreach($metaLines ?? [] as $line)
            <div class="doc-meta">{{ $line }}</div>
            @endforeach
        </td>
    </tr>
</table>
<div class="rule-navy"></div>
<div class="rule-teal"></div>
