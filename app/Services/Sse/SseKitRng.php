<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Hasard déterministe du générateur de contenu SSE : xorshift32 local, amorcé par une graine et un nom de flux.
 * Aucun appel à mt_srand / random_int : une même graine donne le même tirage sur toutes les versions de PHP, et l'état
 * global n'est pas touché.
 */
final class SseKitRng
{
    private int $state;

    public function __construct(int $seed, string $stream = '')
    {
        $s = crc32('sse-kit:' . $seed . ':' . $stream) & 0xFFFFFFFF;
        $this->state = $s !== 0 ? $s : 0x9E3779B9;
    }

    public function next(): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return $this->state;
    }

    /** Entier dans [min, max] (bornes comprises). */
    public function int(int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        return $min + $this->next() % ($max - $min + 1);
    }

    /** Vrai avec une probabilité de $percent sur 100. */
    public function chance(int|float $percent): bool
    {
        return $this->next() % 10000 < (int) round($percent * 100);
    }

    /**
     * @template T
     * @param list<T> $list
     * @return T
     */
    public function pick(array $list): mixed
    {
        if ($list === []) {
            throw new \InvalidArgumentException('Liste vide.');
        }

        return $list[$this->next() % count($list)];
    }

    /**
     * Tirage pondéré : renvoie l'indice.
     *
     * @param list<int> $weights
     */
    public function weighted(array $weights): int
    {
        $total = max(1, array_sum($weights));
        $roll = $this->next() % $total;
        foreach ($weights as $i => $w) {
            $roll -= $w;
            if ($roll < 0) {
                return $i;
            }
        }

        return count($weights) - 1;
    }

    /**
     * Mélange déterministe.
     *
     * @template T
     * @param list<T> $list
     * @return list<T>
     */
    public function shuffle(array $list): array
    {
        for ($i = count($list) - 1; $i > 0; $i--) {
            $j = $this->next() % ($i + 1);
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }

        return $list;
    }

    /** Motif : « # » chiffre, « @ » lettre du jeu donné, le reste recopié. */
    public function pattern(string $pattern, string $letters = 'ABCDEFGHJKLMNPRSTUVWXYZ'): string
    {
        $out = '';
        $len = mb_strlen($pattern);
        $n = mb_strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($pattern, $i, 1);
            $out .= match ($ch) {
                '#' => (string) ($this->next() % 10),
                '@' => mb_substr($letters, $this->next() % $n, 1),
                default => $ch,
            };
        }

        return $out;
    }
}
