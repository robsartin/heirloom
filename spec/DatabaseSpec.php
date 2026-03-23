<?php
declare(strict_types=1);

namespace spec\Heirloom;

use Heirloom\Database;
use PhpSpec\ObjectBehavior;
use PDO;

class DatabaseSpec extends ObjectBehavior
{
    function let(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE things (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');

        $this->beConstructedWith($pdo);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(Database::class);
    }

    function it_inserts_and_fetches_a_row(): void
    {
        $this->execute('INSERT INTO things (label) VALUES (:l)', [':l' => 'alpha']);
        $this->fetchOne('SELECT * FROM things WHERE label = :l', [':l' => 'alpha'])
            ->shouldBeArray();
    }

    function it_returns_null_when_no_row_matches(): void
    {
        $this->fetchOne('SELECT * FROM things WHERE label = :l', [':l' => 'nope'])
            ->shouldReturn(null);
    }

    function it_fetches_all_matching_rows(): void
    {
        $this->execute('INSERT INTO things (label) VALUES (:l)', [':l' => 'a']);
        $this->execute('INSERT INTO things (label) VALUES (:l)', [':l' => 'b']);
        $this->fetchAll('SELECT * FROM things ORDER BY label')
            ->shouldHaveCount(2);
    }

    function it_returns_empty_array_when_no_rows_exist(): void
    {
        $this->fetchAll('SELECT * FROM things')
            ->shouldReturn([]);
    }

    function it_returns_last_insert_id(): void
    {
        $this->execute('INSERT INTO things (label) VALUES (:l)', [':l' => 'first']);
        $this->lastInsertId()->shouldReturn(1);
    }

    function it_returns_scalar_value(): void
    {
        $this->execute('INSERT INTO things (label) VALUES (:l)', [':l' => 'x']);
        $this->execute('INSERT INTO things (label) VALUES (:l)', [':l' => 'y']);
        $this->scalar('SELECT COUNT(*) FROM things')->shouldBeLike(2);
    }

    function it_returns_null_scalar_for_no_rows(): void
    {
        $this->scalar('SELECT label FROM things WHERE id = 999')
            ->shouldReturn(null);
    }
}
