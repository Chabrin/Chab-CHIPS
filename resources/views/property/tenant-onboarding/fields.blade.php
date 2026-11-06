<div class="row">
@foreach($values as $key => $value)
    @continue(in_array($key, ['sla_ref', 'rent_amount', 'lease_open_ended', 'lease_term_days']))
    @php $field = $prefix ? $prefix.'['.$key.']' : $key; $label = ucwords(str_replace('_', ' ', (string) $key)); @endphp
    @if(is_array($value))
        <div class="col-12 mb-2"><fieldset class="border rounded p-2"><legend class="float-none w-auto fs-6">{{ is_numeric($key) ? 'Entry '.($key + 1) : $label }}</legend>
        @include('property.tenant-onboarding.fields', ['values' => $value, 'prefix' => $field, 'editable' => $editable])
        </fieldset></div>
    @else
        <div class="col-md-4 mb-2">
        @if($editable)
            <label class="form-label">{{ $label }}
            @if(in_array($key, ['property_id', 'unit_id']))
                <input class="form-control" name="{{ $field }}" value="{{ $value }}" readonly>
            @else
                <input class="form-control" name="{{ $field }}" value="{{ $value }}">
            @endif
            </label>
        @else
            <strong>{{ $label }}</strong><div>{{ $value ?? '—' }}</div>
        @endif
        </div>
    @endif
@endforeach
</div>
