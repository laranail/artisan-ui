<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Output;

/**
 * Turns ANSI-coloured console output into styled segments: `{text, classes}` pairs.
 *
 * No HTML is produced here, on purpose. The browser renders each segment with `textContent`
 * and the Blade views with `{{ }}`, and the class names come from a fixed table, so nothing
 * in a command's output can ever become markup in the panel (engineering reference, Part IV:
 * "never innerHTML a server-derived string").
 *
 * Handles the SGR subset Artisan actually produces (bold, dim, italic, underline, the 16
 * foreground and background colours, resets); 256-colour and truecolour parameters are
 * consumed and ignored. Every other escape sequence, including OSC 8 hyperlinks, is removed.
 * Carriage-return progress redraws keep only their final state.
 *
 * `laranail/console` measures and truncates ANSI text but does not convert it; this is a
 * candidate to move there.
 */
final class AnsiFormatter
{
    private const array COLOURS = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'];

    /**
     * @return list<array{text: string, classes: string}> adjacent segments with the same
     *                                                    classes are merged
     */
    public function segments(string $text): array
    {
        $text = $this->normaliseLines($this->stripNonSgr($text));

        $parts = preg_split('/\e\[([0-9;]*)m/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [['text' => $this->toPlain($text), 'classes' => '']];
        }

        $state = ['fg' => null, 'bg' => null, 'bold' => false, 'dim' => false, 'italic' => false, 'underline' => false];

        /** @var list<array{text: string, classes: string}> $segments */
        $segments = [];
        $text = '';
        $classes = '';

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $state = $this->apply($state, $part);

                continue;
            }

            if ($part === '') {
                continue;
            }

            $next = $this->classes($state);

            if ($text !== '' && $next !== $classes) {
                $segments[] = ['text' => $text, 'classes' => $classes];
                $text = '';
            }

            $text .= $part;
            $classes = $next;
        }

        if ($text !== '') {
            $segments[] = ['text' => $text, 'classes' => $classes];
        }

        return $segments;
    }

    public function toPlain(string $text): string
    {
        return $this->normaliseLines((string) preg_replace('/\e\[[0-9;]*m/', '', $this->stripNonSgr($text)));
    }

    private function stripNonSgr(string $text): string
    {
        // OSC sequences (hyperlinks, titles), terminated by BEL or ST.
        $text = (string) preg_replace('/\e\][^\a\e]*(?:\a|\e\\\\)/', '', $text);

        // CSI sequences other than SGR (cursor movement, erase line, ...).
        $text = (string) preg_replace('/\e\[[0-9;?]*[A-La-ln-z]/', '', $text);

        // Anything else left behind: a lone ESC and the character after it.
        return (string) preg_replace('/\e(?!\[[0-9;]*m)./s', '', $text);
    }

    private function normaliseLines(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);

        if (! str_contains($text, "\r")) {
            return $text;
        }

        return implode("\n", array_map(
            static function (string $line): string {
                $segments = explode("\r", $line);

                return end($segments);
            },
            explode("\n", $text),
        ));
    }

    /**
     * @param array{fg: ?string, bg: ?string, bold: bool, dim: bool, italic: bool, underline: bool} $state
     *
     * @return array{fg: ?string, bg: ?string, bold: bool, dim: bool, italic: bool, underline: bool}
     */
    private function apply(array $state, string $parameters): array
    {
        $codes = $parameters === '' ? [0] : array_map(intval(...), explode(';', $parameters));

        for ($i = 0, $count = count($codes); $i < $count; $i++) {
            $code = $codes[$i];

            switch (true) {
                case $code === 0:
                    $state = ['fg' => null, 'bg' => null, 'bold' => false, 'dim' => false, 'italic' => false, 'underline' => false];
                    break;
                case $code === 1:
                    $state['bold'] = true;
                    break;
                case $code === 2:
                    $state['dim'] = true;
                    break;
                case $code === 3:
                    $state['italic'] = true;
                    break;
                case $code === 4:
                    $state['underline'] = true;
                    break;
                case $code === 22:
                    $state['bold'] = $state['dim'] = false;
                    break;
                case $code === 23:
                    $state['italic'] = false;
                    break;
                case $code === 24:
                    $state['underline'] = false;
                    break;
                case $code >= 30 && $code <= 37:
                    $state['fg'] = self::COLOURS[$code - 30];
                    break;
                case $code >= 90 && $code <= 97:
                    $state['fg'] = 'bright-' . self::COLOURS[$code - 90];
                    break;
                case $code === 39:
                    $state['fg'] = null;
                    break;
                case $code >= 40 && $code <= 47:
                    $state['bg'] = self::COLOURS[$code - 40];
                    break;
                case $code >= 100 && $code <= 107:
                    $state['bg'] = 'bright-' . self::COLOURS[$code - 100];
                    break;
                case $code === 49:
                    $state['bg'] = null;
                    break;
                case $code === 38 || $code === 48:
                    // 38;5;n or 38;2;r;g;b: consume the parameters, render nothing.
                    $i += ($codes[$i + 1] ?? null) === 2 ? 4 : 2;
                    break;
            }
        }

        return $state;
    }

    /**
     * @param array{fg: ?string, bg: ?string, bold: bool, dim: bool, italic: bool, underline: bool} $state
     */
    private function classes(array $state): string
    {
        $classes = [];

        if ($state['fg'] !== null) {
            $classes[] = 'lau-fg-' . $state['fg'];
        }

        if ($state['bg'] !== null) {
            $classes[] = 'lau-bg-' . $state['bg'];
        }

        foreach (['bold', 'dim', 'italic', 'underline'] as $flag) {
            if ($state[$flag]) {
                $classes[] = 'lau-' . $flag;
            }
        }

        return implode(' ', $classes);
    }
}
