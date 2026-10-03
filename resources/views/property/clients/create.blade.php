@extends('layouts.main')
@section('content')
<style>
    /* Individual is the default selection, so hide Company/Partnership-only fields
       until toggleClientTypeFields() runs (or immediately, if old('client_type_id')
       was 3 or 4). */
    .company-only, .partnership-only { display: none; }

    /* Banks/Other Contacts/Next of Kin/Partners — compact, line-free rows
       rather than a bordered table (table-borderless alone gets overridden by
       the theme's own table styling, so these are explicit + !important). */
    .repeater-table { margin-bottom: 0; width: auto; }
    .repeater-table,
    .repeater-table tr,
    .repeater-table td,
    .repeater-table th {
        border: none !important;
        box-shadow: none !important;
    }
    .repeater-table td {
        padding: 90px 160px 90px 0 !important;
        vertical-align: middle;
        white-space: nowrap;
        font-size: 44px;
        line-height: 1.8;
    }
    .repeater-table td:last-child {
        padding-right: 0 !important;
    }
    .repeater-table th {
        padding: 0 60px 20px 0 !important;
        font-size: 16px;
        font-weight: 600;
        text-transform: uppercase;
        color: #6c757d;
        white-space: nowrap;
    }
    .repeater-table th:last-child {
        padding-right: 0 !important;
    }
    .repeater-table tbody tr:hover {
        background-color: transparent !important;
    }
