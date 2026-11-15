<?php

namespace App\Services\SystemMasterData;

use App\Constants\Status;
use App\Models\Sync;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;

class SystemMasterDataRecordSynchronizer
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(mixed): array{key: int|string, attributes: array<string, mixed>}|array{skip_reason: string}  $mapItem
     */
    public function sync(
        array $items,
        string $modelClass,
        string $keyColumn,
        callable $mapItem,
        Sync $sync,
        string $actor,
        string $emptyMessage
    ): array {
        $counts = $this->emptyCounts();
        $records = [];

        foreach ($items as $item) {
            $mapped = $mapItem($item);

            if (isset($mapped['skip_reason'])) {
                $counts['skipped']++;
                $counts['skip_reasons'][$mapped['skip_reason']]++;

                continue;
            }

            $key = $mapped['key'];
            $attributes = $mapped['attributes'];
            $index = (string) $key;

            if (array_key_exists($index, $records)) {
                $counts['skipped']++;
                $counts['skip_reasons']['duplicate_source_id']++;
            }

            $records[$index] = compact('key', 'attributes');
        }

        if ($items !== [] && $records === []) {
            throw new UnexpectedValueException($emptyMessage);
        }

        foreach ($records as ['key' => $key, 'attributes' => $attributes]) {
            $model = $modelClass::withInactive()->where($keyColumn, $key)->first()
                ?? new $modelClass;

            if (! $model->exists) {
                $model->setAttribute($keyColumn, $key);
            }

            $status = $this->saveModel($model, $attributes, $sync, $actor);
            $counts[$status]++;

            if ($status === 'skipped') {
                $counts['skip_reasons']['unchanged']++;
            }
        }

        $counts['inactivated'] = $this->deactivateMissing(
            $modelClass,
            $keyColumn,
            array_column($records, 'key'),
            $sync,
            $actor
        );
        $counts['skip_reasons'] = $this->formatSkipReasons(
            $counts['skip_reasons']
        );

        return $counts;
    }

    private function saveModel(Model $model, array $attributes, Sync $sync, string $actor): string
    {
        $isInsert = ! $model->exists;

        $model->fill([
            ...$attributes,
            'status' => Status::ACTIVE,
        ]);

        if (! $isInsert && ! $model->isDirty()) {
            return 'skipped';
        }

        if ($isInsert) {
            $model->setAttribute('created_by', $actor);
        }

        $model->fill([
            'updated_by' => $actor,
            'sync_id' => $sync->getKey(),
        ])->save();

        return $isInsert ? 'inserted' : 'updated';
    }

    private function emptyCounts(): array
    {
        return [
            'inserted' => 0,
            'updated' => 0,
            'inactivated' => 0,
            'skipped' => 0,
            'skip_reasons' => [
                'unchanged' => 0,
                'invalid_source_data' => 0,
                'duplicate_source_id' => 0,
                'faculty_not_found' => 0,
            ],
        ];
    }

    private function formatSkipReasons(array $skipReasons): array
    {
        $formatted = [];

        foreach ($skipReasons as $code => $count) {
            if ($count > 0) {
                $formatted[] = compact('code', 'count');
            }
        }

        return $formatted;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deactivateMissing(
        string $modelClass,
        string $key,
        array $activeValues,
        Sync $sync,
        string $actor
    ): int {
        return $modelClass::query()
            ->whereNotIn($key, array_values(array_unique($activeValues)))
            ->update([
                'status' => Status::INACTIVE,
                'updated_by' => $actor,
                'sync_id' => $sync->getKey(),
            ]);
    }
}
