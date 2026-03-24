<?php
declare(strict_types=1);

use Heirloom\InputValidator;
use PHPUnit\Framework\TestCase;

final class InputLengthLimitTest extends TestCase
{
    public function testConstantsExistWithExpectedValues(): void
    {
        $this->assertSame(500, InputValidator::MAX_SHIPPING_ADDRESS);
        $this->assertSame(1000, InputValidator::MAX_INTEREST_MESSAGE);
        $this->assertSame(255, InputValidator::MAX_PAINTING_TITLE);
        $this->assertSame(5000, InputValidator::MAX_PAINTING_DESCRIPTION);
    }

    public function testShippingAddressOver500CharsIsRejected(): void
    {
        $value = str_repeat('a', InputValidator::MAX_SHIPPING_ADDRESS + 1);
        $error = InputValidator::validateLength($value, InputValidator::MAX_SHIPPING_ADDRESS, 'Shipping address');
        $this->assertNotNull($error);
        $this->assertStringContainsString((string) InputValidator::MAX_SHIPPING_ADDRESS, $error);
        $this->assertStringContainsString('Shipping address', $error);
    }

    public function testShippingAddressAtExactly500CharsIsAccepted(): void
    {
        $value = str_repeat('a', InputValidator::MAX_SHIPPING_ADDRESS);
        $error = InputValidator::validateLength($value, InputValidator::MAX_SHIPPING_ADDRESS, 'Shipping address');
        $this->assertNull($error);
    }

    public function testInterestMessageOver1000CharsIsRejected(): void
    {
        $value = str_repeat('b', InputValidator::MAX_INTEREST_MESSAGE + 1);
        $error = InputValidator::validateLength($value, InputValidator::MAX_INTEREST_MESSAGE, 'Interest message');
        $this->assertNotNull($error);
        $this->assertStringContainsString((string) InputValidator::MAX_INTEREST_MESSAGE, $error);
    }

    public function testPaintingTitleOver255CharsIsRejected(): void
    {
        $value = str_repeat('c', InputValidator::MAX_PAINTING_TITLE + 1);
        $error = InputValidator::validateLength($value, InputValidator::MAX_PAINTING_TITLE, 'Title');
        $this->assertNotNull($error);
        $this->assertStringContainsString((string) InputValidator::MAX_PAINTING_TITLE, $error);
    }

    public function testPaintingDescriptionOver5000CharsIsRejected(): void
    {
        $value = str_repeat('d', InputValidator::MAX_PAINTING_DESCRIPTION + 1);
        $error = InputValidator::validateLength($value, InputValidator::MAX_PAINTING_DESCRIPTION, 'Description');
        $this->assertNotNull($error);
        $this->assertStringContainsString((string) InputValidator::MAX_PAINTING_DESCRIPTION, $error);
    }

    public function testEmptyStringIsAccepted(): void
    {
        $error = InputValidator::validateLength('', InputValidator::MAX_SHIPPING_ADDRESS, 'Shipping address');
        $this->assertNull($error);
    }

    public function testStringUnderLimitIsAccepted(): void
    {
        $error = InputValidator::validateLength('hello', InputValidator::MAX_SHIPPING_ADDRESS, 'Shipping address');
        $this->assertNull($error);
    }
}
