<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter;

it('turns SGR colours into class segments', function (): void {
    $segments = new AnsiFormatter()->segments("plain \e[32mgreen\e[39m \e[1;31mbold red\e[0m end");

    expect($segments)->toBe([
        ['text' => 'plain ', 'classes' => ''],
        ['text' => 'green', 'classes' => 'lau-fg-green'],
        ['text' => ' ', 'classes' => ''],
        ['text' => 'bold red', 'classes' => 'lau-fg-red lau-bold'],
        ['text' => ' end', 'classes' => ''],
    ]);
});

it('never produces markup: text is returned verbatim for the client to set as textContent', function (): void {
    $segments = new AnsiFormatter()->segments("\e[31m<script>alert(1)</script>\e[0m");

    expect($segments[0]['text'])->toBe('<script>alert(1)</script>')
        ->and($segments[0]['classes'])->toBe('lau-fg-red');
});

it('strips hyperlinks and cursor sequences', function (): void {
    $text = "\e]8;;https://example.com\e\\link\e]8;;\e\\ \e[2Kdone";

    expect(new AnsiFormatter()->toPlain($text))->toBe('link done');
});

it('keeps only the final state of a carriage-return redraw', function (): void {
    expect(new AnsiFormatter()->toPlain("10%\r50%\r100%\nnext"))->toBe("100%\nnext");
});

it('consumes 256-colour and truecolour parameters without misreading them', function (): void {
    $segments = new AnsiFormatter()->segments("\e[38;5;196mx\e[0m\e[38;2;1;2;3;4my");

    expect($segments)->toBe([
        ['text' => 'x', 'classes' => ''],
        ['text' => 'y', 'classes' => 'lau-underline'],
    ]);
});
