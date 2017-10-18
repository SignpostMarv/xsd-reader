<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Utils;

class UrlUtils
{
    public static function resolveRelativeUrl(
        string $base,
        string $rel
    ) : string {
        if (!$rel) {
            return $base;
        } elseif (
        /* return if already absolute URL */
            parse_url($rel, PHP_URL_SCHEME) !== null ||
            substr($rel, 0, 2) === '//'
        ) {
            return $rel;
        } elseif (
        /* queries and anchors */
            in_array(
                $rel[0],
                [
                    '#',
                    '?'
                ]
            )
        ) {
            return $base.$rel;
        }

        return static::resolveRelativeUrlAfterEarlyChecks($base, $rel);
    }

    protected static function resolveRelativeUrlAfterEarlyChecks(
        string $base,
        string $rel
    ) : string {
        /* fix url file for Windows */
        $base = preg_replace('#^file:\/\/([^/])#', 'file:///\1', $base);

        /*
         * parse base URL and convert to local variables:
         * $scheme, $host, $path
         */
        $parts = parse_url($base);

        return static::resolveRelativeUrlToAbsoluteUrl(
            $rel,
            (
                $rel[0] === '/'
                    ? ''  // destroy path if relative url points to root
                    : ( // remove non-directory element from path
                        isset($parts['path'])
                            ? preg_replace('#/[^/]*$#', '', $parts["path"])
                            : ''
                    )
            ),
            $parts
        );
    }

    protected static function resolveRelativeUrlToAbsoluteUrl(
        string $rel,
        string $path,
        array $parts
    ) : string {
        /* Build absolute URL */
        $abs = '';

        if (isset($parts["host"])) {
            $abs .= $parts['host'];
        }

        if (isset($parts["port"])) {
            $abs .= ":".$parts["port"];
        }

        $abs .= $path."/".$rel;

        /*
        * replace superfluous slashes with a single slash.
        * covers:
        * //
        * /./
        * /foo/../
        */
        $n = 1;
        do {
            $abs = preg_replace(
                '#(?:(?:/\.?/)|(?!\.\.)[^/]+/\.\./)#',
                '/',
                $abs,
                -1,
                $n
            );
        } while ($n > 0);

        if (isset($parts["scheme"])) {
            $abs = $parts["scheme"].'://'.$abs;
        }

        return $abs;
    }

}
