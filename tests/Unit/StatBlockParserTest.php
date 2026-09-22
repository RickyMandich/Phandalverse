<?php

namespace Tests\Unit;

use App\Services\StatBlockParser;
use PHPUnit\Framework\TestCase;

class StatBlockParserTest extends TestCase
{
    public function test_extracts_only_attribute_scores_without_bonuses(): void
    {
        $markdown = <<<MD
> # mind flayer
> *Aberrazione Media, Legale Malvagio*
> ___
> - **Classe Armatura** 15 (corazza a piastre)
> - **Punti Vita** 71 (13d8 + 13)
> - **Velocità** 9m
> ___
> |FOR|DES|COS|INT|SAG|CAR|
> |:---:|:---:|:---:|:---:|:---:|:---:|
> |11 (+0)|12 (+1)|12 (+1)|19 (+4)|17 (+3)|17 (+3)|
> ___
MD;

        $parsed = StatBlockParser::parse($markdown);

        $this->assertSame([
            'for' => 11,
            'des' => 12,
            'cos' => 12,
            'int' => 19,
            'sag' => 17,
            'car' => 17,
        ], $parsed['attributes']);
    }

    public function test_extracts_scores_with_negative_bonus_and_raw_integers(): void
    {
        $markdown = <<<MD
> |FOR|DES|COS|INT|SAG|CAR|
> |:---:|:---:|:---:|:---:|:---:|:---:|
> |8 (-1)|10|14 (+2)|**16** (+3)|9 (-1)|10 (+0)|
MD;

        $parsed = StatBlockParser::parse($markdown);

        $this->assertSame([
            'for' => 8,
            'des' => 10,
            'cos' => 14,
            'int' => 16,
            'sag' => 9,
            'car' => 10,
        ], $parsed['attributes']);
    }
}
