<?php

namespace App\Support;

use App\Models\User;

/** Nombre corto para la barra: «Alex A.»; si dos usuarios coinciden, más letras del apellido («Alex Ar.», «Alex Arr.»…). */
class NombreCorto
{
    public static function de(User $u): string
    {
        $n = self::partes($u->name);
        if ($n[1] === '') {
            return $n[0];
        }
        $otros = self::todos()->reject(fn ($x) => $x->id === $u->id);
        for ($i = 1; $i <= mb_strlen($n[1]); $i++) {
            $c = self::corto($n, $i);
            if (! $otros->contains(fn ($x) => self::corto(self::partes($x->name), $i) === $c)) {
                return $c;
            }
        }

        return $u->name;
    }

    private static function todos()
    {
        static $t;

        return $t ??= User::query()->get(['id', 'name']);
    }

    private static function partes(string $nombre): array
    {
        $p = preg_split('/\s+/u', trim($nombre));

        return [$p[0] ?? '', $p[1] ?? ''];
    }

    private static function corto(array $n, int $letras): string
    {
        return $n[0].' '.mb_substr($n[1], 0, $letras).'.';
    }
}
