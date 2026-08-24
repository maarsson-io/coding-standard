<?php

declare(strict_types=1);

final class IncompatibleReturnType
{
    /**
    * @return int
    */
    public function run(): int
    {
        return 456;
    }
}
