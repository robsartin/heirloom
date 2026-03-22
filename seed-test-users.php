<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Heirloom\Config;

Config::load(__DIR__ . '/.env');

$host = Config::get('DB_HOST', '127.0.0.1');
$port = Config::get('DB_PORT', '3306');
$name = Config::get('DB_NAME', 'heirloom');
$user = Config::get('DB_USER', 'root');
$pass = Config::get('DB_PASS', '');

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$hash = password_hash('test', PASSWORD_DEFAULT);

$users = [
    ['email' => 'margaret.brushworth@example.com', 'name' => 'Margaret Brushworth'],
    ['email' => 'dave.wallspace@example.com',      'name' => 'Dave Wallspace'],
    ['email' => 'priya.colorfield@example.com',    'name' => 'Priya Colorfield'],
    ['email' => 'hank.framington@example.com',     'name' => 'Hank Framington'],
    ['email' => 'june.gallerista@example.com',     'name' => 'June Gallerista'],
];

// Reasons pool — each user will draw from these without repeating
$reasons = [
    'My cat has been staring at a blank wall for three years. She deserves culture.',
    'I recently repainted my hallway and it now looks like a hospital corridor. This would fix that.',
    'My therapist says I need more beauty in my life. I\'m taking it literally.',
    'I want to hang it above my desk so my Zoom background finally has some personality.',
    'It matches the exact shade of existential longing I feel every Monday morning.',
    'My grandmother would have loved this. She also would have tried to take it apart to see how it was made.',
    'I\'ve been losing arguments with my spouse about wall decor. This painting is my closing statement.',
    'My toddler drew on my last painting with a Sharpie. I need a replacement and I\'ve learned to hang things higher.',
    'I\'m starting a gallery in my garage. Current collection: one Ikea poster and a dart board.',
    'This painting speaks to me. Specifically, it says "take me home, I\'m tired of being on the internet."',
    'I promised myself something nice after finishing my taxes. This seems healthier than a revenge purchase on Amazon.',
    'My dog ate my last piece of art. He has no regrets but I\'m still grieving.',
    'It reminds me of a dream I had once, except in the dream the colors were arguing with each other.',
    'I need something to point at during dinner parties and say "ah yes, the artist was exploring the human condition."',
    'I collect things made with love. So far I have a hand-knit scarf, a sourdough starter, and zero paintings.',
    'My apartment has all the warmth and character of a spreadsheet. Help.',
    'I want to give this to my brother who just moved into a place that still has the previous tenant\'s nail holes.',
    'I genuinely love it and would give it a good home. Also I promise not to hang it in the bathroom.',
    'This would look perfect in my reading nook, where I pretend to read but actually nap.',
    'I\'m convinced this painting contains a secret message and I need to study it up close for approximately forever.',
    'My neighbor has original art and I\'ve been losing the quiet suburban arms race.',
    'It has the same energy as my morning coffee — warm, a little complex, and essential to my survival.',
    'I want to leave this to my kids someday so they can argue about who gets it. Building family traditions early.',
    'I once accidentally bid on a painting at a charity auction by swatting a fly. This time I\'m choosing deliberately.',
    'My office has motivational posters. I need something that motivates through beauty, not passive aggression.',
];

shuffle($reasons);
$reasonIdx = 0;

// Get all available painting IDs
$paintingIds = $pdo->query('SELECT id FROM paintings WHERE awarded_to IS NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

foreach ($users as $u) {
    // Insert user if not exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $u['email']]);
    $row = $stmt->fetch();

    if ($row) {
        $userId = (int) $row['id'];
        echo "User {$u['email']} already exists (id=$userId)\n";
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (email, name, password_hash) VALUES (:email, :name, :hash)');
        $stmt->execute([':email' => $u['email'], ':name' => $u['name'], ':hash' => $hash]);
        $userId = (int) $pdo->lastInsertId();
        echo "Created user {$u['name']} <{$u['email']}> (id=$userId)\n";
    }

    // Pick 3-7 random paintings
    $count = rand(3, 7);
    $shuffled = $paintingIds;
    shuffle($shuffled);
    $picks = array_slice($shuffled, 0, min($count, count($shuffled)));

    foreach ($picks as $paintingId) {
        $reason = $reasons[$reasonIdx % count($reasons)];
        $reasonIdx++;

        // Skip if interest already exists
        $stmt = $pdo->prepare('SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid');
        $stmt->execute([':pid' => $paintingId, ':uid' => $userId]);
        if ($stmt->fetch()) {
            continue;
        }

        $stmt = $pdo->prepare('INSERT INTO interests (painting_id, user_id, message) VALUES (:pid, :uid, :msg)');
        $stmt->execute([':pid' => $paintingId, ':uid' => $userId, ':msg' => $reason]);
        echo "  -> wants painting #$paintingId\n";
    }
}

echo "\nDone seeding test users and interests.\n";