</style>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Add New Client</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button type="submit" form="clientCreateForm" class="btn btn-primary btn-sm d-flex align-items-center" id="createButton">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">check_circle</span>
                                <span>Save Client</span>
                            </button>
                            <a href="{{ route('property.clients.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to List</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> Please correct the following errors:
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <form action="{{ route('property.clients.store') }}" method="POST" id="clientCreateForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken }}">

                            <!-- Nav Tabs -->
                            <ul class="nav nav-tabs" id="clientCreateTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">person</span>
                                        Personal Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab" aria-controls="banking" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">account_balance</span>
                                        Bank Details
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab" aria-controls="contacts" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">contact_phone</span>
                                        Other Contacts
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="nextofkin-tab" data-bs-toggle="tab" data-bs-target="#nextofkin" type="button" role="tab" aria-controls="nextofkin" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">family_restroom</span>
                                        Next of Kin
                                    </button>
                                </li>
                                <li class="nav-item partnership-only" role="presentation">
                                    <button class="nav-link" id="partners-tab" data-bs-toggle="tab" data-bs-target="#partners" type="button" role="tab" aria-controls="partners" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">groups</span>
                                        Partners
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content mt-3" id="clientCreateTabsContent">
                                <!-- Personal Details Tab -->
                                <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                                    <div class="row">
                                        <!-- Registration Information Section -->
                                        <div class="col-12 mb-3">
                                            <h6 class="text-muted border-bottom pb-2">Registration Information</h6>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="select_time" class="form-label">Select Time</label>
                                            <input type="datetime-local" class="form-control @error('select_time') is-invalid @enderror" id="select_time" name="select_time" value="{{ old('select_time', now()->format('Y-m-d\TH:i')) }}">
                                            @error('select_time')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-4 mb-3">
                                            <label for="registered_date" class="form-label">Registration Date</label>
                                            <input type="datetime-local" class="form-control @error('registered_date') is-invalid @enderror" id="registered_date" name="registered_date" value="{{ old('registered_date', now()->format('Y-m-d\TH:i')) }}">
                                            @error('registered_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-4 mb-3">
                                            <label for="client_type_id" class="form-label">Client Type <span class="text-danger">*</span></label>
                                            <select class="form-select @error('client_type_id') is-invalid @enderror" id="client_type_id" name="client_type_id" required>
                                                <option value="1" {{ old('client_type_id', '1') == '1' ? 'selected' : '' }}>Individual</option>
                                                <option value="3" {{ old('client_type_id') == '3' ? 'selected' : '' }}>Company</option>
                                                <option value="4" {{ old('client_type_id') == '4' ? 'selected' : '' }}>Partnership and Trust</option>
                                            </select>
                                            @error('client_type_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Personal Information Section -->
                                        <div class="col-12 mb-3 mt-3 individual-only">
                                            <h6 class="text-muted border-bottom pb-2">Personal Information</h6>
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only" id="individualFields">
                                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                            <select class="form-select @error('title') is-invalid @enderror" id="title" name="title">
                                                <option value="">Select</option>
                                                <option value="Mr" {{ old('title') == 'Mr' ? 'selected' : '' }}>Mr</option>
                                                <option value="Mrs" {{ old('title') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                                                <option value="Miss" {{ old('title') == 'Miss' ? 'selected' : '' }}>Miss</option>
                                                <option value="Ms" {{ old('title') == 'Ms' ? 'selected' : '' }}>Ms</option>
                                                <option value="Dr" {{ old('title') == 'Dr' ? 'selected' : '' }}>Dr</option>
                                                <option value="Prof" {{ old('title') == 'Prof' ? 'selected' : '' }}>Prof</option>
                                            </select>
                                            @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="Enter first name">
                                            @error('first_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control @error('middle_name') is-invalid @enderror" id="middle_name" name="middle_name" value="{{ old('middle_name') }}" placeholder="Enter middle name">
                                            @error('middle_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="Enter last name">
                                            @error('last_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Company / Partnership Information Section -->
                                        <div class="col-12 mb-3 mt-3 company-only partnership-only" id="companyHeading">
                                            <h6 class="text-muted border-bottom pb-2" id="entityInfoHeading">Company Information</h6>
                                        </div>

                                        <div class="col-md-6 mb-3 company-only partnership-only" id="companyFields">
                                            <label for="company_name" class="form-label" id="entityNameLabel">Company Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name') }}" placeholder="Enter company name">
                                            @error('company_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 mb-3 company-only">
                                            <label for="company_registration_number" class="form-label">Registration Number</label>
                                            <input type="text" class="form-control @error('company_registration_number') is-invalid @enderror" id="company_registration_number" name="company_registration_number" value="{{ old('company_registration_number') }}" placeholder="Enter certificate of incorporation / registration number">
                                            @error('company_registration_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="company_registration_number_duplicate"></small>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="nationality_id" class="form-label">Nationality</label>
                                            <select class="form-select @error('nationality_id') is-invalid @enderror" id="nationality_id" name="nationality_id">
                                                <option value="">Please select</option>
                                                <option value="1">Afghanistan</option>
                                                <option value="2">Albania</option>
                                                <option value="3">Algeria</option>
                                                <option value="4">American Samoa</option>
                                                <option value="5">Andorra</option>
                                                <option value="6">Angola</option>
                                                <option value="7">Anguilla</option>
                                                <option value="8">Antarctica</option>
                                                <option value="9">Antigua and Barbuda</option>
                                                <option value="10">Argentina</option>
                                                <option value="11">Armenia</option>
                                                <option value="12">Aruba</option>
                                                <option value="13">Australia</option>
                                                <option value="14">Austria</option>
                                                <option value="15">Azerbaijan</option>
                                                <option value="16">Bahamas</option>
                                                <option value="17">Bahrain</option>
                                                <option value="18">Bangladesh</option>
                                                <option value="19">Barbados</option>
                                                <option value="20">Belarus</option>
                                                <option value="21">Belgium</option>
                                                <option value="22">Belize</option>
                                                <option value="23">Benin</option>
                                                <option value="24">Bermuda</option>
                                                <option value="25">Bhutan</option>
                                                <option value="26">Bolivia</option>
                                                <option value="27">Bosnia and Herzegovina</option>
                                                <option value="28">Botswana</option>
                                                <option value="29">Bouvet Island</option>
                                                <option value="30">Brazil</option>
                                                <option value="31">British Indian Ocean Territory</option>
                                                <option value="32">Brunei Darussalam</option>
                                                <option value="33">Bulgaria</option>
                                                <option value="34">Burkina Faso</option>
                                                <option value="35">Burundi</option>
                                                <option value="36">Cambodia</option>
                                                <option value="37">Cameroon</option>
                                                <option value="38">Canada</option>
                                                <option value="39">Cape Verde</option>
                                                <option value="40">Cayman Islands</option>
                                                <option value="41">Central African Republic</option>
                                                <option value="42">Chad</option>
                                                <option value="43">Chile</option>
                                                <option value="44">China</option>
                                                <option value="45">Christmas Island</option>
                                                <option value="46">Cocos (Keeling) Islands</option>
                                                <option value="47">Colombia</option>
                                                <option value="48">Comoros</option>
                                                <option value="51">Cook Islands</option>
                                                <option value="52">Costa Rica</option>
                                                <option value="53">Croatia (Hrvatska)</option>
                                                <option value="54">Cuba</option>
                                                <option value="55">Cyprus</option>
                                                <option value="56">Czech Republic</option>
                                                <option value="49">Democratic Republic of the Congo</option>
                                                <option value="57">Denmark</option>
                                                <option value="58">Djibouti</option>
                                                <option value="59">Dominica</option>
                                                <option value="60">Dominican Republic</option>
                                                <option value="61">East Timor</option>
                                                <option value="62">Ecuador</option>
                                                <option value="63">Egypt</option>
                                                <option value="64">El Salvador</option>
                                                <option value="65">Equatorial Guinea</option>
                                                <option value="66">Eritrea</option>
                                                <option value="67">Estonia</option>
                                                <option value="68">Ethiopia</option>
                                                <option value="69">Falkland Islands (Malvinas)</option>
                                                <option value="70">Faroe Islands</option>
                                                <option value="71">Fiji</option>
                                                <option value="72">Finland</option>
                                                <option value="73">France</option>
                                                <option value="74">France, Metropolitan</option>
                                                <option value="75">French Guiana</option>
                                                <option value="76">French Polynesia</option>
                                                <option value="77">French Southern Territories</option>
                                                <option value="78">Gabon</option>
                                                <option value="79">Gambia</option>
                                                <option value="80">Georgia</option>
                                                <option value="81">Germany</option>
                                                <option value="82">Ghana</option>
                                                <option value="83">Gibraltar</option>
                                                <option value="85">Greece</option>
                                                <option value="86">Greenland</option>
                                                <option value="87">Grenada</option>
                                                <option value="88">Guadeloupe</option>
                                                <option value="89">Guam</option>
                                                <option value="90">Guatemala</option>
                                                <option value="84">Guernsey</option>
                                                <option value="91">Guinea</option>
                                                <option value="92">Guinea-Bissau</option>
                                                <option value="93">Guyana</option>
                                                <option value="94">Haiti</option>
                                                <option value="95">Heard and Mc Donald Islands</option>
                                                <option value="96">Honduras</option>
                                                <option value="97">Hong Kong</option>
                                                <option value="98">Hungary</option>
                                                <option value="99">Iceland</option>
                                                <option value="100">India</option>
                                                <option value="102">Indonesia</option>
                                                <option value="103">Iran (Islamic Republic of)</option>
                                                <option value="104">Iraq</option>
                                                <option value="105">Ireland</option>
                                                <option value="101">Isle of Man</option>
                                                <option value="106">Israel</option>
                                                <option value="107">Italy</option>
                                                <option value="108">Ivory Coast</option>
                                                <option value="110">Jamaica</option>
                                                <option value="111">Japan</option>
                                                <option value="109">Jersey</option>
                                                <option value="112">Jordan</option>
                                                <option value="113">Kazakhstan</option>
                                                <option value="114" selected="selected">Kenya</option>
                                                <option value="115">Kiribati</option>
                                                <option value="116">Korea, Democratic People's Republic of</option>
                                                <option value="117">Korea, Republic of</option>
                                                <option value="118">Kosovo</option>
                                                <option value="119">Kuwait</option>
                                                <option value="120">Kyrgyzstan</option>
                                                <option value="121">Lao People's Democratic Republic</option>
                                                <option value="122">Latvia</option>
                                                <option value="123">Lebanon</option>
                                                <option value="124">Lesotho</option>
                                                <option value="125">Liberia</option>
                                                <option value="126">Libyan Arab Jamahiriya</option>
                                                <option value="127">Liechtenstein</option>
                                                <option value="128">Lithuania</option>
                                                <option value="129">Luxembourg</option>
                                                <option value="130">Macau</option>
                                                <option value="132">Madagascar</option>
                                                <option value="133">Malawi</option>
                                                <option value="134">Malaysia</option>
                                                <option value="135">Maldives</option>
                                                <option value="136">Mali</option>
                                                <option value="137">Malta</option>
                                                <option value="138">Marshall Islands</option>
                                                <option value="139">Martinique</option>
                                                <option value="140">Mauritania</option>
                                                <option value="141">Mauritius</option>
                                                <option value="142">Mayotte</option>
                                                <option value="143">Mexico</option>
                                                <option value="144">Micronesia, Federated States of</option>
                                                <option value="145">Moldova, Republic of</option>
                                                <option value="146">Monaco</option>
                                                <option value="147">Mongolia</option>
                                                <option value="148">Montenegro</option>
                                                <option value="149">Montserrat</option>
                                                <option value="150">Morocco</option>
                                                <option value="151">Mozambique</option>
                                                <option value="152">Myanmar</option>
                                                <option value="153">Namibia</option>
                                                <option value="154">Nauru</option>
                                                <option value="155">Nepal</option>
                                                <option value="156">Netherlands</option>
                                                <option value="157">Netherlands Antilles</option>
                                                <option value="158">New Caledonia</option>
                                                <option value="159">New Zealand</option>
                                                <option value="160">Nicaragua</option>
                                                <option value="161">Niger</option>
                                                <option value="162">Nigeria</option>
                                                <option value="163">Niue</option>
                                                <option value="164">Norfolk Island</option>
                                                <option value="131">North Macedonia</option>
                                                <option value="165">Northern Mariana Islands</option>
                                                <option value="166">Norway</option>
                                                <option value="167">Oman</option>
                                                <option value="168">Pakistan</option>
                                                <option value="169">Palau</option>
                                                <option value="170">Palestine</option>
                                                <option value="171">Panama</option>
                                                <option value="172">Papua New Guinea</option>
                                                <option value="173">Paraguay</option>
                                                <option value="174">Peru</option>
                                                <option value="175">Philippines</option>
                                                <option value="176">Pitcairn</option>
                                                <option value="177">Poland</option>
                                                <option value="178">Portugal</option>
                                                <option value="179">Puerto Rico</option>
                                                <option value="180">Qatar</option>
                                                <option value="50">Republic of Congo</option>
                                                <option value="181">Reunion</option>
                                                <option value="182">Romania</option>
                                                <option value="183">Russian Federation</option>
                                                <option value="184">Rwanda</option>
                                                <option value="185">Saint Kitts and Nevis</option>
                                                <option value="186">Saint Lucia</option>
                                                <option value="187">Saint Vincent and the Grenadines</option>
                                                <option value="188">Samoa</option>
                                                <option value="189">San Marino</option>
                                                <option value="190">Sao Tome and Principe</option>
                                                <option value="191">Saudi Arabia</option>
                                                <option value="192">Senegal</option>
                                                <option value="193">Serbia</option>
                                                <option value="194">Seychelles</option>
                                                <option value="195">Sierra Leone</option>
                                                <option value="196">Singapore</option>
                                                <option value="197">Slovakia</option>
                                                <option value="198">Slovenia</option>
                                                <option value="199">Solomon Islands</option>
                                                <option value="200">Somalia</option>
                                                <option value="201">South Africa</option>
                                                <option value="202">South Georgia South Sandwich Islands</option>
                                                <option value="203">South Sudan</option>
                                                <option value="204">Spain</option>
                                                <option value="205">Sri Lanka</option>
                                                <option value="206">St. Helena</option>
                                                <option value="207">St. Pierre and Miquelon</option>
                                                <option value="208">Sudan</option>
                                                <option value="209">Suriname</option>
                                                <option value="210">Svalbard and Jan Mayen Islands</option>
                                                <option value="211">Swaziland</option>
                                                <option value="212">Sweden</option>
                                                <option value="213">Switzerland</option>
                                                <option value="214">Syrian Arab Republic</option>
                                                <option value="215">Taiwan</option>
                                                <option value="216">Tajikistan</option>
                                                <option value="217">Tanzania, United Republic of</option>
                                                <option value="218">Thailand</option>
                                                <option value="219">Togo</option>
                                                <option value="220">Tokelau</option>
                                                <option value="221">Tonga</option>
                                                <option value="222">Trinidad and Tobago</option>
                                                <option value="223">Tunisia</option>
                                                <option value="224">Turkey</option>
                                                <option value="225">Turkmenistan</option>
                                                <option value="226">Turks and Caicos Islands</option>
                                                <option value="227">Tuvalu</option>
                                                <option value="228">Uganda</option>
                                                <option value="229">Ukraine</option>
                                                <option value="230">United Arab Emirates</option>
                                                <option value="231">United Kingdom</option>
                                                <option value="232">United States</option>
                                                <option value="233">United States minor outlying islands</option>
                                                <option value="234">Uruguay</option>
                                                <option value="235">Uzbekistan</option>
                                                <option value="236">Vanuatu</option>
                                                <option value="237">Vatican City State</option>
                                                <option value="238">Venezuela</option>
                                                <option value="239">Vietnam</option>
                                                <option value="240">Virgin Islands (British)</option>
                                                <option value="241">Virgin Islands (U.S.)</option>
                                                <option value="242">Wallis and Futuna Islands</option>
                                                <option value="243">Western Sahara</option>
                                                <option value="244">Yemen</option>
                                                <option value="245">Zambia</option>
                                                <option value="246">Zimbabwe</option>
                                            </select>
                                            @error('nationality_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="preferred_language_id" class="form-label">Preferred Language</label>
                                            <select class="form-select @error('preferred_language_id') is-invalid @enderror" id="preferred_language_id" name="preferred_language_id">
                                                <option value="">Select Language</option>
                                                <option value="1" {{ old('preferred_language_id') == '1' ? 'selected' : '' }}>English</option>
                                                <option value="2" {{ old('preferred_language_id') == '2' ? 'selected' : '' }}>Swahili</option>
                                            </select>
                                            @error('preferred_language')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="photo" class="form-label">Client Photo</label>
                                            <input type="file" class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" accept="image/*">
                                            <small class="text-muted">Max 2MB (JPG, PNG, GIF)</small>
                                            @error('photo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <!-- Identification Documents Section -->
                                        <div class="col-12 mb-3 mt-3">
                                            <h6 class="text-muted border-bottom pb-2">Identification & Tax Information</h6>
                                        </div>
                                
                                        <div class="col-md-3 mb-3 individual-only" id="individualIdFields">
                                            <label for="national_id" class="form-label">National ID</label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id') }}" placeholder="Enter national ID number">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-danger d-none duplicate-check-message" id="national_id_duplicate"></small>
                                        </div>

                                        <div class="col-md-3 mb-3 individual-only">
                                            <label for="passport_number" class="form-label">Passport Number</label>
                                            <input type="text" class="form-control @error('passport_number') is-invalid @enderror" id="passport_number" name="passport_number" value="{{ old('passport_number') }}" placeholder="Enter passport number">
                                            @error('passport_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="pin_number" class="form-label">PIN Number</label>
                                            <input type="text" class="form-control @error('pin_number') is-invalid @enderror" id="pin_number" name="pin_number" value="{{ old('pin_number') }}" placeholder="Enter KRA PIN number">
                                            @error('pin_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="vat_number" class="form-label">VAT Number</label>
                                            <input type="text" class="form-control @error('vat_number') is-invalid @enderror" id="vat_number" name="vat_number" value="{{ old('vat_number') }}" placeholder="Enter VAT number">
                                            @error('vat_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Contact Person Section (Company only) -->
                                        <div class="col-12 mb-3 mt-3 company-only" id="contactPersonHeading">
                                            <h6 class="text-muted border-bottom pb-2">Contact Person</h6>
                                            <small class="text-muted">The individual who deals with this account day-to-day</small>
                                        </div>

                                        <div class="col-md-3 mb-3 company-only" id="companyContactFields">
                                            <label for="contact_person_name" class="form-label">Contact Person Name</label>
                                            <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name') }}" placeholder="Enter contact person's name">
                                            @error('contact_person_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_title" class="form-label">Position / Title</label>
                                            <input type="text" class="form-control @error('contact_person_title') is-invalid @enderror" id="contact_person_title" name="contact_person_title" value="{{ old('contact_person_title') }}" placeholder="e.g. Director, Property Manager">
                                            @error('contact_person_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_mobile" class="form-label">Contact Person Mobile</label>
                                            <input type="text" class="form-control @error('contact_person_mobile') is-invalid @enderror" id="contact_person_mobile" name="contact_person_mobile" value="{{ old('contact_person_mobile') }}" placeholder="254XXXXXXXXX">
                                            @error('contact_person_mobile')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3 mb-3 company-only">
                                            <label for="contact_person_email" class="form-label">Contact Person Email</label>
                                            <input type="email" class="form-control @error('contact_person_email') is-invalid @enderror" id="contact_person_email" name="contact_person_email" value="{{ old('contact_person_email') }}" placeholder="name@example.com">
                                            @error('contact_person_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Contact Information Section -->
                                        <div class="col-12 mb-3 mt-3">
                                            <h6 class="text-muted border-bottom pb-2">Contact Information</h6>
                                        </div>
                                
                                        <div class="col-md-4 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" id="mobile_number" name="mobile_number" value="{{ old('mobile_number') }}" placeholder="254XXXXXXXXX" maxlength="12" pattern="254[0-9]{9}" required>
                                            <small class="text-muted">Format: 254XXXXXXXXX (12 characters)</small>
                                            @error('mobile_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-4 mb-3">
                                            <label for="email_address" class="form-label">Email Address</label>
                                            <input type="email" class="form-control @error('email_address') is-invalid @enderror" id="email_address" name="email_address" value="{{ old('email_address') }}" placeholder="name@example.com">
                                            @error('email_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <!-- Address Information Section -->
                                        <div class="col-12 mb-3 mt-3">
                                            <h6 class="text-muted border-bottom pb-2">Address Information</h6>
                                        </div>
                                
                                        <div class="col-md-12 mb-3">
                                            <label for="address" class="form-label">Primary Address</label>
                                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Enter primary address">{{ old('address') }}</textarea>
                                            @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-6 mb-3">
                                            <label for="address_2" class="form-label">Secondary Address</label>
                                            <textarea class="form-control @error('address_2') is-invalid @enderror" id="address_2" name="address_2" rows="2" placeholder="Enter secondary address">{{ old('address_2') }}</textarea>
                                            @error('address_2')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-6 mb-3">
                                            <label for="address_3" class="form-label">Additional Address</label>
                                            <textarea class="form-control @error('address_3') is-invalid @enderror" id="address_3" name="address_3" rows="2" placeholder="Enter additional address">{{ old('address_3') }}</textarea>
                                            @error('address_3')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Details Tab -->
                                <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Bank Accounts</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <thead><tr><th>Bank</th><th>Branch</th><th>Account Name</th><th>Account Number</th><th class="text-end">Action</th></tr></thead>
                                            <tbody id="bankAccountsTableBody">
                                                <tr><td colspan="5" class="text-center text-muted">No bank accounts added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Other Contacts Tab -->
                                <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Other Contacts</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <thead><tr><th>Names</th><th>Email</th><th>Mobile Number</th><th class="text-end">Action</th></tr></thead>
                                            <tbody id="contactsTableBody">
                                                <tr><td colspan="4" class="text-center text-muted">No contacts added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Next of Kin Tab -->
                                <div class="tab-pane fade" id="nextofkin" role="tabpanel" aria-labelledby="nextofkin-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Next of Kin</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addNextOfKinModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <thead><tr><th>Name</th><th>Mobile Number</th><th>Email</th><th>Relation</th><th>National ID</th><th class="text-end">Action</th></tr></thead>
                                            <tbody id="nextOfKinTableBody">
                                                <tr><td colspan="6" class="text-center text-muted">No next of kin added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Partners Tab (Partnership and Trust only) -->
                                <div class="tab-pane fade partnership-only" id="partners" role="tabpanel" aria-labelledby="partners-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Partners</h6>
                                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
                                            <span class="material-symbols-outlined me-1" style="font-size:16px;">add</span>
                                            Add
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table repeater-table">
                                            <thead><tr><th>Name</th><th>National ID</th><th>KRA PIN</th><th>Phone</th><th class="text-end">Action</th></tr></thead>
                                            <tbody id="partnersTableBody">
                                                <tr><td colspan="5" class="text-center text-muted">No partners added yet</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Add Bank Account Modal -->
                        <div class="modal fade" id="addBankModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Bank Account</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Bank</label>
                                            <select class="form-select" id="modal_bank_id">
                                                <option value="">Please select</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Branch</label>
                                            <select class="form-select" id="modal_branch_id">
                                                <option value="">Please select</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control" id="modal_account_name" placeholder="Enter account name">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control" id="modal_account_number" placeholder="Enter account number">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="saveBankBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Contact Modal -->
                        <div class="modal fade" id="addContactModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Contact</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Names</label>
                                            <input type="text" class="form-control" id="modal_contact_names" placeholder="Enter contact name">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" id="modal_contact_email" placeholder="name@example.com">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mobile Number</label>
                                            <input type="text" class="form-control" id="modal_contact_mobile" placeholder="254XXXXXXXXX">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="saveContactBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Next of Kin Modal -->
                        <div class="modal fade" id="addNextOfKinModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Next of Kin</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="modal_nok_first_name" placeholder="Enter first name">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Second Name</label>
                                                <input type="text" class="form-control" id="modal_nok_second_name" placeholder="Enter second name">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="modal_nok_last_name" placeholder="Enter last name">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Mobile Number</label>
                                                <input type="text" class="form-control" id="modal_nok_mobile" placeholder="254XXXXXXXXX">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="modal_nok_email" placeholder="name@example.com">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Relation</label>
                                                <select class="form-select" id="modal_nok_relation">
                                                    <option value="">Please select</option>
                                                    <option value="Spouse">Spouse</option>
                                                    <option value="Parent">Parent</option>
                                                    <option value="Sibling">Sibling</option>
                                                    <option value="Child">Child</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">National ID</label>
                                                <input type="text" class="form-control" id="modal_nok_national_id" placeholder="Enter national ID number">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="saveNextOfKinBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Partner Modal -->
                        <div class="modal fade" id="addPartnerModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Partner</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" id="modal_partner_name" placeholder="Enter partner's name">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">National ID</label>
                                            <input type="text" class="form-control" id="modal_partner_national_id" placeholder="Enter national ID number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">KRA PIN</label>
                                            <input type="text" class="form-control" id="modal_partner_kra_pin" placeholder="Enter KRA PIN">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="modal_partner_phone" placeholder="254XXXXXXXXX">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" id="savePartnerBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let banksData = [];
        let branchesData = [];

        // Native HTML5 validation can't focus a field inside a hidden (inactive)
        // Bootstrap tab-pane — the browser just silently aborts the submit with no
        // visible feedback. Switch to the offending field's tab as soon as it's
        // flagged invalid, so the browser can focus it and show its error bubble.
        document.querySelectorAll('#clientCreateForm [required], #clientCreateForm [pattern]').forEach(function(field) {
            field.addEventListener('invalid', function() {
                var pane = this.closest('.tab-pane');
                if (pane && !pane.classList.contains('active')) {
                    var tabButton = document.getElementById(pane.id + '-tab');
                    if (tabButton) {
                        bootstrap.Tab.getOrCreateInstance(tabButton).show();
                    }
                }
            });
        });

        // Toggle Individual vs Company vs Partnership/Trust fields based on Client Type
        function toggleClientTypeFields() {
            var typeId = $('#client_type_id').val();
            var isIndividual = typeId === '1';
            var isCompany = typeId === '3';
            var isPartnership = typeId === '4';
            var isEntity = isCompany || isPartnership;

            $('.individual-only').toggle(isIndividual);
            // .company-only also covers the shared entity-name field (used by both
            // Company and Partnership) — company_registration_number/contact-person
            // fields inside it are further scoped to Company alone, below.
            $('.company-only').toggle(isEntity);
            $('.partnership-only').toggle(isPartnership);

            $('#entityInfoHeading').text(isPartnership ? 'Partnership / Trust Information' : 'Company Information');
            $('#entityNameLabel').html(isPartnership ? 'Partnership/Trust Name <span class="text-danger">*</span>' : 'Company Name <span class="text-danger">*</span>');
            $('#company_name').attr('placeholder', isPartnership ? 'Enter partnership/trust name' : 'Enter company name');

            // Registration number + contact person are Company-only, not shared with Partnership.
            $('.company-only:not(.partnership-only)').toggle(isCompany);

            // First/Last Name/Gender are required for Individual; entity name for Company/Partnership.
            $('#first_name, #last_name, #gender').prop('required', isIndividual);
            $('#company_name').prop('required', isEntity);
        }

        $('#client_type_id').on('change', toggleClientTypeFields);
        toggleClientTypeFields();

        // Live duplicate-identifier checker — a landlord can only be registered once,
        // by National ID (Individual) or Registration Number (Company).
        function checkDuplicateIdentifier(fieldId, clientTypeId, label) {
            var $field = $('#' + fieldId);
            var $msg = $('#' + fieldId + '_duplicate');
            var value = $field.val().trim();

            $msg.addClass('d-none').text('');
            $field.removeClass('is-invalid').removeData('duplicate-flagged');

            if (!value) {
                return;
            }

            $.ajax({
                url: "{{ route('property.clients.check-identifier') }}",
                type: 'GET',
                data: {
                    client_type_id: clientTypeId,
                    value: value
                },
                success: function(response) {
                    // The field may have changed (or been cleared) while the request was
                    // in flight — only act on the result if it still matches.
                    if ($field.val().trim() !== value) {
                        return;
                    }
                    if (response.exists) {
                        var details = [response.name, response.reference_number].filter(Boolean).join(' — ');
                        $msg.removeClass('d-none').text('A landlord with this ' + label + ' already exists' + (details ? ': ' + details : '') + '.');
                        $field.addClass('is-invalid').data('duplicate-flagged', true);
                    }
                },
                error: function(xhr) {
                    console.error('Duplicate identifier check failed:', xhr);
                }
            });
        }

        $('#national_id').on('blur', function() {
            checkDuplicateIdentifier('national_id', 1, 'National ID');
        });

        $('#company_registration_number').on('blur', function() {
            checkDuplicateIdentifier('company_registration_number', 3, 'Registration Number');
        });

        // Block submission while a flagged duplicate is showing for the active client
        // type — runs before the spinner handler below so a blocked submit doesn't
        // leave the Save button stuck disabled.
        $('#clientCreateForm').on('submit', function(e) {
            var isCompany = $('#client_type_id').val() === '3';
            var $flagged = isCompany ? $('#company_registration_number') : $('#national_id');

            if ($flagged.data('duplicate-flagged')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var tabButton = document.getElementById('personal-tab');
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
                toastr.error('Please resolve the duplicate ' + (isCompany ? 'registration number' : 'National ID') + ' before submitting.');
            }
        });

        // Handle form submission
        $('#clientCreateForm').on('submit', function() {
            var submitButton = $('#createButton');
            var spinner = $('#submitSpinner');

            // Show spinner and disable button
            spinner.removeClass('d-none');
            submitButton.prop('disabled', true);
            submitButton.css('opacity', '0.6');
        });

        // Load banks and branches
        $.ajax({
            url: "{{ url('/ajax/banks-and-branches') }}",
            type: 'GET',
            success: function(response) {
                if (response.banks) {
                    banksData = response.banks;
                    populateBankDropdowns();
                }
                if (response.branches) {
                    branchesData = response.branches;
                }
            },
            error: function(xhr) {
                console.error('Failed to load banks and branches:', xhr);
            }
        });

        // Function to populate all bank dropdowns
        function populateBankDropdowns() {
            $('.bank-select').each(function() {
                let $select = $(this);
                let selectedValue = $select.val();
                $select.empty().append('<option value="">Please select</option>');
                banksData.forEach(function(bank) {
                    let selected = (selectedValue == bank.id) ? 'selected' : '';
                    $select.append(`<option value="${bank.id}" ${selected}>${bank.bank_name}</option>`);
                });
            });

            // Add Bank Account modal's select isn't part of the main form, so it's
            // populated directly rather than via the .bank-select class above.
            let $modalBank = $('#modal_bank_id');
            $modalBank.empty().append('<option value="">Please select</option>');
            banksData.forEach(function(bank) {
                $modalBank.append(`<option value="${bank.id}">${bank.bank_name}</option>`);
            });
        }

        // Mobile number validation
        $('#mobile_number').on('input', function() {
            let value = $(this).val();
            // Remove non-numeric characters
            value = value.replace(/[^0-9]/g, '');

            // Local format (0712345678) — drop the leading 0 before prefixing 254
            if (value.startsWith('0')) {
                value = '254' + value.substring(1);
            } else if (value.length > 0 && !value.startsWith('254')) {
                value = '254' + value;
            }

            // Limit to 12 characters
            if (value.length > 12) {
                value = value.substring(0, 12);
            }

            $(this).val(value);
        });

        // ─────────────────────────────────────────────────────────────────
        // Banks / Other Contacts / Next of Kin / Partners — modal + AJAX
        // table, staged in clients_draft_repeaters (tagged by draft_token)
        // until the client itself is saved. See ClientDraftRepeatersController.
        // ─────────────────────────────────────────────────────────────────
        var draftToken = $('#draft_token').val();

        function draftRepeaterUrl() {
            return "{{ url('/ajax/clients/draft-repeaters') }}";
        }

        function loadDraftTable(kind, tbodySelector, emptyColspan, rowRenderer) {
            $.get(draftRepeaterUrl(), { draft_token: draftToken, kind: kind })
                .done(function(res) {
                    var rows = (res && res.data) ? res.data : [];
                    if (!rows.length) {
                        $(tbodySelector).html('<tr><td colspan="' + emptyColspan + '" class="text-center text-muted">No entries added yet</td></tr>');
                        return;
                    }
                    var html = '';
                    rows.forEach(function(row) {
                        html += rowRenderer(row);
                    });
                    $(tbodySelector).html(html);
                })
                .fail(function() {
                    toastr.error('Failed to load entries.');
                });
        }

        function removeDraftRow(encryptedId, onDone) {
            $.ajax({
                url: draftRepeaterUrl() + '/' + encryptedId,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function() {
                    toastr.success('Removed successfully');
                    onDone();
                },
                error: function() {
                    toastr.error('Failed to remove entry.');
                }
            });
        }

        // Enforce capital case on the Banks/Other Contacts/Next of Kin/Partners
        // modal forms (names, account name, IDs, etc.) — matches the rest of the
        // app's naming convention. Email inputs are a different type, so they're
        // untouched; phone/number fields are unaffected by uppercasing digits.
        $(document).on('input', '#addBankModal input[type="text"], #addContactModal input[type="text"], #addNextOfKinModal input[type="text"], #addPartnerModal input[type="text"]', function() {
            var pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });

        function escAttr(v) {
            return (v === null || v === undefined) ? '' : String(v).replace(/"/g, '&quot;');
        }

        function actionIcons(editCls, encryptedId, reloadKey, rawAttrs) {
            var attrs = '';
            Object.keys(rawAttrs || {}).forEach(function(key) {
                attrs += ' data-' + key + '="' + escAttr(rawAttrs[key]) + '"';
            });
            return '<td class="text-end">' +
                '<span class="material-symbols-outlined ' + editCls + ' text-primary" role="button" title="Edit" style="font-size:20px;cursor:pointer;margin-right:14px;" data-id="' + encryptedId + '"' + attrs + '>edit</span>' +
                '<span class="material-symbols-outlined remove-draft-row text-primary" role="button" title="Remove" style="font-size:20px;cursor:pointer;" data-id="' + encryptedId + '" data-reload="' + reloadKey + '">delete</span>' +
                '</td>';
        }

        function reloadBanksTable() {
            loadDraftTable('bank', '#bankAccountsTableBody', 5, function(row) {
                return '<tr>' +
                    '<td>' + (row.bank_name || '-') + '</td>' +
                    '<td>' + (row.branch_name || '-') + '</td>' +
                    '<td>' + (row.account_name || '-') + '</td>' +
                    '<td>' + (row.account_number || '-') + '</td>' +
                    actionIcons('edit-bank-row', row.encrypted_id, 'banks', {
                        'bank-id': row.bank_id, 'branch-id': row.branch_id,
                        'account-name': row.account_name, 'account-number': row.account_number
                    }) +
                    '</tr>';
            });
        }

        function reloadContactsTable() {
            loadDraftTable('contact', '#contactsTableBody', 4, function(row) {
                return '<tr>' +
                    '<td>' + (row.names || '-') + '</td>' +
                    '<td>' + (row.email || '-') + '</td>' +
                    '<td>' + (row.mobile || '-') + '</td>' +
                    actionIcons('edit-contact-row', row.encrypted_id, 'contacts', {
                        names: row.names, email: row.email, mobile: row.mobile
                    }) +
                    '</tr>';
            });
        }

        function reloadNextOfKinTable() {
            loadDraftTable('nextofkin', '#nextOfKinTableBody', 6, function(row) {
                var name = [row.first_name, row.second_name, row.last_name].filter(Boolean).join(' ');
                return '<tr>' +
                    '<td>' + (name || '-') + '</td>' +
                    '<td>' + (row.mobile || '-') + '</td>' +
                    '<td>' + (row.email || '-') + '</td>' +
                    '<td>' + (row.relation || '-') + '</td>' +
                    '<td>' + (row.national_id || '-') + '</td>' +
                    actionIcons('edit-nextofkin-row', row.encrypted_id, 'nextofkin', {
                        'first-name': row.first_name, 'second-name': row.second_name, 'last-name': row.last_name,
                        mobile: row.mobile, email: row.email, relation: row.relation, 'national-id': row.national_id
                    }) +
                    '</tr>';
            });
        }

        function reloadPartnersTable() {
            loadDraftTable('partner', '#partnersTableBody', 5, function(row) {
                return '<tr>' +
                    '<td>' + (row.name || '-') + '</td>' +
                    '<td>' + (row.national_id || '-') + '</td>' +
                    '<td>' + (row.kra_pin || '-') + '</td>' +
                    '<td>' + (row.phone || '-') + '</td>' +
                    actionIcons('edit-partner-row', row.encrypted_id, 'partner', {
                        name: row.name, 'national-id': row.national_id, 'kra-pin': row.kra_pin, phone: row.phone
                    }) +
                    '</tr>';
            });
        }

        var reloadFns = {
            banks: reloadBanksTable,
            contacts: reloadContactsTable,
            nextofkin: reloadNextOfKinTable,
            partner: reloadPartnersTable
        };

        $(document).on('click', '.remove-draft-row', function() {
            var id = $(this).data('id');
            var reload = reloadFns[$(this).data('reload')];
            removeDraftRow(id, reload);
        });

        // Clear every modal's edit-mode flag + fields whenever it closes (save,
        // cancel, or backdrop click) so the next "Add" click always starts fresh.
        var modalResets = {
            addBankModal: function() {
                $('#modal_bank_id, #modal_branch_id').val('');
                $('#modal_account_name, #modal_account_number').val('');
            },
            addContactModal: function() {
                $('#modal_contact_names, #modal_contact_email, #modal_contact_mobile').val('');
            },
            addNextOfKinModal: function() {
                $('#modal_nok_first_name, #modal_nok_second_name, #modal_nok_last_name, #modal_nok_mobile, #modal_nok_email, #modal_nok_national_id').val('');
                $('#modal_nok_relation').val('');
            },
            addPartnerModal: function() {
                $('#modal_partner_name, #modal_partner_national_id, #modal_partner_kra_pin, #modal_partner_phone').val('');
            }
        };
        Object.keys(modalResets).forEach(function(modalId) {
            document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
                $(this).removeData('editId');
                modalResets[modalId]();
            });
        });

        function saveDraftRow(payload, modalId, saveBtnId, reloadFn) {
            var $btn = $('#' + saveBtnId);
            var $spinner = $btn.find('.spinner-border');
            var editId = $('#' + modalId).data('editId');
            $spinner.removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: editId ? (draftRepeaterUrl() + '/' + editId) : draftRepeaterUrl(),
                type: editId ? 'PUT' : 'POST',
                data: Object.assign({ _token: '{{ csrf_token() }}', draft_token: draftToken }, payload),
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Saved successfully');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).hide();
                        reloadFn();
                    } else {
                        toastr.error(res.message || 'Failed to save entry.');
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save entry.';
                    toastr.error(msg);
                },
                complete: function() {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                }
            });
        }

        $('#saveBankBtn').on('click', function() {
            saveDraftRow({
                kind: 'bank',
                bank_id: $('#modal_bank_id').val(),
                branch_id: $('#modal_branch_id').val(),
                account_name: $('#modal_account_name').val(),
                account_number: $('#modal_account_number').val()
            }, 'addBankModal', 'saveBankBtn', reloadBanksTable);
        });

        $('#saveContactBtn').on('click', function() {
            saveDraftRow({
                kind: 'contact',
                names: $('#modal_contact_names').val(),
                email: $('#modal_contact_email').val(),
                mobile: $('#modal_contact_mobile').val()
            }, 'addContactModal', 'saveContactBtn', reloadContactsTable);
        });

        $('#saveNextOfKinBtn').on('click', function() {
            saveDraftRow({
                kind: 'nextofkin',
                first_name: $('#modal_nok_first_name').val(),
                second_name: $('#modal_nok_second_name').val(),
                last_name: $('#modal_nok_last_name').val(),
                mobile: $('#modal_nok_mobile').val(),
                email: $('#modal_nok_email').val(),
                relation: $('#modal_nok_relation').val(),
                national_id: $('#modal_nok_national_id').val()
            }, 'addNextOfKinModal', 'saveNextOfKinBtn', reloadNextOfKinTable);
        });

        $('#savePartnerBtn').on('click', function() {
            saveDraftRow({
                kind: 'partner',
                name: $('#modal_partner_name').val(),
                national_id: $('#modal_partner_national_id').val(),
                kra_pin: $('#modal_partner_kra_pin').val(),
                phone: $('#modal_partner_phone').val()
            }, 'addPartnerModal', 'savePartnerBtn', reloadPartnersTable);
        });

        // Edit icon: prefill the Add modal with this row's current values and
        // switch it into edit mode (the modal's editId data flag) before opening.
        $(document).on('click', '.edit-bank-row', function() {
            var $el = $(this);
            $('#modal_bank_id').val($el.data('bankId')).trigger('change');
            $('#modal_branch_id').val($el.data('branchId'));
            $('#modal_account_name').val($el.data('accountName'));
            $('#modal_account_number').val($el.data('accountNumber'));
            $('#addBankModal').data('editId', $el.data('id'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addBankModal')).show();
        });

        $(document).on('click', '.edit-contact-row', function() {
            var $el = $(this);
            $('#modal_contact_names').val($el.data('names'));
            $('#modal_contact_email').val($el.data('email'));
            $('#modal_contact_mobile').val($el.data('mobile'));
            $('#addContactModal').data('editId', $el.data('id'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addContactModal')).show();
        });

        $(document).on('click', '.edit-nextofkin-row', function() {
            var $el = $(this);
            $('#modal_nok_first_name').val($el.data('firstName'));
            $('#modal_nok_second_name').val($el.data('secondName'));
            $('#modal_nok_last_name').val($el.data('lastName'));
            $('#modal_nok_mobile').val($el.data('mobile'));
            $('#modal_nok_email').val($el.data('email'));
            $('#modal_nok_relation').val($el.data('relation'));
            $('#modal_nok_national_id').val($el.data('nationalId'));
            $('#addNextOfKinModal').data('editId', $el.data('id'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addNextOfKinModal')).show();
        });

        $(document).on('click', '.edit-partner-row', function() {
            var $el = $(this);
            $('#modal_partner_name').val($el.data('name'));
            $('#modal_partner_national_id').val($el.data('nationalId'));
            $('#modal_partner_kra_pin').val($el.data('kraPin'));
            $('#modal_partner_phone').val($el.data('phone'));
            $('#addPartnerModal').data('editId', $el.data('id'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addPartnerModal')).show();
        });

        // Populate the modal's Bank/Branch selects once bank data has loaded
        $(document).on('change', '#modal_bank_id', function() {
            var bankId = $(this).val();
            var $branch = $('#modal_branch_id');
            $branch.html('<option value="">Please select</option>');
            if (bankId) {
                branchesData.filter(function(b) { return b.bank_id == bankId; }).forEach(function(b) {
                    $branch.append('<option value="' + b.id + '">' + b.branch_name + '</option>');
                });
            }
        });

        reloadBanksTable();
        reloadContactsTable();
        reloadNextOfKinTable();
        reloadPartnersTable();

        // If there are validation errors, find which tab has errors and activate it
        var firstErrorField = $('.is-invalid').first();
        if (firstErrorField.length) {
            var tabPane = firstErrorField.closest('.tab-pane');
            if (tabPane.length) {
                var tabId = tabPane.attr('id');
                var tabButton = document.getElementById(tabId + '-tab');
                if (tabButton) {
                    var tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }
        }
    });
</script>
@endsection
