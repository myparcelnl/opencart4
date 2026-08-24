<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use JsonSerializable;
use MyParcelNL\OpenCart\Core\Dto\ContractDefinitions;
use PHPUnit\Framework\TestCase;

class ContractDefinitionsTest extends TestCase
{
    public function testNormalisesSdkObjectsAndTypesTopLevelFields(): void
    {
        $contract = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['carrier' => 'POSTNL'];
            }
        };

        $definitions = ContractDefinitions::fromArray([
            'schema_version' => '2',
            'environment' => 'acceptance',
            'account_id' => 123,
            'shop_id' => '456',
            'platform' => 'MYPARCEL',
            'default_carrier' => 'POSTNL',
            'contract_definitions' => [$contract],
            'carrier_catalog' => [[
                'id' => 1,
                'slug' => 'postnl',
                'name' => 'PostNL',
                'logo_svg' => '/skin/general-images/carrier-logos/svg/24/postnl.svg',
            ]],
            'fetched_at' => '1700000000',
        ]);

        self::assertSame(2, $definitions->schemaVersion);
        self::assertSame('123', $definitions->accountId);
        self::assertSame(456, $definitions->shopId);
        self::assertSame('POSTNL', $definitions->defaultCarrier);
        self::assertSame([['carrier' => 'POSTNL']], $definitions->contracts);
        self::assertSame('postnl', $definitions->carrierCatalog->slugForId(1));
        self::assertSame(1, $definitions->summary()['carrier_count']);
    }

    public function testDropsItemsTheSdkCannotEncodeAndKeepsTheRest(): void
    {
        $known = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['carrier' => 'POSTNL'];
            }
        };
        $unknown = new class implements JsonSerializable {
            public function getCarrier(): string
            {
                return 'NEW_ACCEPTANCE_CARRIER';
            }

            public function jsonSerialize(): array
            {
                throw new \InvalidArgumentException("Invalid value for enum 'Carrier'");
            }
        };

        $skipped = [];
        $items = ContractDefinitions::filterEncodable(
            [$known, $unknown],
            static function ($item, \Throwable $e) use (&$skipped): void {
                $skipped[] = [$item->getCarrier(), $e->getMessage()];
            }
        );

        self::assertSame([$known], $items);
        self::assertSame([['NEW_ACCEPTANCE_CARRIER', "Invalid value for enum 'Carrier'"]], $skipped);
    }

    public function testAddsAnImportErrorWithoutDroppingDefinitions(): void
    {
        $definitions = ContractDefinitions::fromArray([
            'contract_definitions' => [['carrier' => 'POSTNL']],
        ])->withLastError('transport_error', 1234);

        self::assertSame([['carrier' => 'POSTNL']], $definitions->contracts);
        self::assertSame(['timestamp' => 1234, 'reason' => 'transport_error'], $definitions->lastError);
        self::assertNull($definitions->defaultCarrier);
    }

    public function testUpdatesDefaultCarrierWithoutDroppingCachedData(): void
    {
        $definitions = ContractDefinitions::fromArray([
            'account_id' => '123',
            'contract_definitions' => [['carrier' => 'POSTNL']],
        ])->withDefaultCarrier('POSTNL');

        self::assertSame('POSTNL', $definitions->defaultCarrier);
        self::assertSame('123', $definitions->accountId);
        self::assertSame([['carrier' => 'POSTNL']], $definitions->contracts);
        self::assertSame('POSTNL', $definitions->toArray()['default_carrier']);
    }
}
