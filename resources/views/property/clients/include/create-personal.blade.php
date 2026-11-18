<!-- Personal Details Tab -->
<div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="registered_date" class="form-label">Registration Date</label>
            <input type="datetime-local" class="form-control @error('select_time') is-invalid @enderror" id="registered_date" name="select_time" value="{{ old('select_time', now()->format('Y-m-d\TH:i')) }}">
            @error('select_time')
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


        <div class="col-md-3 mb-3 individual-only" id="individualFields">
            <label for="title" class="form-label">Title <span class="client-optional">(optional)</span></label>
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
            <select class="form-select @error('nationality_id') is-invalid @enderror" id="nationality_id" name="nationality_id" data-initial-value="{{ old('nationality_id') }}">
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


        <div class="col-md-3 mb-3 individual-only" id="individualIdFields">
            <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id') }}" placeholder="Enter national ID number">
            @error('national_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-danger d-none duplicate-check-message" id="national_id_duplicate"></small>
        </div>

        <div class="col-md-3 mb-3 individual-only">
            <label for="passport_number" class="form-label">Passport Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('passport_number') is-invalid @enderror" id="passport_number" name="passport_number" value="{{ old('passport_number') }}" placeholder="Enter passport number">
            @error('passport_number')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted d-block">National ID or Passport Number is required (one is enough).</small>
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

        <div class="col-md-4 mb-3">
            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" id="mobile_number" name="mobile_number" value="{{ old('mobile_number') }}" placeholder="254XXXXXXXXX" maxlength="30" pattern="254[0-9]{9}" required>
            <small class="text-muted">Enter a number such as 0712 345 678 or +254 712 345 678. It will be saved as 254XXXXXXXXX.</small>
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

        <div class="col-md-12 mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Enter address">{{ old('address') }}</textarea>
            @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12 mt-3 partnership-only">
            @include('property.clients.include.create-repeater', ['kind' => 'partner'])
        </div>
    </div>
</div>
