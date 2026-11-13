<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Rules\CompanyEmail;
use App\Rules\StaffMobile;
use App\Services\StaffContactInput;
use Illuminate\Http\Request;
use Tests\TestCase;

class StaffContactInputTest extends TestCase
{
    private function request(array $input): Request
    {
        return Request::create('/', 'POST', $input);
    }

    /** A staff record as it is stored, so the values count as "not changed" until the form says otherwise. */
    private function stored(array $attributes): Staff
    {
        $staff = new Staff();
        $staff->forceFill($attributes)->syncOriginal();

        return $staff;
    }

    private function prepare(Request $request, ?Staff $current = null): array
    {
        return (new StaffContactInput())->prepare($request, $current);
    }

    public function test_a_new_staff_member_gets_the_country_code_and_lower_case_emails(): void
    {
        $request = $this->request([
            'mobile_number' => '0712 345 678',
            'email_address' => ' Lucy.Karanja@ChabrinAgencies.co.ke ',
            'login_email' => 'LUCY.KARANJA@chabrinagencies.co.ke',
        ]);

        $rules = $this->prepare($request);

        $this->assertSame('+254712345678', $request->input('mobile_number'));
        $this->assertSame('lucy.karanja@chabrinagencies.co.ke', $request->input('email_address'));
        $this->assertSame('lucy.karanja@chabrinagencies.co.ke', $request->input('login_email'));
        $this->assertInstanceOf(StaffMobile::class, $rules['mobile_number'][0]);
        $this->assertInstanceOf(CompanyEmail::class, $rules['email_address'][0]);
        $this->assertInstanceOf(CompanyEmail::class, $rules['login_email'][0]);
    }

    public function test_fields_that_are_not_in_the_request_are_left_out(): void
    {
        $request = $this->request(['full_names' => 'Lucy Karanja']);

        $rules = $this->prepare($request);

        $this->assertSame([], $rules);
        $this->assertSame(['full_names'], array_keys($request->all()));
    }

    public function test_a_blank_mobile_number_becomes_null(): void
    {
        $request = $this->request(['mobile_number' => '  ', 'email_address' => '']);

        $this->prepare($request);

        $this->assertNull($request->input('mobile_number'));
        $this->assertNull($request->input('email_address'));
    }

    public function test_a_number_that_cannot_be_read_stays_as_typed_for_the_rule_to_refuse(): void
    {
        $request = $this->request(['mobile_number' => '12-34']);

        $this->prepare($request);

        $this->assertSame('1234', $request->input('mobile_number'));
    }

    public function test_when_editing_a_value_that_has_not_changed_is_left_exactly_as_it_is(): void
    {
        $current = $this->stored(['mobile_number' => '0722728512', 'email_address' => 'Old.Person@gmail.com']);
        $request = $this->request(['mobile_number' => '0722728512', 'email_address' => ' old.person@gmail.com ']);

        $rules = $this->prepare($request, $current);

        $this->assertSame([], $rules);
        $this->assertSame('0722728512', $request->input('mobile_number'));
        $this->assertSame(' old.person@gmail.com ', $request->input('email_address'));
    }

    public function test_when_editing_an_older_value_that_cannot_be_read_can_still_be_kept(): void
    {
        $current = $this->stored(['mobile_number' => 'N/A']);
        $request = $this->request(['mobile_number' => 'N/A']);

        $this->assertSame([], $this->prepare($request, $current));
    }

    public function test_when_editing_a_changed_mobile_number_is_tidied_and_checked(): void
    {
        $current = $this->stored(['mobile_number' => '0722728512']);
        $request = $this->request(['mobile_number' => '0733 111 222']);

        $rules = $this->prepare($request, $current);

        $this->assertSame('+254733111222', $request->input('mobile_number'));
        $this->assertInstanceOf(StaffMobile::class, $rules['mobile_number'][0]);
    }

    public function test_when_editing_a_changed_email_is_lower_cased_and_checked(): void
    {
        $current = $this->stored(['email_address' => 'old.person@gmail.com']);
        $request = $this->request(['email_address' => 'New.Person@Chabrinagencies.co.ke']);

        $rules = $this->prepare($request, $current);

        $this->assertSame('new.person@chabrinagencies.co.ke', $request->input('email_address'));
        $this->assertInstanceOf(CompanyEmail::class, $rules['email_address'][0]);
    }

    public function test_when_editing_the_old_value_in_another_case_counts_as_unchanged(): void
    {
        $current = $this->stored(['email_address' => 'Old.Person@Gmail.com']);

        $this->assertSame([], $this->prepare($this->request(['email_address' => 'old.person@gmail.com']), $current));
    }

    public function test_filling_in_a_mobile_number_for_the_first_time_is_checked(): void
    {
        $current = $this->stored(['mobile_number' => null]);

        $rules = $this->prepare($this->request(['mobile_number' => '0712345678']), $current);

        $this->assertInstanceOf(StaffMobile::class, $rules['mobile_number'][0]);
    }
}
