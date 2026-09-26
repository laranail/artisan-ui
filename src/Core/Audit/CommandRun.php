<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Audit;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Simtabi\Laranail\Enumerator\Casts\AsEnum;
use Illuminate\Database\Eloquent\MassPrunable;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * One recorded run, in `laranail_artisan_ui_runs`.
 *
 * The key is the run id the executor generated, so the database row, the log line and the
 * events all carry the same identifier.
 *
 * Pruned by the framework: schedule `model:prune --model="Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun"`.
 * `audit.retention_days` decides what is old.
 *
 * @property string $id
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string $command
 * @property array<array-key, mixed> $arguments
 * @property array<array-key, mixed> $options
 * @property CommandRisk $risk
 * @property RunStatus $status
 * @property int|null $exit_code
 * @property string|null $output
 * @property bool $output_truncated
 * @property string|null $error
 * @property int|null $duration_ms
 * @property string|null $ip
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CommandRun extends Model
{
    use MassPrunable;

    public const string TABLE = 'laranail_artisan_ui_runs';

    public $incrementing = false;

    protected $table = self::TABLE;

    protected $keyType = 'string';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return app(ArtisanUIConfig::class)->auditConnection() ?? parent::getConnectionName();
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where(
            'created_at',
            '<',
            Carbon::now()->subDays(app(ArtisanUIConfig::class)->retentionDays()),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arguments'        => 'array',
            'options'          => 'array',
            'risk'             => AsEnum::of(CommandRisk::class),
            'status'           => AsEnum::of(RunStatus::class),
            'exit_code'        => 'integer',
            'output_truncated' => 'boolean',
            'duration_ms'      => 'integer',
            'started_at'       => 'datetime',
            'finished_at'      => 'datetime',
        ];
    }
}
