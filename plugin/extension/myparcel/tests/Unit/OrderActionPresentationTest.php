<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrderActionPresentationTest extends TestCase
{
    public function testTwigOwnsActionAppearanceWhileTheControllerProvidesSemanticActions(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/admin/controller/event/order.php');
        $button = (string) file_get_contents($root . '/admin/view/template/event/order_action_button.twig');

        self::assertStringContainsString("'action' => \$method", $controller);
        self::assertStringNotContainsString("'icon' =>", $controller);
        self::assertStringNotContainsString("'variant' =>", $controller);
        self::assertStringContainsString("export: { variant: 'btn-success', icon: 'fa-truck'", $button);
        self::assertStringContainsString("label: { variant: 'btn-info', icon: 'fa-file-pdf'", $button);
        self::assertStringContainsString("trackTrace: { variant: 'btn-secondary', icon: 'fa-location-dot'", $button);
    }

    public function testActionPartialsUseOpenCartsRouteAwareViewLoader(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/admin/controller/event/order.php');
        $actions = (string) file_get_contents($root . '/admin/view/template/event/order_actions.twig');

        self::assertStringNotContainsString('{% include', $actions);
        self::assertStringContainsString(
            "self::EVENT_VIEW_ROUTE . 'order_action_button'",
            $controller
        );
        self::assertStringContainsString(
            "self::EVENT_VIEW_ROUTE . 'order_shipment_count_badge'",
            $controller
        );
    }
}
