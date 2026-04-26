<?php

namespace Tests\Unit\Support;

use App\Support\BoardBasisDisplay;
use Tests\TestCase;

class BoardBasisDisplayTest extends TestCase
{
    public function test_it_maps_jet2_numeric_board_ids(): void
    {
        $this->assertSame('All Inclusive', BoardBasisDisplay::humanLabel('5', null));
        $this->assertSame('Bed & Breakfast', BoardBasisDisplay::humanLabel('2', null));
    }

    public function test_it_maps_letter_codes_case_insensitively(): void
    {
        $this->assertSame('All Inclusive', BoardBasisDisplay::humanLabel('ai', null));
        $this->assertSame('Half Board', BoardBasisDisplay::humanLabel('HB', null));
    }

    public function test_it_prefers_known_type_over_recommended_when_type_maps(): void
    {
        $this->assertSame('Bed & Breakfast', BoardBasisDisplay::humanLabel('2', 'Half Board'));
    }

    public function test_it_uses_recommended_when_type_is_unknown_numeric(): void
    {
        $this->assertSame('All Inclusive Plus', BoardBasisDisplay::humanLabel('8', 'All Inclusive Plus'));
    }

    public function test_it_returns_null_for_unknown_numeric_without_recommended(): void
    {
        $this->assertNull(BoardBasisDisplay::humanLabel('8', null));
        $this->assertNull(BoardBasisDisplay::humanLabel('8', '   '));
    }

    public function test_it_formats_slug_board_types(): void
    {
        $this->assertSame('All Inclusive', BoardBasisDisplay::humanLabel('all_inclusive', null));
    }
}
