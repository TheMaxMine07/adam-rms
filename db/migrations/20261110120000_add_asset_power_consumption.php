<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAssetPowerConsumption extends AbstractMigration
{
    public function change(): void
    {
        $this->table('assetTypes')
            ->addColumn('assetTypes_powerConsumptionW', 'decimal', [
                'null' => true,
                'precision' => 12,
                'scale' => 3,
                'after' => 'assetTypes_mass',
            ])
            ->update();

        $this->table('assets')
            ->addColumn('assets_powerConsumptionW', 'decimal', [
                'null' => true,
                'precision' => 12,
                'scale' => 3,
                'after' => 'assets_mass',
            ])
            ->update();
    }
}
