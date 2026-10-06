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
}

enum State
{
    case Active;
}

echo (new Members())->read();
echo State::Active->name;
