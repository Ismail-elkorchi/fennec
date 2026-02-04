<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function testBootstrapMessage(): void
    {
        $output = $this->captureBootstrapOutput();

        $this->assertSame('Fennec bootstrap', trim($output));
    }

    private function captureBootstrapOutput(): string
    {
        ob_start();
        include __DIR__ . '/../public/index.php';
        return (string) ob_get_clean();
    }
}
