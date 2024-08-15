<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../Configurator');
    }

    public function testValidateBridge(): void
    {
        $this->validateModule(__DIR__ . '/../Bridge');
    }

    public function testValidateDevice(): void
    {
        $this->validateModule(__DIR__ . '/../Device');
    }

    public function testValidateGroup(): void
    {
        $this->validateModule(__DIR__ . '/../Group');
    }
}