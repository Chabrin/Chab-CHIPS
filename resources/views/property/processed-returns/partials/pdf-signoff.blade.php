{{-- Sign-off block appears once at the end and stays together across page breaks. Expects $signoff and $signoffColumns (title => key in $signoff, or null for a blank column). --}}
@php
    $clean = fn($v) => (!empty($v) && $v !== '-') ? e($v) : '&nbsp;';
@endphp
<div class="signoff-footer">
    <table class="signoff">
        <tr>
            @foreach($signoffColumns as $title => $key)
            <td>
                <div class="signoff-title">{{ strtoupper($title) }}</div>
                <table class="signoff-line">
                    <tr><td class="k">Name</td><td class="v">{!! $key ? $clean($signoff[$key]['name'] ?? '') : '&nbsp;' !!}</td></tr>
                    <tr><td class="k">Date</td><td class="v">{!! $key ? $clean($signoff[$key]['date'] ?? '') : '&nbsp;' !!}</td></tr>
                    <tr><td class="k">Sign</td><td class="v">&nbsp;</td></tr>
                </table>
            </td>
            @endforeach
        </tr>
    </table>
</div>
