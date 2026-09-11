<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RwandaLocationsSeeder extends Seeder
{
    private const PROVINCE_NAMES = [
        'East' => 'Eastern Province',
        'Kigali' => 'Kigali City',
        'North' => 'Northern Province',
        'South' => 'Southern Province',
        'West' => 'Western Province',
    ];

    public function run(): void
    {
        $path = database_path('data/rwanda.json');

        if (! file_exists($path)) {
            throw new RuntimeException(
                'Rwanda location data was not found at '.$path
            );
        }

        $data = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $provinces = $data['rwanda'] ?? null;

        if (! is_array($provinces) || count($provinces) !== 5) {
            throw new RuntimeException(
                'The Rwanda JSON must contain exactly five provinces.'
            );
        }

        Schema::disableForeignKeyConstraints();
        DB::table('rwanda_locations')->truncate();
        Schema::enableForeignKeyConstraints();

        $now = now();

        $provinceRows = [];

        foreach ($provinces as $provinceName => $districts) {
            $provinceRows[] = [
                'parent_id' => null,
                'name' => self::PROVINCE_NAMES[$provinceName] ?? $provinceName,
                'type' => 'province',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('rwanda_locations')->insert($provinceRows);

        $provinceIds = DB::table('rwanda_locations')
            ->where('type', 'province')
            ->pluck('id', 'name');

        $districtRows = [];

        foreach ($provinces as $provinceName => $districts) {
            $savedProvinceName =
                self::PROVINCE_NAMES[$provinceName] ?? $provinceName;

            $provinceId = $provinceIds[$savedProvinceName] ?? null;

            if (! $provinceId) {
                throw new RuntimeException(
                    'Province ID was not found for '.$savedProvinceName
                );
            }

            foreach ($districts as $districtName => $sectors) {
                $districtRows[] = [
                    'parent_id' => $provinceId,
                    'name' => $districtName,
                    'type' => 'district',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertInChunks($districtRows);

        $districtIds = DB::table('rwanda_locations')
            ->where('type', 'district')
            ->get(['id', 'parent_id', 'name'])
            ->keyBy(
                fn ($location) => $location->parent_id.'|'.$location->name
            );

        $sectorRows = [];

        foreach ($provinces as $provinceName => $districts) {
            $savedProvinceName =
                self::PROVINCE_NAMES[$provinceName] ?? $provinceName;

            $provinceId = $provinceIds[$savedProvinceName];

            foreach ($districts as $districtName => $sectors) {
                $districtKey = $provinceId.'|'.$districtName;
                $districtId = $districtIds[$districtKey]->id ?? null;

                if (! $districtId) {
                    throw new RuntimeException(
                        'District ID was not found for '.$districtName
                    );
                }

                foreach ($sectors as $sectorName => $cells) {
                    $sectorRows[] = [
                        'parent_id' => $districtId,
                        'name' => $sectorName,
                        'type' => 'sector',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertInChunks($sectorRows);

        $sectorIds = DB::table('rwanda_locations')
            ->where('type', 'sector')
            ->get(['id', 'parent_id', 'name'])
            ->keyBy(
                fn ($location) => $location->parent_id.'|'.$location->name
            );

        $cellRows = [];

        foreach ($provinces as $provinceName => $districts) {
            $savedProvinceName =
                self::PROVINCE_NAMES[$provinceName] ?? $provinceName;

            $provinceId = $provinceIds[$savedProvinceName];

            foreach ($districts as $districtName => $sectors) {
                $districtId =
                    $districtIds[$provinceId.'|'.$districtName]->id;

                foreach ($sectors as $sectorName => $cells) {
                    $sectorKey = $districtId.'|'.$sectorName;
                    $sectorId = $sectorIds[$sectorKey]->id ?? null;

                    if (! $sectorId) {
                        throw new RuntimeException(
                            'Sector ID was not found for '.$sectorName
                        );
                    }

                    foreach ($cells as $cellName => $villages) {
                        $cellRows[] = [
                            'parent_id' => $sectorId,
                            'name' => $cellName,
                            'type' => 'cell',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        $this->insertInChunks($cellRows);

        $cellIds = DB::table('rwanda_locations')
            ->where('type', 'cell')
            ->get(['id', 'parent_id', 'name'])
            ->keyBy(
                fn ($location) => $location->parent_id.'|'.$location->name
            );

        $villageRows = [];

        foreach ($provinces as $provinceName => $districts) {
            $savedProvinceName =
                self::PROVINCE_NAMES[$provinceName] ?? $provinceName;

            $provinceId = $provinceIds[$savedProvinceName];

            foreach ($districts as $districtName => $sectors) {
                $districtId =
                    $districtIds[$provinceId.'|'.$districtName]->id;

                foreach ($sectors as $sectorName => $cells) {
                    $sectorId =
                        $sectorIds[$districtId.'|'.$sectorName]->id;

                    foreach ($cells as $cellName => $villages) {
                        $cellKey = $sectorId.'|'.$cellName;
                        $cellId = $cellIds[$cellKey]->id ?? null;

                        if (! $cellId) {
                            throw new RuntimeException(
                                'Cell ID was not found for '.$cellName
                            );
                        }

                        foreach ($villages as $villageName) {
                            $villageRows[] = [
                                'parent_id' => $cellId,
                                'name' => $villageName,
                                'type' => 'village',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            if (count($villageRows) >= 500) {
                                DB::table('rwanda_locations')
                                    ->insert($villageRows);

                                $villageRows = [];
                            }
                        }
                    }
                }
            }
        }

        if ($villageRows !== []) {
            DB::table('rwanda_locations')->insert($villageRows);
        }

        $this->displayCounts();
    }

    private function insertInChunks(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('rwanda_locations')->insert($chunk);
        }
    }

    private function displayCounts(): void
    {
        $counts = DB::table('rwanda_locations')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        foreach ([
            'province',
            'district',
            'sector',
            'cell',
            'village',
        ] as $type) {
            $this->command?->line(
                ucfirst($type).': '.($counts[$type] ?? 0)
            );
        }

        $this->command?->info(
            'Rwanda locations imported successfully.'
        );
    }
}
