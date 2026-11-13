<?php

namespace Tests\Unit;

use App\Rules\CompanyEmail;
use App\Rules\StaffMobile;
use App\Support\StaffContact;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffContactTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'local with spaces' => ['0712 345 678', '+254712345678'],
            'local' => ['0712345678', '+254712345678'],
            'local 01 prefix' => ['0112345678', '+254112345678'],
            'country code without plus' => ['254712345678', '+254712345678'],
            'country code with spaces' => ['+254 712 345 678', '+254712345678'],
            'country code with dashes' => ['+254-712-345-678', '+254712345678'],
            'brackets and dots' => ['(0712) 345.678', '+254712345678'],
            'a zero left in after the country code' => ['+2540712345678', '+254712345678'],
            'a zero left in without the plus' => ['2540712345678', '+254712345678'],
            'international 00 prefix' => ['00254712345678', '+254712345678'],
            'without the leading zero' => ['712345678', '+254712345678'],
            'another country is kept' => ['+256 772 123456', '+256772123456'],
            'too short stays as typed' => ['0712', '0712'],
            'letters stay as typed' => ['abc', 'abc'],
            'not a mobile prefix stays as typed' => ['0812345678', '0812345678'],
            'blank' => ['', null],
            'only spaces' => ['   ', null],
            'nothing' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_a_mobile_number_is_written_with_its_country_code(?string $typed, ?string $expected): void
    {
        $this->assertSame($expected, StaffContact::mobile($typed));
    }

    public static function mobileChecks(): array
    {
        return [
            'kenyan 07' => ['+254712345678', true],
            'kenyan 01' => ['+254112345678', true],
            'another country' => ['+256772123456', true],
            'kenyan but not a mobile prefix' => ['+254812345678', false],
            'too short' => ['+25471234567', false],
            'too long' => ['+2547123456789', false],
            'no country code' => ['0712345678', false],
            'no plus' => ['254712345678', false],
            'a short international number' => ['+12345', false],
            'letters' => ['abc', false],
            'blank' => ['', false],
        ];
    }

    #[DataProvider('mobileChecks')]
    public function test_only_a_number_with_its_country_code_passes(string $number, bool $valid): void
    {
        $this->assertSame($valid, StaffContact::isMobile($number));
    }

    public static function names(): array
    {
        return [
            'three names' => ['LUCY W KARANJA', 'lucy.karanja@chabrinagencies.co.ke'],
            'two names' => ['Lucy Karanja', 'lucy.karanja@chabrinagencies.co.ke'],
            'extra spaces' => ['  JOHN   DOE  ', 'john.doe@chabrinagencies.co.ke'],
            'a double-barrelled name' => ["MARY-ANN O'BRIEN", 'mary-ann.obrien@chabrinagencies.co.ke'],
            'accents' => ['José Müller', 'jose.muller@chabrinagencies.co.ke'],
            'initials and dots' => ['J. K. Mwangi', 'j.mwangi@chabrinagencies.co.ke'],
            'one name only' => ['Wanjiru', null],
            'blank' => ['', null],
            'nothing' => [null, null],
        ];
    }

    #[DataProvider('names')]
    public function test_the_email_is_suggested_as_firstname_dot_surname(?string $names, ?string $expected): void
    {
        $this->assertSame($expected, StaffContact::emailFor($names));
    }

    public static function emails(): array
    {
        return [
            'the company format' => ['lucy.karanja@chabrinagencies.co.ke', true],
            'in capitals' => ['Lucy.Karanja@ChabrinAgencies.co.ke', true],
            'with spaces around it' => [' lucy.karanja@chabrinagencies.co.ke ', true],
            'a double-barrelled name' => ['mary-ann.kamau@chabrinagencies.co.ke', true],
            'a number for two people with one name' => ['john.kamau2@chabrinagencies.co.ke', true],
            'no surname' => ['lucy@chabrinagencies.co.ke', false],
            'another domain' => ['lucy.karanja@gmail.com', false],
            'a lookalike domain' => ['lucy.karanja@chabrinagencies.co.ke.evil.com', false],
            'a lookalike start' => ['lucy.karanja@notchabrinagencies.co.ke', false],
            'a middle initial for two people with one name' => ['mary.a.wanjiku@chabrinagencies.co.ke', true],
            'a middle initial in capitals' => ['Mary.A.Wanjiku@chabrinagencies.co.ke', true],
            'a whole middle name' => ['lucy.wambui.karanja@chabrinagencies.co.ke', false],
            'two middle initials' => ['lucy.w.k.karanja@chabrinagencies.co.ke', false],
            'a middle initial with no surname' => ['lucy.w.@chabrinagencies.co.ke', false],
            'a digit as the middle initial' => ['lucy.1.karanja@chabrinagencies.co.ke', false],
            'too many digits' => ['john.kamau123@chabrinagencies.co.ke', false],
            'digits in the first name' => ['j0hn.kamau@chabrinagencies.co.ke', false],
            'nothing before the dot' => ['.kamau@chabrinagencies.co.ke', false],
            'a single letter name' => ['x@chabrinagencies.co.ke', false],
            'blank' => ['', false],
        ];
    }

    #[DataProvider('emails')]
    public function test_only_firstname_dot_surname_at_the_company_domain_passes(string $email, bool $valid): void
    {
        $this->assertSame($valid, StaffContact::isCompanyEmail($email));
    }

    public static function initialEmails(): array
    {
        return [
            'one middle name' => ['Mary Ann Wanjiku', 'mary.a.wanjiku@chabrinagencies.co.ke'],
            'in capitals' => ['MARY ANN WANJIKU', 'mary.a.wanjiku@chabrinagencies.co.ke'],
            'the first middle name is used' => ['Mary Ann Grace Wanjiku', 'mary.a.wanjiku@chabrinagencies.co.ke'],
            'an accented middle name' => ['Mary Élise Wanjiku', 'mary.e.wanjiku@chabrinagencies.co.ke'],
            'no middle name' => ['Mary Wanjiku', null],
            'one name' => ['Mary', null],
            'nothing' => ['', null],
            'extra spaces' => ['  Mary   Ann   Wanjiku  ', 'mary.a.wanjiku@chabrinagencies.co.ke'],
        ];
    }

    #[DataProvider('initialEmails')]
    public function test_the_middle_initial_address_uses_the_first_middle_name(string $names, ?string $expected): void
    {
        $this->assertSame($expected, StaffContact::emailWithInitial($names));
        if ($expected !== null) {
            $this->assertTrue(StaffContact::isCompanyEmail($expected), 'and the format check accepts it');
        }
    }

    public function test_the_numbered_address_goes_on_the_surname(): void
    {
        $this->assertSame('mary.wanjiku2@chabrinagencies.co.ke', StaffContact::emailWithNumber('Mary Ann Wanjiku', 2));
        $this->assertSame('mary.wanjiku12@chabrinagencies.co.ke', StaffContact::emailWithNumber('Mary Wanjiku', 12));
        $this->assertNull(StaffContact::emailWithNumber('Mary', 2));
        $this->assertTrue(StaffContact::isCompanyEmail('mary.wanjiku99@chabrinagencies.co.ke'));
    }

    public function test_the_domain_and_country_code_come_from_the_settings(): void
    {
        config(['staff.email_domain' => 'example.co.ke', 'staff.country_code' => '256']);

        $this->assertSame('lucy.karanja@example.co.ke', StaffContact::emailFor('Lucy Karanja'));
        $this->assertTrue(StaffContact::isCompanyEmail('lucy.karanja@example.co.ke'));
        $this->assertFalse(StaffContact::isCompanyEmail('lucy.karanja@chabrinagencies.co.ke'));
        $this->assertSame('firstname.surname@example.co.ke', StaffContact::emailExample());
        $this->assertSame('+256772123456', StaffContact::mobile('0772 123 456'));
        $this->assertTrue(StaffContact::isMobile('+256772123456'));
    }

    public function test_the_mobile_rule_explains_what_to_enter(): void
    {
        $validator = Validator::make(['mobile_number' => '12345'], ['mobile_number' => [new StaffMobile()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('Enter the mobile number with its country code, like +254 7XX XXX XXX.', $validator->errors()->first('mobile_number'));
        $this->assertFalse(Validator::make(['mobile_number' => '+254712345678'], ['mobile_number' => [new StaffMobile()]])->fails());
    }

    public function test_the_company_email_rule_shows_the_format(): void
    {
        $validator = Validator::make(['email' => 'lucy@gmail.com'], ['email' => [new CompanyEmail()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('Use the company email format: firstname.surname@chabrinagencies.co.ke.', $validator->errors()->first('email'));
        $this->assertFalse(Validator::make(['email' => 'lucy.karanja@chabrinagencies.co.ke'], ['email' => [new CompanyEmail()]])->fails());
    }

    public function test_a_blank_value_is_left_to_the_required_rules(): void
    {
        $rules = ['mobile_number' => ['nullable', new StaffMobile()], 'email' => ['nullable', new CompanyEmail()]];

        $this->assertFalse(Validator::make(['mobile_number' => null, 'email' => null], $rules)->fails());
    }
}
