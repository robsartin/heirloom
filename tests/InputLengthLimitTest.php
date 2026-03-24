<?php
declare(strict_types=1);

use Heirloom\InputValidator;
use PHPUnit\Framework\TestCase;

final class InputLengthLimitTest extends TestCase
{
    public function testShippingAddressOver500CharsIsRejected(): void
    {
        $value = str_repeat('a', 501);
        $error = InputValidator::validateLength($value, 500, 'Shipping address');
        $this->assertNotNull($error);
        $this->assertStringContainsString('500', $error);
        $this->assertStringContainsString('Shipping address', $error);
    }

    public function testShippingAddressAtExactly500CharsIsAccepted(): void
    {
        $value = str_repeat('a', 500);
        $error = InputValidator::validateLength($value, 500, 'Shipping address');
        $this->assertNull($error);
    }

    public function testInterestMessageOver1000CharsIsRejected(): void
    {
        $value = str_repeat('b', 1001);
        $error = InputValidator::validateLength($value, 1000, 'Interest message');
        $this->assertNotNull($error);
        $this->assertStringContainsString('1000', $error);
    }

    public function testPaintingTitleOver255CharsIsRejected(): void
    {
        $value = str_repeat('c', 256);
        $error = InputValidator::validateLength($value, 255, 'Title');
        $this->assertNotNull($error);
        $this->assertStringContainsString('255', $error);
    }

    public function testPaintingDescriptionOver5000CharsIsRejected(): void
    {
        $value = str_repeat('d', 5001);
        $error = InputValidator::validateLength($value, 5000, 'Description');
        $this->assertNotNull($error);
        $this->assertStringContainsString('5000', $error);
    }

    public function testEmptyStringIsAccepted(): void
    {
        $error = InputValidator::validateLength('', 500, 'Shipping address');
        $this->assertNull($error);
    }

    public function testStringUnderLimitIsAccepted(): void
    {
        $error = InputValidator::validateLength('hello', 500, 'Shipping address');
        $this->assertNull($error);
    }
}
