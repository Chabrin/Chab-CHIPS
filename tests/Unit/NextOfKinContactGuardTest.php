<?php

namespace Tests\Unit;

use App\Services\NextOfKinContactGuard;
use PHPUnit\Framework\TestCase;

class NextOfKinContactGuardTest extends TestCase
{
    private function guard(): NextOfKinContactGuard
    {
        return new NextOfKinContactGuard();
    }

    public function test_different_spellings_of_one_phone_number_match(): void
    {
        $g = $this->guard();

        $this->assertSame('712345678', $g->normalizePhone('254712345678'));
        $this->assertSame('712345678', $g->normalizePhone('0712 345 678'));
        $this->assertSame('712345678', $g->normalizePhone('+254-712-345-678'));
    }

    public function test_same_mobile_is_flagged_whatever_the_format(): void
    {
        $shared = $this->guard()->sharedWithClient(null, '254712345678', null, '0712345678');

        $this->assertTrue($shared['mobile']);
        $this->assertFalse($shared['email']);
    }

    public function test_same_email_is_flagged_ignoring_case_and_spaces(): void
    {
        $shared = $this->guard()->sharedWithClient('Jane@Example.com', null, ' jane@example.COM ', null);

        $this->assertTrue($shared['email']);
        $this->assertFalse($shared['mobile']);
    }

    public function test_different_details_are_not_flagged(): void
    {
        $shared = $this->guard()->sharedWithClient('a@example.com', '254712345678', 'b@example.com', '254722345678');

        $this->assertFalse($shared['email']);
        $this->assertFalse($shared['mobile']);
    }

    public function test_empty_values_never_count_as_the_same(): void
    {
        $g = $this->guard();

        $this->assertSame(['email' => false, 'mobile' => false], $g->sharedWithClient(null, null, null, null));
        $this->assertSame(['email' => false, 'mobile' => false], $g->sharedWithClient('', ' ', '', ''));
        $this->assertSame(['email' => false, 'mobile' => false], $g->sharedWithClient('a@example.com', '254712345678', null, null));
    }

    public function test_kin_errors_name_the_offending_fields(): void
    {
        $errors = $this->guard()->kinErrors('a@example.com', '254712345678', 'A@example.com', '0712345678');

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('mobile', $errors);
        $this->assertSame([], $this->guard()->kinErrors('a@example.com', '254712345678', 'b@example.com', '254722345678'));
    }
}
