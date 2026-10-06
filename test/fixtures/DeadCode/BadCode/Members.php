<?php

declare(strict_types=1);

final class Members
{
    public const VALUE = 'value';

    public string $value = 'value';

    public function read(): string
    {
        return self::VALUE . $this->value;
    }

    public const UNUSED = 'unused';

    public string $unused = 'unused';

    public function unused(): string
    {
        return 'unused';
    }
}

enum State
{
    case Active;
    case Inactive;
}

echo (new Members())->read();
echo State::Active->name;
