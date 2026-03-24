<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\InputValidator;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for InputValidator::validateLength() with multi-byte
 * UTF-8 strings (emoji, CJK characters) and control characters.
 *
 * The implementation uses mb_strlen(), so each multi-byte character should
 * count as 1 character regardless of byte length.
 */
class InputValidatorEdgeCaseTest extends TestCase
{
    // ---------------------------------------------------------------
    // Emoji strings (4 bytes per character in UTF-8)
    // ---------------------------------------------------------------

    public function testEmojiStringAtExactLimit(): void
    {
        // 5 emoji characters, each 4 bytes in UTF-8 but mb_strlen should count 5
        $value = str_repeat("\u{1F3A8}", 5); // 5 palette emojis
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNull($error, 'Emoji string at exact limit should be accepted');
    }

    public function testEmojiStringOverLimit(): void
    {
        $value = str_repeat("\u{1F3A8}", 6); // 6 palette emojis
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNotNull($error, 'Emoji string over limit should be rejected');
        $this->assertStringContainsString('5', $error);
    }

    public function testEmojiStringUnderLimit(): void
    {
        $value = str_repeat("\u{1F3A8}", 3); // 3 palette emojis
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNull($error, 'Emoji string under limit should be accepted');
    }

    // ---------------------------------------------------------------
    // CJK characters (3 bytes per character in UTF-8)
    // ---------------------------------------------------------------

    public function testCjkStringAtExactLimit(): void
    {
        // Chinese characters, each 3 bytes in UTF-8
        $value = str_repeat("\u{7F8E}", 10); // 10 copies of character for "beauty"
        $error = InputValidator::validateLength($value, 10, 'Title');
        $this->assertNull($error, 'CJK string at exact limit should be accepted');
    }

    public function testCjkStringOverLimit(): void
    {
        $value = str_repeat("\u{7F8E}", 11);
        $error = InputValidator::validateLength($value, 10, 'Title');
        $this->assertNotNull($error, 'CJK string over limit should be rejected');
    }

    public function testMixedAsciiAndCjk(): void
    {
        // "Art" (3 chars) + 7 CJK characters = 10 characters total
        $value = 'Art' . str_repeat("\u{7F8E}", 7);
        $error = InputValidator::validateLength($value, 10, 'Title');
        $this->assertNull($error, 'Mixed ASCII+CJK at exact limit should be accepted');
    }

    public function testMixedAsciiAndCjkOverLimit(): void
    {
        // "Art" (3 chars) + 8 CJK characters = 11 characters total
        $value = 'Art' . str_repeat("\u{7F8E}", 8);
        $error = InputValidator::validateLength($value, 10, 'Title');
        $this->assertNotNull($error, 'Mixed ASCII+CJK over limit should be rejected');
    }

    // ---------------------------------------------------------------
    // Control characters (single byte, but should still be counted)
    // ---------------------------------------------------------------

    public function testControlCharactersCountAsCharacters(): void
    {
        // 5 tab characters
        $value = str_repeat("\t", 5);
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNull($error, 'Control characters at exact limit should be accepted');
    }

    public function testControlCharactersOverLimit(): void
    {
        $value = str_repeat("\t", 6);
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNotNull($error, 'Control characters over limit should be rejected');
    }

    public function testNullBytesCountAsCharacters(): void
    {
        $value = str_repeat("\0", 5);
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNull($error, 'Null bytes at exact limit should be accepted');
    }

    public function testNewlinesCountAsCharacters(): void
    {
        $value = str_repeat("\n", 5);
        $error = InputValidator::validateLength($value, 5, 'Field');
        $this->assertNull($error, 'Newlines at exact limit should be accepted');
    }

    public function testMixedEmojiCjkAndAscii(): void
    {
        // 1 emoji + 1 CJK + 3 ASCII = 5 characters
        $value = "\u{1F3A8}\u{7F8E}Art";
        $this->assertSame(5, mb_strlen($value));
        $error = InputValidator::validateLength($value, 5, 'Title');
        $this->assertNull($error, 'Mixed multi-byte string at exact limit should be accepted');
    }

    public function testMixedEmojiCjkAndAsciiOverLimit(): void
    {
        // 1 emoji + 1 CJK + 4 ASCII = 6 characters
        $value = "\u{1F3A8}\u{7F8E}Arts";
        $this->assertSame(6, mb_strlen($value));
        $error = InputValidator::validateLength($value, 5, 'Title');
        $this->assertNotNull($error, 'Mixed multi-byte string over limit should be rejected');
    }
}
