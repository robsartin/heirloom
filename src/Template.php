<?php
declare(strict_types=1);

namespace Heirloom;

class Template
{
    private static string $baseDir = __DIR__ . '/../templates';
    private static array $globals = [];

    public static function setGlobal(string $key, string $value): void
    {
        self::$globals[$key] = $value;
    }

    public static function render(string $template, array $data = []): void
    {
        $data = array_merge(self::$globals, $data);
        extract($data);
        $templatePath = self::$baseDir . '/' . $template . '.php';
        ob_start();
        require $templatePath;
        $content = ob_get_clean();

        if (isset($noLayout) && $noLayout) {
            echo $content;
        } else {
            $auth = $data['auth'] ?? null;
            $siteName = $data['siteName'] ?? 'Heirloom Gallery';
            require self::$baseDir . '/layout.php';
        }
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
