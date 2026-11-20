@extends('layouts.main')

@section('content')
@php($currentMonth = now()->startOfMonth())
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <section class="card" aria-labelledby="generate-returns-title">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 id="generate-returns-title">Generate Client Returns</h5>
                <a href="{{ route('returns.batches.index') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span> Back to returns
                </a>
            </div>
            <div class="card-body">
                <form id="generateReturnsForm" novalidate>
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="return_type">Return type</label>
                            <select class="form-select form-select-sm" id="return_type" name="return_type" aria-describedby="return_type_help">
                                <option value="main">Main return</option>
                                <option value="mini">Mini return</option>
                            </select>
                            <p class="text-muted mt-2 mb-0" id="return_type_help">Uses approved invoices for the selected month.</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="returns_month">Return period</label>
                            <input type="month" class="form-control form-control-sm" id="returns_month" name="returns_month" required
                                   min="{{ $currentMonth->copy()->subMonths(12)->format('Y-m') }}"
                                   max="{{ $currentMonth->copy()->addMonth()->format('Y-m') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="property_selection">Property scope</label>
                            <select class="form-select form-select-sm" id="property_selection" name="property_selection">
                                <option value="one">Specific property</option>
                                <option value="all">All properties</option>
                            </select>
                        </div>
                        <div class="col-md-8" id="property_selector">
                            <label class="form-label mb-1" for="property_select" id="return_property_label">Property</label>
                            <select class="form-select form-select-sm" id="property_select" name="property_id" required><option></option></select>
                        </div>
                    </div>
                    <p id="return_form_error" class="text-danger mt-3 mb-0" role="alert" hidden></p>
                    <button type="submit" class="btn btn-primary btn-sm mt-3" id="review_returns">Review generation</button>
                </form>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="generation_review" tabindex="-1" aria-labelledby="generation_review_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generation_review_title">Review return generation</h5>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-4">Return type</dt><dd class="col-8" id="review_type"></dd>
                    <dt class="col-4">Period</dt><dd class="col-8" id="review_period"></dd>
                    <dt class="col-4">Property scope</dt><dd class="col-8" id="review_scope"></dd>
                </dl>
                <p class="mb-2" id="review_note"></p>
                <p class="mb-0" id="generation_status" role="status"></p>
                <p class="text-danger mb-0" id="generation_error" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" id="cancel_generation" data-bs-dismiss="modal">Back to filters</button>
                <button type="button" class="btn btn-primary btn-sm" id="generate-btn">Generate returns</button>
                <a href="{{ route('returns.batches.index') }}" class="btn btn-primary btn-sm" id="view_generated_returns" hidden>View generated returns</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(function() {
        const form = $('#generateReturnsForm');
        const type = $('#return_type');
        const scope = $('#property_selection');
        const period = $('#returns_month');
        const property = $('#property_select').select2({
            placeholder: 'Search by property name or reference',
            width: '100%',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("ajax.returns.properties.search") }}',
                dataType: 'json',
                delay: 250,
                data: params => ({ search: params.term.trim() }),
                processResults: response => ({
                    results: (response.properties || []).map(item => ({
                        id: item.id,
                        text: [item.property_name, item.description !== item.property_name && item.description, item.reference_number]
                            .filter(Boolean).join(' \u00b7 ')
                    }))
                })
            },
            language: { inputTooShort: () => 'Enter at least 2 characters.' }
        });
        const propertyControl = property.next('.select2').find('.select2-selection');
        propertyControl.attr('aria-labelledby', 'return_property_label ' + propertyControl.attr('aria-labelledby'));

        const dialog = new bootstrap.Modal(document.getElementById('generation_review'), { backdrop: 'static', keyboard: false });
        const review = $('#review_returns');
        const generate = $('#generate-btn');
        const back = $('#cancel_generation');
        const view = $('#view_generated_returns');
        const formError = $('#return_form_error');
        const status = $('#generation_status');
        const error = $('#generation_error');
        let requestData;

        const monthLabel = new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' });
        const miniHelp = 'Uses late payments that are still off the return. The main return for the period must already be approved.';

        function updateScope() {
            const mini = type.val() === 'mini';
            if (mini) scope.val('one');
            scope.find('option[value="all"]').prop('disabled', mini);
            const specificProperty = scope.val() === 'one';
            $('#property_selector').prop('hidden', !specificProperty);
            property.prop('disabled', !specificProperty);
            $('#return_type_help').text(mini ? miniHelp : 'Uses approved invoices for the selected month.');
        }

        type.add(scope).on('change', updateScope);
        form.on('change', () => formError.prop('hidden', true));
        updateScope();

        function showFormError(message, field) {
            formError.text(message).prop('hidden', false);
            field.trigger('focus');
        }

        form.on('submit', function(event) {
            event.preventDefault();
            if (review.prop('disabled')) return;

            const selectedPeriod = period.val();
            if (!selectedPeriod) {
                showFormError('Choose a return period.', period);
                return;
            }
            if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(selectedPeriod) ||
                selectedPeriod < period.attr('min') || selectedPeriod > period.attr('max')) {
                showFormError('Choose a return period within the available months.', period);
                return;
            }

            const specificProperty = scope.val() === 'one';
            if (specificProperty && !property.val()) {
                showFormError('Choose a property.', propertyControl);
                return;
            }

            const mini = type.val() === 'mini';
            requestData = {
                _token: form.find('[name="_token"]').val(),
                return_type: type.val(),
                property_selection: scope.val(),
                returns_month: selectedPeriod
            };
            if (specificProperty) requestData.property_id = property.val();

            formError.prop('hidden', true);
            $('#review_type').text(type.find(':selected').text());
            $('#review_period').text(monthLabel.format(new Date(selectedPeriod + '-01T00:00:00Z')));
            $('#review_scope').text(specificProperty ? property.find(':selected').text() : 'All properties');
            $('#review_note').text(mini ? miniHelp : 'Generation can replace unapproved returns for this period.' +
                (specificProperty ? ' If the property belongs to a merge batch, its linked properties are generated together.' : ''));
            generate.text(mini ? 'Generate mini return' : (specificProperty ? 'Generate main return' : 'Generate for all properties'));
            status.text('');
            error.text('');
            dialog.show(review[0]);
        });

        $('#generation_review').on('hidden.bs.modal', () => review.trigger('focus'));

        generate.on('click', function() {
            if (!requestData || generate.prop('disabled')) return;

            generate.add(back).add(review).prop('disabled', true);
            status.text('Generating returns\u2026');
            error.text('');
            $.ajax({
                url: '{{ route("returns.generate.process") }}',
                method: 'POST',
                dataType: 'json',
                data: requestData
            }).done(function(response) {
                if (!response.success) {
                    showGenerationError(response.message);
                    return;
                }

                status.text(response.message || 'Returns generated.');
                view.attr('href', response.redirect_url || '{{ route("returns.batches.index") }}').prop('hidden', false).trigger('focus');
                generate.add(back).prop('hidden', true);
            }).fail(function(xhr) {
                showGenerationError(xhr.responseJSON?.message);
            });
        });

        function showGenerationError(message) {
            status.text('');
            error.text(message || 'Could not generate returns. Try again.');
            generate.add(back).add(review).prop('disabled', false);
        }
    });
</script>
@endsection
