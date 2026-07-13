<?php

declare(strict_types=1);

it('autoloads the package and REPL contract stubs', function (): void {
    expect(interface_exists(\CoquiBot\Coqui\Contract\ReplCommandProvider::class))->toBeTrue();
    expect(true)->toBeTrue();
});
