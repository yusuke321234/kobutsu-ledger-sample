<?php

namespace Tests\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Ternary;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 配列の値に条件式（比較・三項演算子）を書かない（docs/conventions/03-coding-style.md）。
 * 先に if で変数を決めてから、その変数を渡す。
 *
 * NG：'is_sold' => $saleData !== null
 * NG：'price' => $price === null ? null : (int)$price
 *
 * @implements Rule<ArrayItem>
 */
class NoConditionInArrayValueRule implements Rule
{
    /** 比較の演算子（値が真偽になる二項演算） */
    private const COMPARISON_CLASS_LIST = [
        BinaryOp\Identical::class,
        BinaryOp\NotIdentical::class,
        BinaryOp\Equal::class,
        BinaryOp\NotEqual::class,
        BinaryOp\Greater::class,
        BinaryOp\GreaterOrEqual::class,
        BinaryOp\Smaller::class,
        BinaryOp\SmallerOrEqual::class,
        BinaryOp\BooleanAnd::class,
        BinaryOp\BooleanOr::class,
    ];



    /**
     * 調べる構文（配列の要素）を返す
     *
     * @return string
     */
    public function getNodeType(): string
    {
        //
        return ArrayItem::class;
    }



    /**
     * キー付きの要素の値が、比較・三項演算子なら指摘する
     *
     * @param Node $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // キーのない要素（リストや分割代入）は対象外
        if ($node->key !== null) {
            // ok.
        } else {
            return [];
        }

        //
        $isCondition = false;
        if ($node->value instanceof Ternary) {
            $isCondition = true;
        } else if (in_array(get_class($node->value), self::COMPARISON_CLASS_LIST, true) === true) {
            $isCondition = true;
        } else {
            // no action.
        }

        //
        $errorList = [];
        if ($isCondition === true) {
            $errorList[] = RuleErrorBuilder::message('配列の値に条件式（比較・三項演算子）を書かず、先に if で変数を決めてから渡してください（03-coding-style）。')
                ->identifier('kobucho.conditionInArrayValue')
                ->build();
        } else {
            // no action.
        }
        return $errorList;
    }
}
