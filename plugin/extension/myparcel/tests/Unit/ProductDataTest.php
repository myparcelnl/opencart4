<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Helper\ProductData;
use MyParcelNL\OpenCart\Core\Helper\ProductExportDataResolver;
use MyParcelNL\OpenCart\Core\Helper\WeightResolver;
use PHPUnit\Framework\TestCase;

if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', 'oc_');
}

class ProductDataTest extends TestCase
{
    public function testLoadsAllProductsInAFixedNumberOfQueries(): void
    {
        $db = new ProductDataDb();
        $products = [
            ['product_id' => 10, 'quantity' => 1],
            ['product_id' => 20, 'quantity' => 2],
        ];

        $data = ProductData::load($db, 1, $products);

        self::assertCount(5, $db->queries);

        $weighted = (new WeightResolver())->attachWeights($data, $products);
        $exportProducts = (new ProductExportDataResolver())->attach($data, $weighted);

        self::assertSame(1500, $exportProducts[0]['weight']);
        self::assertSame(250, $exportProducts[1]['weight']);
        self::assertSame('610910', $exportProducts[0]['hs_code']);
        self::assertSame('IT', $exportProducts[0]['country_of_origin']);
        self::assertTrue($exportProducts[0]['requires_shipping']);
        self::assertSame('', $exportProducts[1]['hs_code']);
        self::assertSame('', $exportProducts[1]['country_of_origin']);
        self::assertFalse($exportProducts[1]['requires_shipping']);
        self::assertCount(5, $db->queries, 'Resolvers should not run extra queries.');
        self::assertStringContainsString(
            'LEFT JOIN `oc_myparcel_product`',
            $db->queries[4]
        );
    }


    public function testMissingGramClassReturnsZeroWeight(): void
    {
        $db = new ProductDataDb(true, false);
        $products = [['product_id' => 10, 'quantity' => 1]];

        $data = ProductData::load($db, 1, $products);
        $weighted = (new WeightResolver())->attachWeights($data, $products);

        self::assertSame(0, $weighted[0]['weight']);
    }
}

final class ProductDataDb
{
    /** @var string[] */
    public array $queries = [];

    public function __construct(
        private bool $hasCentimetreClass = true,
        private bool $hasGramClass = true
    ) {
    }

    public function query(string $sql): object
    {
        $this->queries[] = $sql;

        if (str_contains($sql, 'FROM `oc_weight_class`')) {
            return new ProductQueryResult([
                ['weight_class_id' => 1, 'value' => 1],
                ['weight_class_id' => 2, 'value' => 1000],
            ]);
        }

        if (str_contains($sql, 'FROM `oc_length_class`')) {
            return new ProductQueryResult($this->hasCentimetreClass ? [['length_class_id' => 3, 'value' => 1]] : []);
        }

        if (str_contains($sql, 'FROM `oc_weight_class_description`')) {
            return new ProductQueryResult($this->hasGramClass ? [['weight_class_id' => 2]] : []);
        }

        if (str_contains($sql, 'FROM `oc_length_class_description`')) {
            return new ProductQueryResult($this->hasCentimetreClass ? [['length_class_id' => 3]] : []);
        }

        if (str_contains($sql, 'FROM `oc_product`')) {
            return new ProductQueryResult([
                [
                    'product_id' => 10,
                    'shipping' => 1,
                    'weight' => 1.5,
                    'weight_class_id' => 1,
                    'length' => 30,
                    'width' => 10,
                    'height' => 5,
                    'length_class_id' => 3,
                    'hs_code' => '610910',
                    'country_of_origin' => 'it',
                ],
                [
                    'product_id' => 20,
                    'shipping' => 0,
                    'weight' => 250,
                    'weight_class_id' => 2,
                    'length' => 15,
                    'width' => 20,
                    'height' => 10,
                    'length_class_id' => 3,
                    'hs_code' => '',
                    'country_of_origin' => '',
                ],
            ]);
        }

        return new ProductQueryResult();
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }
}

final class ProductQueryResult
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
