@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Template Details</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('update_communication_templates')
                            <a href="/hrsettings/communication/templates/edit/{{ encrypt($template->id) }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">edit</span>
                                <span class="ms-1">Edit</span>
                            </a>
                            @endpermission
                            <a href="{{ route('settings.hr.communication.templates') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Left Column - Template Details -->
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Basic Information</h6>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td width="200"><strong>Template Name:</strong></td>
                                                <td>{{ $template->template_name }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Template Type:</strong></td>
                                                <td>
                                                    @php
                                                        $types = [
                                                            'general' => 'General Communication',
                                                            'contract' => 'Employee Contract',
                                                            'leave_application' => 'Leave Application',
                                                            'contract_termination' => 'Contract Termination',
                                                            'disciplinary_action' => 'Disciplinary Action'
                                                        ];
                                                    @endphp
                                                    {{ $types[$template->template_type] ?? $template->template_type }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Communication Method:</strong></td>
                                                <td>
                                                    @if($template->communication_method == 'email')
                                                        <span class="badge bg-info">Email</span>
                                                    @else
                                                        <span class="badge bg-success">SMS</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if($template->subject)
                                            <tr>
                                                <td><strong>Subject:</strong></td>
                                                <td>{{ $template->subject }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td><strong>Created By:</strong></td>
                                                <td>{{ $template->createdBy ? $template->createdBy->name : '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Created Date:</strong></td>
                                                <td>{{ $template->date_time ? $template->date_time->format('d M Y, h:i A') : '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Message Body</h6>
                                    <div class="border rounded p-3" style="white-space: pre-wrap;">{{ $template->body }}</div>
                                    @if($template->communication_method == 'sms')
                                        <small class="text-muted">Character count: {{ strlen($template->body) }}/160</small>
                                    @endif
                                </div>

                                @if($template->attachment_path)
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Attachment</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="material-symbols-outlined">attach_file</span>
                                        <a href="{{ Storage::url($template->attachment_path) }}" target="_blank" class="text-decoration-none">
                                            {{ basename($template->attachment_path) }}
                                        </a>
                                    </div>
                                </div>
                                @endif

                                @if($template->template_type == 'general' && $template->general_config)
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">General Communication Settings</h6>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td width="200"><strong>Recipient Type:</strong></td>
                                                <td>{{ (ucfirst($template->general_config['recipient_type']  == 'general') ? 'Everyone' : (ucfirst($template->general_config['recipient_type']) ?? '-')) }}</td>
                                            </tr>
                                            @if(isset($template->general_config['recipient_type']) && $template->general_config['recipient_type'] == 'staff')
                                            <tr>
                                                <td><strong>Selected Staff:</strong></td>
                                                <td>
                                                    @if(isset($template->general_config['staff_ids']) && count($template->general_config['staff_ids']) > 0)
                                                        @php
                                                            $staff = \App\Models\Staff::whereIn('id', $template->general_config['staff_ids'])->get();
                                                        @endphp
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($staff as $s)
                                                                <span class="badge bg-primary">{{ $s->full_names }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            @endif
                                            @if(isset($template->general_config['recipient_type']) && $template->general_config['recipient_type'] == 'position')
                                            <tr>
                                                <td><strong>Selected Positions:</strong></td>
                                                <td>
                                                    @if(isset($template->general_config['position_ids']) && count($template->general_config['position_ids']) > 0)
                                                        @php
                                                            $positions = \App\Models\Position::whereIn('id', $template->general_config['position_ids'])->get();
                                                        @endphp
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($positions as $pos)
                                                                <span class="badge bg-info">{{ $pos->position_name }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td><strong>Scheduled Date:</strong></td>
                                                <td>{{ $template->general_config['scheduled_date'] ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Scheduled Time:</strong></td>
                                                <td>{{ $template->general_config['scheduled_time'] ?? '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Right Column - Placeholders Used -->
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Placeholders Detected</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $allPlaceholders = [
                                                'Common' => [
                                                    '{employee_name}' => 'Employee Name',
                                                    '{staff_number}' => 'Staff Number',
                                                    '{position}' => 'Position',
                                                    '{date}' => 'Date',
                                                    '{department}' => 'Department'
                                                ],
                                                'Contract' => [
                                                    '{basic_salary}' => 'Basic Salary',
                                                    '{contract_start}' => 'Contract Start',
                                                    '{contract_end}' => 'Contract End',
                                                    '{contract_duration}' => 'Duration'
                                                ],
                                                'Leave' => [
                                                    '{leave_type}' => 'Leave Type',
                                                    '{start_date}' => 'Start Date',
                                                    '{end_date}' => 'End Date',
                                                    '{days}' => 'Days',
                                                    '{days_balance}' => 'Days Balance',
                                                    '{entitled_days}' => 'Entitled Days'
                                                ],
                                                'Termination' => [
                                                    '{severance_payment}' => 'Severance Payment',
                                                    '{years_served}' => 'Years Served',
                                                    '{termination_date}' => 'Termination Date',
                                                    '{termination_reason}' => 'Reason',
                                                    '{last_working_day}' => 'Last Working Day'
                                                ],
                                                'Disciplinary' => [
                                                    '{incident}' => 'Incident',
                                                    '{action_taken}' => 'Action Taken',
                                                    '{incident_date}' => 'Incident Date',
                                                    '{description}' => 'Description'
                                                ]
                                            ];

                                            $content = ($template->subject ?? '') . ' ' . $template->body;
                                            $foundPlaceholders = [];
                                            
                                            foreach($allPlaceholders as $category => $placeholders) {
                                                foreach($placeholders as $placeholder => $label) {
                                                    if(strpos($content, $placeholder) !== false) {
                                                        $foundPlaceholders[$category][] = ['placeholder' => $placeholder, 'label' => $label];
                                                    }
                                                }
                                            }
                                        @endphp

                                        @if(count($foundPlaceholders) > 0)
                                            @foreach($foundPlaceholders as $category => $placeholders)
                                                <div class="mb-3">
                                                    <strong class="d-block mb-2" style="font-size: 0.9rem;">{{ $category }}</strong>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($placeholders as $p)
                                                            <span class="badge bg-secondary" style="font-size: 0.75rem;">{{ $p['label'] }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <p class="text-muted mb-0">No placeholders detected in this template.</p>
                                        @endif
                                    </div>
                                </div>

                                @if($template->communication_method == 'email')
                                <div class="card bg-light mt-3">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">Preview</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="border rounded p-2 bg- text-black">
                                            @if($template->subject)
                                            <div class="mb-2">
                                                <small class="text-muted">Subject:</small><br>
                                                <strong>{{ $template->subject }}</strong>
                                            </div>
                                            <hr>
                                            @endif
                                            <div style="white-space: pre-wrap; font-size: 0.9rem;">{{ $template->body }}</div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection