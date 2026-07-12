<?php

namespace App\Services\Reports;

/**
 * Thrown when a report cannot be generated because required data is missing
 * (e.g. an unconfigured fee rate, no signatory, an empty roster). Carries a
 * user-facing message; controllers catch it and flash it back rather than
 * letting it surface as a 500.
 */
class ReportPreconditionException extends \RuntimeException
{
}
