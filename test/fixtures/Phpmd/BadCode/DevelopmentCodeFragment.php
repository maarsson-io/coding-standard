<?php

declare(strict_types=1);

final class DevelopmentCodeFragment
{
    public function run(): string
    {
        \dd('value');
        \dump('value');

        return 'value';
    }
}
