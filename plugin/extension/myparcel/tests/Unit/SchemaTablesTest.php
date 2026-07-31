<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\Order\OrderTable;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentTable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SchemaTablesTest extends TestCase
{
    public function testShipmentSchemaAllowsMultipleShipmentsPerOrder(): void
    {
        $db = new SchemaDb([
            'reference' => 'text',
            'carrier' => 'text',
            'barcode' => 'text',
            'tracktrace_url' => 'text',
        ]);

        (new ShipmentTable())->ensure($db, 'oc_');

        $create = $db->queries[0];

        self::assertStringContainsString('KEY `idx_order_id` (`order_id`)', $create);
        self::assertStringNotContainsString('UNIQUE KEY `uniq_order_id`', $create);
        self::assertStringContainsString('UNIQUE KEY `uniq_shipment_id` (`shipment_id`)', $create);
        self::assertStringContainsString('`reference` TEXT NULL', $create);
        self::assertStringContainsString('`carrier` TEXT NULL', $create);
        self::assertStringContainsString('`barcode` TEXT NULL', $create);
        self::assertStringContainsString('`tracktrace_url` TEXT NULL', $create);
    }

    public function testOrderSchemaKeepsDeliveryOptionsSeparateFromShipments(): void
    {
        $db = new SchemaDb();

        (new OrderTable())->ensure($db, 'oc_');

        $create = $db->queries[0];

        self::assertStringContainsString('`order_id` INT NOT NULL PRIMARY KEY', $create);
        self::assertStringContainsString('`delivery_options` JSON NULL', $create);
        self::assertStringContainsString('`last_error` TEXT NULL', $create);
    }

    public function testLegacyShipmentSchemaRequiresCleanPreReleaseInstall(): void
    {
        $db = new SchemaDb([
            'delivery_options' => 'json',
            'error_message' => 'text',
            'tracktrace_url' => 'varchar(255)',
        ], ['uniq_order_id']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Drop `oc_myparcel_order` and `oc_myparcel_shipment`');

        (new ShipmentTable())->ensure($db, 'oc_');
    }
}

final class SchemaDb
{
    /** @var string[] */
    public array $queries = [];

    /**
     * @param array<string, string> $columns
     * @param string[] $indexes
     */
    public function __construct(private array $columns = [], private array $indexes = [])
    {
    }

    public function query(string $sql): object
    {
        $this->queries[] = $sql;

        if (preg_match("/SHOW COLUMNS .* LIKE '([^']+)'/", $sql, $match)) {
            $type = $this->columns[$match[1]] ?? null;

            return new SchemaQueryResult($type === null ? [] : [['Type' => $type]]);
        }

        if (preg_match("/SHOW INDEX .* `Key_name` = '([^']+)'/", $sql, $match)) {
            return new SchemaQueryResult(in_array($match[1], $this->indexes, true) ? [['Key_name' => $match[1]]] : []);
        }

        return new SchemaQueryResult();
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }
}

final class SchemaQueryResult
{
    public int $num_rows;

    /** @var array<string, mixed> */
    public array $row;

    /** @var array<int, array<string, mixed>> */
    public array $rows;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
        $this->row = $rows[0] ?? [];
        $this->num_rows = count($rows);
    }
}
