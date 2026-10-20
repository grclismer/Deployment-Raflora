<?php
// Shared by scratch/check_php_ifs.php and scratch/temp.php: counts alternative-syntax "if (...):"
// blocks and "endif" tokens in compiled PHP using PHP's tokenizer.

/**
 * @return array{0: int, 1: int} [if blocks, endif tokens]
 */
function count_php_ifs(string $php): array
{
    $tokens = token_get_all($php);
    $ifs = 0;
    $endifs = 0;

    foreach ($tokens as $i => $token) {
        if (!is_array($token)) {
            continue;
        }
        if ($token[0] === T_ENDIF) {
            $endifs++;
            continue;
        }
        if ($token[0] !== T_IF) {
            continue;
        }

        // Skip the condition "( ... )", then an alternative-syntax if is followed by ":".
        $depth = 0;
        for ($j = $i + 1; $j < count($tokens); $j++) {
            $t = $tokens[$j];
            if ($t === '(') {
                $depth++;
            } elseif ($t === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }
        for ($j++; $j < count($tokens) && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT], true); $j++);
        if (($tokens[$j] ?? null) === ':') {
            $ifs++;
        }
    }

    return [$ifs, $endifs];
}
