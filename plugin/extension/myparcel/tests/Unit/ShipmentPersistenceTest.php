<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use Opencart\Admin\Model\Extension\Myparcel\Shipment\Myparcel as ShipmentModel;
use Opencart\Catalog\Model\Extension\Myparcel\Checkout\DeliveryOptions;
use Opencart\System\Engine\Registry;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/admin/model/shipment/myparcel.php';
require_once dirname(__DIR__, 2) . '/catalog/model/checkout/delivery_options.php';

class ShipmentPersistenceTest extends TestCase
{
    public function testTwoExportsAppendTwoShipmentRows(): void
    {
        $db = new RecordingDb();
        $model = new ShipmentModel($this->registry($db));

        $model->markExported(42, 1001, 'order-42', 'postnl');
        $model->markExported(42, 1002, 'order-42', 'postnl');

        $inserts = array_values(array_filter(
            $db->queries,
            static fn (string $sql): bool => str_contains($sql, 'INSERT INTO `oc_myparcel_shipment`')
        ));

        self::assertCount(2, $inserts);
        self::assertMatchesRegularExpression('/VALUES \(42, 1001,/', $inserts[0]);
        self::assertMatchesRegularExpression('/VALUES \(42, 1002,/', $inserts[1]);
        self::assertStringNotContainsString('NOW()', implode("\n", $db->queries));
    }

    public function testClearingDeliveryOptionsKeepsAnExportError(): void
    {
        $db = new RecordingDb();
        $model = new DeliveryOptions($this->registry($db));

        $model->deleteDeliveryOptions(42);

        self::assertCount(2, $db->queries);
        self::assertStringContainsString('SET `delivery_options` = NULL', $db->queries[0]);
        self::assertStringContainsString('`last_error` IS NULL', $db->queries[1]);
    }

    public function testExportStateIncludesTheLastError(): void
    {
        $db = new RecordingDb();
        $db->results[] = new PersistenceQueryResult([[
            'order_id' => 42,
            'delivery_options' => '{}',
            'last_error' => '3704: Weight should be between 50 and 70000',
        ]]);
        $db->results[] = new PersistenceQueryResult([[
            'order_id' => 42,
            'shipment_id' => 1001,
            'shipment_count' => 3,
            'status' => 'exported',
            'carrier' => 'upsstandard',
        ]]);
        $model = new ShipmentModel($this->registry($db));

        $states = $model->statesByOrderIds([42]);

        self::assertSame('3704: Weight should be between 50 and 70000', $states[42]['last_error']);
        self::assertSame(1001, $states[42]['shipment_id']);
        self::assertSame(3, $states[42]['shipment_count']);
        self::assertStringContainsString('COUNT(*) AS `shipment_count`', $db->queries[1]);
        self::assertStringContainsString('`last_error`', $db->queries[0]);
    }

    public function testTrackTraceUpdateKeepsExistingValuesWhenTheApiReturnsEmptyFields(): void
    {
        $db = new RecordingDb();
        $model = new ShipmentModel($this->registry($db));

        $model->updateTrackTrace(1001, '', null);

        self::assertStringContainsString("COALESCE(NULLIF('', ''), `barcode`)", $db->queries[0]);
        self::assertStringContainsString("COALESCE(NULLIF('', ''), `tracktrace_url`)", $db->queries[0]);
        self::assertStringContainsString('WHERE `shipment_id` = 1001', $db->queries[0]);
    }

    private function registry(RecordingDb $db): Registry
    {
        $registry = new Registry();
        $registry->set('db', $db);

        return $registry;
    }
}

final class RecordingDb
{
    /** @var string[] */
    public array $queries = [];

    /** @var PersistenceQueryResult[] */
    public array $results = [];

    public function query(string $sql): object
    {
        $this->queries[] = $sql;

        return array_shift($this->results) ?? new PersistenceQueryResult();
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }
}

final class PersistenceQueryResult
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(public array $rows = [])
    {
        $this->num_rows = count($rows);
        $this->row = $rows[0] ?? [];
    }

    public int $num_rows = 0;

    /** @var array<string, mixed> */
    public array $row = [];

}
