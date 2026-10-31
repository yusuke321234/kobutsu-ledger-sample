<?php

namespace Tests\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Repository の1件の取得で、count() で件数を確かめてから first() で取り直さない（docs/conventions/07-architecture.md）。
 * first() の1回で取り、見つからなければ null になるので ?->toArray() で返す。
 *
 * NG：if ($query->count() > 0) { $data = $query->first()->toArray(); }
 *
 * @implements Rule<Greater>
 */
class NoCountThenFirstRule implements Rule
{
    /**
     * 調べる構文（「より大きい」の比較）を返す
     *
     * @return string
     */
    public function getNodeType(): string
    {
        //
        return Greater::class;
    }



    /**
     * Repository の中の「->count() > 0」を指摘する
     *
     * @param Node $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Repository 以外は対象外（件数を数える処理そのものは許す）
        if (str_contains($scope->getFile(), '/app/Repositories/') === true) {
            // ok.
        } else {
            return [];
        }

        //
        $isCountCall = $node->left instanceof MethodCall
            && $node->left->name instanceof Identifier
            && $node->left->name->toString() === 'count';
        $isZero = $node->right instanceof Int_ && $node->right->value === 0;

        //
        $errorList = [];
        if ($isCountCall === true && $isZero === true) {
            $errorList[] = RuleErrorBuilder::message('count() で件数を確かめてから first() で取り直さず、first() の1回で取って ?->toArray() で返してください（07-architecture）。')
                ->identifier('kobucho.countThenFirst')
                ->build();
        } else {
            // no action.
        }
        return $errorList;
    }
}
