<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Throwable;

use function is_int;
use function is_scalar;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strtolower;

/**
 * Does this collation fold values the key gate needs to stay distinct? Asked of the server, not of the name.
 *
 * "_ai_ci" is only the usual spelling. Forks, older servers and site-local collations spell it differently, and a
 * gate that decides by substring is wrong exactly where it matters most. So the server is asked to compare two
 * values that differ only in case, in that collation, and its answer decides. The name is used afterwards only to
 * widen the answer (a hypothetical accent-insensitive, case-sensitive collation), never to narrow it.
 *
 * Binary collations are not probed: they compare byte for byte by construction, and `COLLATE binary` is a syntax
 * error on MySQL ("binary" is a reserved word), so probing them would turn a correct table into an unreadable one.
 *
 * Unreadable means unreadable. If the collation is not in information_schema.COLLATIONS, or the comparison cannot be
 * run, this raises instead of answering: an exception becomes exit 2 ("the database could not be read"), while a
 * swallowed error would become exit 0 - a gate reporting "nothing to worry about" over a table set up to fold.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CollationFoldingProbe
{
    /**
     * @throws RuntimeException when the server cannot answer for this collation
     */
    public static function folds(Connection $connection, string $collation, ExactCollationSupport $support): bool
    {
        $name = strtolower($collation);

        // Binary: every comparison is byte-exact, so an inherited column cannot fold anything.
        if ($support->accepts($name) || str_ends_with($name, '_bin')) {
            return false;
        }

        $charset = self::charsetOf($connection, $name);

        return self::caseInsensitive($connection, $charset, $name)
            || str_contains($name, '_ci')
            || str_contains($name, '_ai');
    }

    /**
     * The collation's character set, taken from the server's own catalogue. That lookup is also what makes the two
     * names safe to write into the probe below: they are the catalogue's spelling, re-checked against [a-z0-9_].
     *
     * @throws RuntimeException
     */
    private static function charsetOf(Connection $connection, string $name): string
    {
        try {
            $row = $connection->fetchAssociative(
                'SELECT COLLATION_NAME AS collation_name, CHARACTER_SET_NAME AS charset_name FROM information_schema.COLLATIONS WHERE LOWER(COLLATION_NAME) = ?',
                [$name],
            );
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Cannot read information_schema.COLLATIONS for "%s": whether that collation folds case or accents is unknown, so no table default can be asserted. Grant SELECT on information_schema to the connection used by the check.', $name), 0, $e);
        }

        $charset = $row === false ? null : ($row['charset_name'] ?? null);
        if (!is_scalar($charset) || preg_match('/^[a-z0-9_]+$/', strtolower((string) $charset)) !== 1 || preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException(sprintf('Collation "%s" is not in information_schema.COLLATIONS on this server: whether it folds case or accents cannot be asserted.', $name));
        }

        return strtolower((string) $charset);
    }

    /**
     * @throws RuntimeException
     */
    private static function caseInsensitive(Connection $connection, string $charset, string $collation): bool
    {
        $sql = sprintf("SELECT CONVERT('a' USING %s) COLLATE %s = CONVERT('A' USING %s) AS folds", $charset, $collation, $charset);

        try {
            $folds = $connection->fetchOne($sql);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('The server could not compare two values in "%s": whether that collation folds case is unknown, so no table default can be asserted.', $collation), 0, $e);
        }

        // fetchOne() answers false when the statement produced no row at all. Reading that as 0, and 0 as "this
        // collation does not fold", is exactly how a gate ends up exiting 0 over a question it never got answered.
        $answer = is_int($folds) || is_string($folds) ? (string) $folds : '';
        if ($answer !== '0' && $answer !== '1') {
            throw new RuntimeException(sprintf('The server returned no usable answer when asked whether "%s" folds case, so no table default can be asserted.', $collation));
        }

        return $answer === '1';
    }
}
