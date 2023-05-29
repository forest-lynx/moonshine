<?php

declare(strict_types=1);

namespace MoonShine\Traits\Fields;

use InvalidArgumentException;

trait ShowWhen
{
    public array $operators = [
        '=', '<', '>', '<=', '>=', '!=', 'in', 'not in'
    ];

    public array $arrayOperators = [
        'in', 'not in'
    ];

    public bool $showWhenState = false;

    protected array $showWhenCondition = [];

    public function hasShowWhen(): bool
    {
        return $this->showWhenState;
    }

    public function showWhenCondition(): array
    {
        return $this->showWhenCondition;
    }

    public function showWhen(string $column, $operator = null, $value = null): static
    {
        [$column, $value, $operator] = $this->makeCondition(...func_get_args());

        $this->showWhenState = true;

        $this->showWhenCondition = [
            'showField' => $this->name(),
            'changeField' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    private function makeCondition(string $column, $operator = null, $value = null): array
    {
        return [$column, ...$this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        )];
    }

    private function prepareValueAndOperator($value, $operator, $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        if(in_array($operator, $this->arrayOperators) && !is_array($value)) {
            throw new InvalidArgumentException('Illegal operator and value combination. Value must be array type');
        }

        return [$value, $operator];
    }

    private function invalidOperatorAndValue($operator, $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) &&
            ! in_array($operator, ['=', '<>', '!=']);
    }

    private function invalidOperator($operator): bool
    {
        return ! is_string($operator) || (! in_array(strtolower($operator), $this->operators, true));
    }
}
