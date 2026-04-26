<?php

namespace Tests\Unit\Support;

use App\Support\PlausibleHttpImageUrl;
use Tests\TestCase;

class PlausibleHttpImageUrlTest extends TestCase
{
    public function test_accepts_jet2_style_urls_and_query_strings(): void
    {
        $this->assertTrue(PlausibleHttpImageUrl::is('https://media.jet2.com/is/image/jet2/PMI_69571_T'));
        $this->assertTrue(PlausibleHttpImageUrl::is('https://media.jet2.com/is/image/jet2/x?fmt=png&qlt=60'));
    }

    public function test_rejects_garbage(): void
    {
        $this->assertFalse(PlausibleHttpImageUrl::is('not a url'));
        $this->assertFalse(PlausibleHttpImageUrl::is(''));
    }
}
