<?php
declare(strict_types=1);

namespace Heirloom\Tests;

use Heirloom\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, value TEXT)');

        $this->db = new Database($pdo);
    }

    public function testExecuteAndFetchOneReturnsInsertedRow(): void
    {
        $this->db->execute(
            'INSERT INTO items (name, value) VALUES (:name, :value)',
            [':name' => 'alpha', ':value' => '100']
        );

        $row = $this->db->fetchOne('SELECT * FROM items WHERE name = :name', [':name' => 'alpha']);

        $this->assertNotNull($row);
        $this->assertSame('alpha', $row['name']);
        $this->assertSame('100', $row['value']);
    }

    public function testFetchOneReturnsNullWhenNoMatch(): void
    {
        $result = $this->db->fetchOne('SELECT * FROM items WHERE name = :name', [':name' => 'nope']);
        $this->assertNull($result);
    }

    public function testFetchAllReturnsMultipleRows(): void
    {
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'a', ':v' => '1']);
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'b', ':v' => '2']);
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'c', ':v' => '3']);

        $rows = $this->db->fetchAll('SELECT * FROM items ORDER BY name');

        $this->assertCount(3, $rows);
        $this->assertSame('a', $rows[0]['name']);
        $this->assertSame('c', $rows[2]['name']);
    }

    public function testFetchAllReturnsEmptyArrayWhenNoRows(): void
    {
        $rows = $this->db->fetchAll('SELECT * FROM items');
        $this->assertSame([], $rows);
    }

    public function testLastInsertIdReturnsIdOfLastInsert(): void
    {
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'first', ':v' => '1']);
        $id1 = $this->db->lastInsertId();

        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'second', ':v' => '2']);
        $id2 = $this->db->lastInsertId();

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    public function testScalarReturnsSingleValue(): void
    {
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'x', ':v' => 'y']);
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'a', ':v' => 'b']);

        $count = $this->db->scalar('SELECT COUNT(*) FROM items');
        $this->assertEquals(2, $count);
    }

    public function testScalarReturnsNullForNoRows(): void
    {
        $result = $this->db->scalar('SELECT name FROM items WHERE id = 999');
        $this->assertNull($result);
    }

    public function testQueryWithNoParamsWorks(): void
    {
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'z', ':v' => 'zz']);
        $rows = $this->db->fetchAll('SELECT * FROM items');
        $this->assertCount(1, $rows);
    }

    public function testNullValueHandled(): void
    {
        $this->db->execute('INSERT INTO items (name, value) VALUES (:n, :v)', [':n' => 'nil', ':v' => null]);
        $row = $this->db->fetchOne('SELECT * FROM items WHERE name = :n', [':n' => 'nil']);

        $this->assertNotNull($row);
        $this->assertNull($row['value']);
    }
}
