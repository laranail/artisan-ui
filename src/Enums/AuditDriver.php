<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Help;
use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\Enumerator\Attributes\Description;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;

/**
 * Where runs are recorded.
 */
#[Description('Audit storage for command runs.')]
enum AuditDriver: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Log channel'), Help('One structured line per run. The default; needs no migration.')]
    case Log = 'log';

    #[Label('Database'), Help('A row per run. Enables History and rerun. Needs the published migration.')]
    case Database = 'database';

    #[Label('None'), Help('Nothing is recorded.')]
    case None = 'none';

    public static function translationNamespace(): string
    {
        return 'laranail/artisan-ui';
    }
}
