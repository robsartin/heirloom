<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Controllers\AdminController;
use Heirloom\Database;
use Heirloom\SiteSettings;
use Heirloom\Tests\Controllers\ControllerTestSchema;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CSV export queries and output used by AdminController.
 *
 * Validates that paintings and users CSV exports contain the expected
 * header rows and that data rows match database content.
 */
class CsvExportTest extends TestCase
{
    use ControllerTestSchema;

    protected function setUp(): void
    {
        $this->createDatabase();
    }

    // ---------------------------------------------------------------
    // Paintings CSV
    // ---------------------------------------------------------------

    public function testPaintingsCsvHeaderRow(): void
    {
        $header = AdminController::paintingsCsvHeader();

        $this->assertSame([
            'ID',
            'Title',
            'Description',
            'Filename',
            'Interest Count',
            'Awarded To Name',
            'Awarded To Email',
            'Awarded At',
            'Tracking Number',
            'Created At',
        ], $header);
    }

    public function testPaintingsCsvDataMatchesDb(): void
    {
        $alice = $this->insertUser('alice@test.com', 'Alice');
        $bob = $this->insertUser('bob@test.com', 'Bob');

        $p1 = $this->insertPainting('Sunset', $alice, 'A warm sunset');
        $this->db->execute(
            'UPDATE paintings SET tracking_number = :tn WHERE id = :id',
            [':tn' => 'TRACK123', ':id' => $p1]
        );

        $p2 = $this->insertPainting('Forest', null, 'Deep woods');

        $this->insertInterest($p1, $bob);
        $this->insertInterest($p2, $alice);
        $this->insertInterest($p2, $bob);

        $rows = AdminController::paintingsCsvRows($this->db);

        $this->assertCount(2, $rows);

        // Find the Sunset row
        $sunset = null;
        $forest = null;
        foreach ($rows as $row) {
            if ($row['title'] === 'Sunset') {
                $sunset = $row;
            }
            if ($row['title'] === 'Forest') {
                $forest = $row;
            }
        }

        $this->assertNotNull($sunset);
        $this->assertSame(1, (int) $sunset['interest_count']);
        $this->assertSame('Alice', $sunset['awarded_name']);
        $this->assertSame('alice@test.com', $sunset['awarded_email']);
        $this->assertSame('TRACK123', $sunset['tracking_number']);

        $this->assertNotNull($forest);
        $this->assertSame(2, (int) $forest['interest_count']);
        $this->assertNull($forest['awarded_name']);
        $this->assertNull($forest['awarded_email']);
    }

    // ---------------------------------------------------------------
    // Users CSV
    // ---------------------------------------------------------------

    public function testUsersCsvHeaderRow(): void
    {
        $header = AdminController::usersCsvHeader();

        $this->assertSame([
            'ID',
            'Email',
            'Name',
            'Shipping Address',
            'Interest Count',
            'Awarded Painting Count',
            'Is Admin',
            'Created At',
        ], $header);
    }

    public function testUsersCsvDataMatchesDb(): void
    {
        $alice = $this->insertUser('alice@test.com', 'Alice');
        $bob = $this->insertUser('bob@test.com', 'Bob');
        $this->db->execute(
            'UPDATE users SET shipping_address = :addr WHERE id = :id',
            [':addr' => '123 Main St', ':id' => $alice]
        );

        $p1 = $this->insertPainting('Sunset', $alice);
        $p2 = $this->insertPainting('Dawn', $alice);
        $p3 = $this->insertPainting('Forest');

        $this->insertInterest($p1, $bob);
        $this->insertInterest($p3, $bob);
        $this->insertInterest($p3, $alice);

        $rows = AdminController::usersCsvRows($this->db);

        $this->assertCount(2, $rows);

        $aliceRow = null;
        $bobRow = null;
        foreach ($rows as $row) {
            if ($row['email'] === 'alice@test.com') {
                $aliceRow = $row;
            }
            if ($row['email'] === 'bob@test.com') {
                $bobRow = $row;
            }
        }

        $this->assertNotNull($aliceRow);
        $this->assertSame('Alice', $aliceRow['name']);
        $this->assertSame('123 Main St', $aliceRow['shipping_address']);
        $this->assertSame(1, (int) $aliceRow['interest_count']);
        $this->assertSame(2, (int) $aliceRow['awarded_count']);

        $this->assertNotNull($bobRow);
        $this->assertSame(2, (int) $bobRow['interest_count']);
        $this->assertSame(0, (int) $bobRow['awarded_count']);
    }
}
