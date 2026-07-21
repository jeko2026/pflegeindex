<?php

namespace Tests\Feature;

use Tests\TestCase;

class SocialImageAssetTest extends TestCase
{
    public function test_default_social_image_is_a_square_public_png_asset(): void
    {
        $path = public_path('assets/og-image.png');
        $image = getimagesize($path);

        $this->assertFileExists($path);
        $this->assertIsArray($image);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame($image[0], $image[1]);
        $this->assertGreaterThanOrEqual(1200, $image[0]);
        $this->assertGreaterThan(0, filesize($path));
    }
}
