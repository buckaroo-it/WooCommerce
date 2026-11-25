<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Order\OrderArticles;

/**
 * Size of remote product images, read through the WordPress HTTP API.
 */
class Test_OrderArticlesImageSize extends WP_UnitTestCase
{
    /** @var array|null Arguments of the last HTTP request */
    private $requestArgs;

    /** @var int Number of HTTP requests made */
    private $requestCount = 0;

    public function test_remote_image_size_is_read_from_the_response_body(): void
    {
        $this->respondWith(['body' => $this->png(320, 240), 'response' => ['code' => 200, 'message' => 'OK']]);

        $size = $this->imageSize('https://shop.test/wp-content/uploads/mug.png');

        $this->assertIsArray($size);
        $this->assertSame(320, $size[0]);
        $this->assertSame(240, $size[1]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function test_remote_image_is_fetched_with_a_short_timeout(): void
    {
        $this->respondWith(['body' => $this->png(320, 240), 'response' => ['code' => 200, 'message' => 'OK']]);

        $this->imageSize('https://shop.test/wp-content/uploads/mug.png');

        $this->assertSame(3, $this->requestArgs['timeout']);
    }

    public function test_only_the_start_of_a_remote_image_is_downloaded(): void
    {
        $this->respondWith(['body' => $this->png(320, 240), 'response' => ['code' => 200, 'message' => 'OK']]);

        $this->imageSize('https://shop.test/wp-content/uploads/mug.png');

        $this->assertSame(65536, $this->requestArgs['limit_response_size']);
    }

    public function test_image_is_read_with_a_single_request_when_its_start_holds_the_size(): void
    {
        $this->respondWith(['body' => $this->fullPng(640, 480), 'response' => ['code' => 200, 'message' => 'OK']]);

        $this->imageSize('https://shop.test/wp-content/uploads/poster.png');

        $this->assertSame(1, $this->requestCount);
    }

    public function test_whole_image_is_read_when_metadata_pushes_the_size_past_the_first_bytes(): void
    {
        $this->respondWith(['body' => $this->jpegWithLargeMetadata(2000, 100), 'response' => ['code' => 200, 'message' => 'OK']]);

        $size = $this->imageSize('https://shop.test/wp-content/uploads/photo.jpg');

        $this->assertIsArray($size);
        $this->assertSame(2000, $size[0]);
        $this->assertSame(100, $size[1]);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertSame(2, $this->requestCount);
        $this->assertSame(5242880, $this->requestArgs['limit_response_size']);
    }

    public function test_large_error_page_is_read_with_a_single_request(): void
    {
        $this->respondWith(['body' => $this->largeHtmlPage(), 'response' => ['code' => 404, 'message' => 'Not Found']]);

        $this->assertFalse($this->imageSize('https://shop.test/wp-content/uploads/missing.jpg'));
        $this->assertSame(1, $this->requestCount);
    }

    public function test_large_response_that_is_not_a_jpeg_is_read_with_a_single_request(): void
    {
        $this->respondWith(['body' => $this->largeHtmlPage(), 'response' => ['code' => 200, 'message' => 'OK']]);

        $this->assertFalse($this->imageSize('https://shop.test/wp-content/uploads/page.jpg'));
        $this->assertSame(1, $this->requestCount);
    }

    public function test_size_is_read_from_a_partially_downloaded_image(): void
    {
        $image = $this->fullPng(640, 480);
        $this->respondWith(['body' => substr($image, 0, 64), 'response' => ['code' => 200, 'message' => 'OK']]);

        $size = $this->imageSize('https://shop.test/wp-content/uploads/poster.png');

        $this->assertIsArray($size);
        $this->assertSame(640, $size[0]);
        $this->assertSame(480, $size[1]);
    }

    public function test_failed_request_returns_false(): void
    {
        $this->respondWith(new WP_Error('http_request_failed', 'Operation timed out'));

        $this->assertFalse($this->imageSize('https://shop.test/wp-content/uploads/mug.png'));
    }

    public function test_response_that_is_not_an_image_returns_false(): void
    {
        $this->respondWith(['body' => '<html>Not found</html>', 'response' => ['code' => 404, 'message' => 'Not Found']]);

        $this->assertFalse($this->imageSize('https://shop.test/wp-content/uploads/missing.png'));
    }

    public function test_empty_response_returns_false(): void
    {
        $this->respondWith(['body' => '', 'response' => ['code' => 200, 'message' => 'OK']]);

        $this->assertFalse($this->imageSize('https://shop.test/wp-content/uploads/empty.png'));
    }

    /**
     * @param  array|WP_Error  $response
     */
    private function respondWith($response): void
    {
        add_filter('pre_http_request', function ($preempt, $args) use ($response) {
            $this->requestArgs = $args;
            $this->requestCount++;

            if (is_array($response) && ! empty($args['limit_response_size'])) {
                $response['body'] = substr($response['body'], 0, $args['limit_response_size']);
            }

            return $response;
        }, 10, 2);
    }

    /**
     * @return array|false
     */
    private function imageSize(string $src)
    {
        $articles = (new ReflectionClass(OrderArticles::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(OrderArticles::class, 'safe_remote_getimagesize');
        $method->setAccessible(true);

        return $method->invoke($articles, $src);
    }

    /**
     * Complete PNG image, encoded by GD
     */
    private function fullPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function largeHtmlPage(): string
    {
        return '<html><body>' . str_repeat('<p>Page not found</p>', 5000) . '</body></html>';
    }

    /**
     * JPEG with two large ICC profile segments before the frame that holds its size
     */
    private function jpegWithLargeMetadata(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        $jpeg = (string) ob_get_clean();

        $payload = str_repeat("\0", 65000);
        $segment = "\xFF\xE2" . pack('n', strlen($payload) + 2) . $payload;

        return substr($jpeg, 0, 2) . $segment . $segment . substr($jpeg, 2);
    }

    /**
     * PNG signature and header chunk, enough to read the image size
     */
    private function png(int $width, int $height): string
    {
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . pack('N', strlen($header)) . 'IHDR' . $header . pack('N', crc32('IHDR' . $header));
    }
}
