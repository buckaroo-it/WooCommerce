<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Hooks\CronEvents;

/**
 * The daily log cleanup is scheduled on init, so reading the cron schedules
 * does not load translations too early.
 */
class Test_CronEvents extends WP_UnitTestCase
{
    private const HOOK = 'buckaroo_clean_logger_storage';

    protected function setUp(): void
    {
        parent::setUp();

        wp_clear_scheduled_hook(self::HOOK);
    }

    protected function tearDown(): void
    {
        wp_clear_scheduled_hook(self::HOOK);

        parent::tearDown();
    }

    public function test_constructing_does_not_schedule_before_init(): void
    {
        $cron = new CronEvents();

        $this->assertFalse(wp_next_scheduled(self::HOOK));
        $this->assertSame(10, has_action('init', [$cron, 'schedule']));
    }

    public function test_schedule_adds_the_daily_cleanup_once(): void
    {
        $cron = new CronEvents();

        $cron->schedule();
        $first = wp_next_scheduled(self::HOOK);
        $cron->schedule();

        $this->assertIsInt($first);
        $this->assertSame($first, wp_next_scheduled(self::HOOK));
        $this->assertSame('daily', wp_get_schedule(self::HOOK));
    }
}
