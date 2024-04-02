<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Configuration\Environment;

class DotEnvSerializer
{
    /**
     * @param array<string,string> $env
     *
     * @return string
     */
    public static function serialize(array $env): string
    {
        return implode("\n", array_map(
            static function (string $key, string $value): string {
                return 'export '.$key.'='.$value;
            },
            array_keys($env),
            $env
        ));
    }
}
