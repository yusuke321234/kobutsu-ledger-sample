<?php

namespace Kobucho\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * アロー関数は fn() と書き、fn と ( のあいだに空白を入れない（docs/conventions/03-coding-style.md）。
 * 一般的な規約（PER Coding Style）は fn () だが、このプロジェクトは fn() にそろえる。
 */
class ArrowFunctionSpacingSniff implements Sniff
{
    /**
     * 調べる字句（fn）を返す
     *
     * @return array
     */
    public function register()
    {
        //
        return [T_FN];
    }



    /**
     * fn の直後に空白があれば指摘する（phpcbf で自動修正もできる）
     *
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return void
     */
    public function process(File $phpcsFile, int $stackPtr)
    {
        $tokenList = $phpcsFile->getTokens();
        $nextPtr = $stackPtr + 1;

        //
        if (isset($tokenList[$nextPtr]) === true && $tokenList[$nextPtr]['code'] === T_WHITESPACE) {
            $fix = $phpcsFile->addFixableError('アロー関数は fn() と書き、fn と ( のあいだに空白を入れないでください（03-coding-style）。', $stackPtr, 'SpaceAfterFn');
            if ($fix === true) {
                $phpcsFile->fixer->replaceToken($nextPtr, '');
            } else {
                // no action.
            }
        } else {
            // no action.
        }
    }
}
