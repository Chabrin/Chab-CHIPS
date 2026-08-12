@extends('layouts.main')
@section('content')
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
                                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">folder</span>
                                        Files & Documents
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="nextofkin-tab" data-bs-toggle="tab" data-bs-target="#nextofkin" type="button" role="tab" aria-controls="nextofkin" aria-selected="false">
                                        <span class="material-symbols-outlined me-1" style="font-size:18px;">family_restroom</span>
                                        Next of Kin
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
                                                <option value="">Select Client Type</option>
                                                <option value="1" {{ old('client_type_id') == '1' ? 'selected' : '' }}>Individual</option>
                                                <option value="2" {{ old('client_type_id') == '2' ? 'selected' : '' }}>SME</option>
                                                <option value="3" {{ old('client_type_id') == '3' ? 'selected' : '' }}>Corporate</option>
                                            </select>
                                            @error('client_type_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <!-- Personal Information Section -->
                                        <div class="col-12 mb-3 mt-3">
                                            <h6 class="text-muted border-bottom pb-2">Personal Information</h6>
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                            <select class="form-select @error('title') is-invalid @enderror" id="title" name="title" required>
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
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="Enter first name" required>
                                            @error('first_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control @error('middle_name') is-invalid @enderror" id="middle_name" name="middle_name" value="{{ old('middle_name') }}" placeholder="Enter middle name">
                                            @error('middle_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="Enter last name" required>
                                            @error('last_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                            @error('gender')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
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
                                
                                        <div class="col-md-3 mb-3">
                                            <label for="national_id" class="form-label">National ID</label>
                                            <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id') }}" placeholder="Enter national ID number">
                                            @error('national_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                
                                        <div class="col-md-3 mb-3">
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
                                
                                        <div class="col-md-4 mb-3">
                                            <label for="overdraft_penalty" class="form-label">Overdraft Penalty (%)</label>
                                            <input type="number" step="0.01" class="form-control @error('overdraft_penalty') is-invalid @enderror" id="overdraft_penalty" name="overdraft_penalty" value="{{ old('overdraft_penalty') }}" placeholder="0.00">
                                            @error('overdraft_penalty')
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
                                    <div id="bankAccountsContainer">
                                        <div class="bank-entry mb-4 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="bank_id_0" class="form-label">Bank</label>
                                                    <select class="form-select bank-select @error('banks.0.bank_id') is-invalid @enderror" id="bank_id_0" name="banks[0][bank_id]" data-index="0">
                                                        <option value="">Please select</option>
                                                        <!-- Banks will be populated via AJAX -->
                                                    </select>
                                                    @error('banks.0.bank_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="branch_id_0" class="form-label">Branch</label>
                                                    <select class="form-select branch-select @error('banks.0.branch_id') is-invalid @enderror" id="branch_id_0" name="banks[0][branch_id]" data-index="0">
                                                        <option value="">Please select</option>
                                                        <!-- Branches will be populated based on bank selection -->
                                                    </select>
                                                    @error('banks.0.branch_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-5 mb-3">
                                                    <label for="account_name_0" class="form-label">Account Name</label>
                                                    <input type="text" class="form-control @error('banks.0.account_name') is-invalid @enderror" id="account_name_0" name="banks[0][account_name]" value="{{ old('banks.0.account_name') }}" placeholder="Enter account name">
                                                    @error('banks.0.account_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-5 mb-3">
                                                    <label for="account_number_0" class="form-label">Account Number</label>
                                                    <input type="text" class="form-control @error('banks.0.account_number') is-invalid @enderror" id="account_number_0" name="banks[0][account_number]" value="{{ old('banks.0.account_number') }}" placeholder="Enter account number">
                                                    @error('banks.0.account_number')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-md-5 mb-3">
                                                    <label for="return_percentage_0" class="form-label">Return Percentage</label>
                                                    <input type="text" class="form-control @error('banks.0.return_percentage') is-invalid @enderror" id="return_percentage_0" name="banks[0][return_percentage]" value="{{ old('banks.0.return_percentage') }}" placeholder="e.g. 10">
                                                    @error('banks.0.return_percentage')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-bank" style="display: none;">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addBank">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        Add Another Bank Account
                                    </button>
                                </div>
                                
                                <!-- Other Contacts Tab -->
                                <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                                    <div id="contactsContainer">
                                        <div class="contact-entry mb-4 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="contact_names_0" class="form-label">Names</label>
                                                    <input type="text" class="form-control" id="contact_names_0" name="contacts[0][names]" value="{{ old('contacts.0.names') }}" placeholder="Enter contact name">
                                                </div>
                                
                                                <div class="col-md-3 mb-3">
                                                    <label for="contact_email_0" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="contact_email_0" name="contacts[0][email]" value="{{ old('contacts.0.email') }}" placeholder="name@example.com">
                                                </div>
                                
                                                <div class="col-md-3 mb-3">
                                                    <label for="contact_mobile_0" class="form-label">Mobile Number</label>
                                                    <input type="text" class="form-control" id="contact_mobile_0" name="contacts[0][mobile]" value="{{ old('contacts.0.mobile') }}" placeholder="254XXXXXXXXX">
                                                </div>
                                
                                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-contact" style="display: none;">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addContact">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        Add Another Contact
                                    </button>
                                </div>
                                
                                <!-- Files & Documents Tab -->
                                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                                    <div id="documentsContainer">
                                        <div class="document-entry mb-4 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="file_type_0" class="form-label">File Type</label>
                                                    <select class="form-select" id="file_type_0" name="documents[0][file_type]">
                                                        <option value="">Please select</option>
                                                        <option value="National ID">National ID</option>
                                                        <option value="Passport">Passport</option>
                                                        <option value="PIN Certificate">PIN Certificate</option>
                                                        <option value="Contract">Contract</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </div>
                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="document_file_0" class="form-label">Documents</label>
                                                    <input type="file" class="form-control" id="document_file_0" name="documents[0][file]">
                                                </div>
                                
                                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-document" style="display: none;">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addDocument">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        Add Another Document
                                    </button>
                                </div>
                                
                                <!-- Next of Kin Tab -->
                                <div class="tab-pane fade" id="nextofkin" role="tabpanel" aria-labelledby="nextofkin-tab">
                                    <div id="nextOfKinContainer">
                                        <div class="nok-entry mb-4 p-3 border rounded">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="nok_first_name_0" class="form-label">First Name</label>
                                                    <input type="text" class="form-control @error('next_of_kin.0.first_name') is-invalid @enderror" id="nok_first_name_0" name="next_of_kin[0][first_name]" value="{{ old('next_of_kin.0.first_name') }}" placeholder="Enter first name">
                                                    @error('next_of_kin.0.first_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="nok_second_name_0" class="form-label">Second Name</label>
                                                    <input type="text" class="form-control @error('next_of_kin.0.second_name') is-invalid @enderror" id="nok_second_name_0" name="next_of_kin[0][second_name]" value="{{ old('next_of_kin.0.second_name') }}" placeholder="Enter second name">
                                                    @error('next_of_kin.0.second_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="nok_last_name_0" class="form-label">Last Name</label>
                                                    <input type="text" class="form-control @error('next_of_kin.0.last_name') is-invalid @enderror" id="nok_last_name_0" name="next_of_kin[0][last_name]" value="{{ old('next_of_kin.0.last_name') }}" placeholder="Enter last name">
                                                    @error('next_of_kin.0.last_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="nok_mobile_0" class="form-label">Mobile Number</label>
                                                    <input type="text" class="form-control @error('next_of_kin.0.mobile') is-invalid @enderror" id="nok_mobile_0" name="next_of_kin[0][mobile]" value="{{ old('next_of_kin.0.mobile') }}" placeholder="254XXXXXXXXX">
                                                    @error('next_of_kin.0.mobile')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="nok_email_0" class="form-label">Email Address</label>
                                                    <input type="email" class="form-control @error('next_of_kin.0.email') is-invalid @enderror" id="nok_email_0" name="next_of_kin[0][email]" value="{{ old('next_of_kin.0.email') }}" placeholder="name@example.com">
                                                    @error('next_of_kin.0.email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="nok_relation_0" class="form-label">Relation</label>
                                                    <select class="form-select @error('next_of_kin.0.relation') is-invalid @enderror" id="nok_relation_0" name="next_of_kin[0][relation]">
                                                        <option value="">Please select</option>
                                                        <option value="Spouse" {{ old('next_of_kin.0.relation') == 'Spouse' ? 'selected' : '' }}>Spouse</option>
                                                        <option value="Parent" {{ old('next_of_kin.0.relation') == 'Parent' ? 'selected' : '' }}>Parent</option>
                                                        <option value="Sibling" {{ old('next_of_kin.0.relation') == 'Sibling' ? 'selected' : '' }}>Sibling</option>
                                                        <option value="Child" {{ old('next_of_kin.0.relation') == 'Child' ? 'selected' : '' }}>Child</option>
                                                        <option value="Other" {{ old('next_of_kin.0.relation') == 'Other' ? 'selected' : '' }}>Other</option>
                                                    </select>
                                                    @error('next_of_kin.0.relation')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-10 mb-3">
                                                    <label for="nok_national_id_0" class="form-label">National ID</label>
                                                    <input type="text" class="form-control @error('next_of_kin.0.national_id') is-invalid @enderror" id="nok_national_id_0" name="next_of_kin[0][national_id]" value="{{ old('next_of_kin.0.national_id') }}" placeholder="Enter national ID number">
                                                    @error('next_of_kin.0.national_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                
                                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-outline-primary btn-sm remove-nok" style="display: none;">
                                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addNextOfKin">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                                        Add Another Next of Kin
                                    </button>
                                </div>
                            </div>
                        </form>
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
        }

        // Handle bank change to load branches (delegated event)
        $(document).on('change', '.bank-select', function() {
            var bankId = $(this).val();
            var index = $(this).data('index');
            var branchSelect = $('#branch_id_' + index);

            branchSelect.html('<option value="">Please select</option>');

            if (bankId) {
                // Filter branches by selected bank
                var filteredBranches = branchesData.filter(function(branch) {
                    return branch.bank_id == bankId;
                });

                if (filteredBranches.length > 0) {
                    filteredBranches.forEach(function(branch) {
                        branchSelect.append(`<option value="${branch.id}">${branch.branch_name}</option>`);
                    });
                } else {
                    branchSelect.append('<option value="">No branches available</option>');
                }
            }
        });

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

        // Add Bank Account functionality
        let bankCounter = 1;
        $('#addBank').on('click', function() {
            const bankEntry = `
                <div class="bank-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bank_id_${bankCounter}" class="form-label">Bank</label>
                            <select class="form-select bank-select" id="bank_id_${bankCounter}" name="banks[${bankCounter}][bank_id]" data-index="${bankCounter}">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="branch_id_${bankCounter}" class="form-label">Branch</label>
                            <select class="form-select branch-select" id="branch_id_${bankCounter}" name="banks[${bankCounter}][branch_id]" data-index="${bankCounter}">
                                <option value="">Please select</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="account_name_${bankCounter}" class="form-label">Account Name</label>
                            <input type="text" class="form-control" id="account_name_${bankCounter}" name="banks[${bankCounter}][account_name]" placeholder="Enter account name">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="account_number_${bankCounter}" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number_${bankCounter}" name="banks[${bankCounter}][account_number]" placeholder="Enter account number">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="return_percentage_${bankCounter}" class="form-label">Return Percentage</label>
                            <input type="text" class="form-control" id="return_percentage_${bankCounter}" name="banks[${bankCounter}][return_percentage]" placeholder="e.g. 10">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-bank">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#bankAccountsContainer').append(bankEntry);
            bankCounter++;

            // Populate banks for new entry
            populateBankDropdowns();

            // Show delete buttons for all entries if more than one
            if ($('.bank-entry').length > 1) {
                $('.remove-bank').show();
            }
        });

        // Remove Bank Account
        $(document).on('click', '.remove-bank', function() {
            $(this).closest('.bank-entry').remove();
            if ($('.bank-entry').length === 1) {
                $('.remove-bank').hide();
            }
        });

        // Add Contact functionality
        let contactCounter = 1;
        $('#addContact').on('click', function() {
            const contactEntry = `
                <div class="contact-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="contact_names_${contactCounter}" class="form-label">Names</label>
                            <input type="text" class="form-control" id="contact_names_${contactCounter}" name="contacts[${contactCounter}][names]" placeholder="Enter contact name">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="contact_email_${contactCounter}" class="form-label">Email</label>
                            <input type="email" class="form-control" id="contact_email_${contactCounter}" name="contacts[${contactCounter}][email]" placeholder="name@example.com">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="contact_mobile_${contactCounter}" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="contact_mobile_${contactCounter}" name="contacts[${contactCounter}][mobile]" placeholder="254XXXXXXXXX">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-contact">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#contactsContainer').append(contactEntry);
            contactCounter++;

            if ($('.contact-entry').length > 1) {
                $('.remove-contact').show();
            }
        });

        // Remove Contact
        $(document).on('click', '.remove-contact', function() {
            $(this).closest('.contact-entry').remove();
            if ($('.contact-entry').length === 1) {
                $('.remove-contact').hide();
            }
        });

        // Add Document functionality
        let documentCounter = 1;
        $('#addDocument').on('click', function() {
            const documentEntry = `
                <div class="document-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="file_type_${documentCounter}" class="form-label">File Type</label>
                            <select class="form-select" id="file_type_${documentCounter}" name="documents[${documentCounter}][file_type]">
                                <option value="">Please select</option>
                                <option value="National ID">National ID</option>
                                <option value="Passport">Passport</option>
                                <option value="PIN Certificate">PIN Certificate</option>
                                <option value="Contract">Contract</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="document_file_${documentCounter}" class="form-label">Documents</label>
                            <input type="file" class="form-control" id="document_file_${documentCounter}" name="documents[${documentCounter}][file]">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-document">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#documentsContainer').append(documentEntry);
            documentCounter++;

            if ($('.document-entry').length > 1) {
                $('.remove-document').show();
            }
        });

        // Remove Document
        $(document).on('click', '.remove-document', function() {
            $(this).closest('.document-entry').remove();
            if ($('.document-entry').length === 1) {
                $('.remove-document').hide();
            }
        });

        // Add Next of Kin functionality
        let nokCounter = 1;
        $('#addNextOfKin').on('click', function() {
            const nokEntry = `
                <div class="nok-entry mb-4 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nok_first_name_${nokCounter}" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="nok_first_name_${nokCounter}" name="next_of_kin[${nokCounter}][first_name]" placeholder="Enter first name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_second_name_${nokCounter}" class="form-label">Second Name</label>
                            <input type="text" class="form-control" id="nok_second_name_${nokCounter}" name="next_of_kin[${nokCounter}][second_name]" placeholder="Enter second name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_last_name_${nokCounter}" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="nok_last_name_${nokCounter}" name="next_of_kin[${nokCounter}][last_name]" placeholder="Enter last name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_mobile_${nokCounter}" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="nok_mobile_${nokCounter}" name="next_of_kin[${nokCounter}][mobile]" placeholder="254XXXXXXXXX">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_email_${nokCounter}" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="nok_email_${nokCounter}" name="next_of_kin[${nokCounter}][email]" placeholder="name@example.com">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nok_relation_${nokCounter}" class="form-label">Relation</label>
                            <select class="form-select" id="nok_relation_${nokCounter}" name="next_of_kin[${nokCounter}][relation]">
                                <option value="">Please select</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Parent">Parent</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Child">Child</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-10 mb-3">
                            <label for="nok_national_id_${nokCounter}" class="form-label">National ID</label>
                            <input type="text" class="form-control" id="nok_national_id_${nokCounter}" name="next_of_kin[${nokCounter}][national_id]" placeholder="Enter national ID number">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary btn-sm remove-nok">
                                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#nextOfKinContainer').append(nokEntry);
            nokCounter++;

            if ($('.nok-entry').length > 1) {
                $('.remove-nok').show();
            }
        });

        // Remove Next of Kin
        $(document).on('click', '.remove-nok', function() {
            $(this).closest('.nok-entry').remove();
            if ($('.nok-entry').length === 1) {
                $('.remove-nok').hide();
            }
        });

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
