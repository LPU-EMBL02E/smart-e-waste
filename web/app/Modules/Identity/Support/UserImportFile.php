<?php

namespace App\Modules\Identity\Support;

/**
 * Reads the admin's CSV file into rows. API_Design.md §7.6 ("CSV import"), P36.
 *
 * This only checks the file's shape. AccountService::import() checks what the
 * rows say. A file with a shape problem is never imported.
 *
 * Files come from spreadsheets, so this expects what Excel writes: "CSV UTF-8"
 * starts with a byte-order mark, and plain "CSV" is often not UTF-8 at all, which
 * would break a name like Peña.
 */
final class UserImportFile
{
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * Rows and errors are both keyed by row number, counting the header as row 1.
     *
     * @param  list<string>  $columns  the names the header row must have, in any order
     * @return array{rows: array<int, array<string, string>>, errors: array<int, list<string>>}
     */
    public static function read(string $path, array $columns): array
    {
        $handle = fopen($path, 'r');
        $rowNumber = 0;
        $header = null;
        $rows = [];
        $errors = [];

        // The empty escape character is what RFC 4180 CSV means, and PHP 8.4
        // deprecates relying on the default.
        while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rowNumber++;

            if ($cells === [null]) {
                continue;
            }

            if ($header === null) {
                $header = self::readHeader($cells);

                if (! self::sameColumns($header, $columns)) {
                    fclose($handle);

                    return ['rows' => [], 'errors' => [
                        $rowNumber => ['The first row must name the columns: '.implode(', ', $columns).'.'],
                    ]];
                }

                continue;
            }

            if (count($cells) !== count($columns)) {
                $errors[$rowNumber][] = 'This row should have '.count($columns).' columns and has '.count($cells).'.';
            } elseif (! mb_check_encoding(implode('', $cells), 'UTF-8')) {
                $errors[$rowNumber][] = 'This row is not valid UTF-8. Save the file as "CSV UTF-8" and upload it again.';
            } else {
                $rows[$rowNumber] = array_combine($header, $cells);
            }
        }

        fclose($handle);

        if ($rows === [] && $errors === []) {
            $errors[1][] = 'The file has no students in it.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  list<string|null>  $cells
     * @return list<string>
     */
    private static function readHeader(array $cells): array
    {
        $cells[0] = str_replace(self::BYTE_ORDER_MARK, '', (string) $cells[0]);

        return array_map(fn (?string $cell): string => mb_strtolower(trim((string) $cell)), $cells);
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $columns
     */
    private static function sameColumns(array $header, array $columns): bool
    {
        sort($header);
        sort($columns);

        return $header === $columns;
    }
}
