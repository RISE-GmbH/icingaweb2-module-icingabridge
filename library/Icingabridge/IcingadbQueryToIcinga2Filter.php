<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later
// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

namespace Icinga\Module\Icingabridge;

use InvalidArgumentException;
use ipl\Stdlib\Filter\All;
use ipl\Stdlib\Filter\Any;
use ipl\Stdlib\Filter\Chain;
use ipl\Stdlib\Filter\Condition;
use ipl\Stdlib\Filter\Equal;
use ipl\Stdlib\Filter\GreaterThan;
use ipl\Stdlib\Filter\GreaterThanOrEqual;
use ipl\Stdlib\Filter\LessThan;
use ipl\Stdlib\Filter\LessThanOrEqual;
use ipl\Stdlib\Filter\Like;
use ipl\Stdlib\Filter\None;
use ipl\Stdlib\Filter\Rule;
use ipl\Stdlib\Filter\Unequal;
use ipl\Stdlib\Filter\Unlike;
use ipl\Web\Filter\QueryString;

class IcingadbQueryToIcinga2Filter
{
    private array $vars = [];
    private int $varNum = 0;

    private array $fieldMap = [
        'host.name' => 'host.name',
        'host.display_name' => 'host.display_name',
        'host.address' => 'host.address',
        'host.address6' => 'host.address6',
        'host.checkcommand_name' => 'host.check_command',
        'host.zone_name' => 'host.zone',
        'host.state.hard_state' => 'host.state',
        'host.state.soft_state' => 'host.state',
        'host.state.state_type' => 'host.state_type',
        'host.state.is_problem' => 'host.problem',
        'host.state.is_flapping' => 'host.flapping',

        'service.name' => 'service.name',
        'service.display_name' => 'service.display_name',
        'service.host.name' => 'host.name',
        'service.checkcommand_name' => 'service.check_command',
        'service.zone_name' => 'service.zone',
        'service.state.hard_state' => 'service.state',
        'service.state.soft_state' => 'service.state',
        'service.state.state_type' => 'service.state_type',
        'service.state.is_problem' => 'service.problem',
        'service.state.is_flapping' => 'service.flapping',
    ];

    public function fromQueryString(string $queryString): array
    {
        $this->vars = [];
        $this->varNum = 0;

        $rule = QueryString::parse($queryString);

        return [
            'filter' => $this->compile($rule),
            'filter_vars' => $this->vars
        ];
    }

    public function fromRule(Rule $rule): array
    {
        $this->vars = [];
        $this->varNum = 0;

        return [
            'filter' => $this->compile($rule),
            'filter_vars' => $this->vars
        ];
    }

    private function compile(Rule $rule): string
    {
        if ($rule instanceof All) {
            return $this->compileChain($rule, '&&');
        }

        if ($rule instanceof Any) {
            return $this->compileChain($rule, '||');
        }

        if ($rule instanceof None) {
            return '!(' . $this->compileChain($rule, '||') . ')';
        }

        if ($rule instanceof Condition) {
            return $this->compileCondition($rule);
        }

        throw new InvalidArgumentException('Unsupported filter rule: ' . get_class($rule));
    }

    private function compileChain(Chain $chain, string $op): string
    {
        $parts = [];

        foreach ($chain as $child) {
            $parts[] = '(' . $this->compile($child) . ')';
        }

        if (empty($parts)) {
            return 'true';
        }

        return implode(' ' . $op . ' ', $parts);
    }

    private function compileCondition(Condition $rule): string
    {
        $column = $rule->getColumn();

        if (is_array($column)) {
            throw new InvalidArgumentException('Array columns are not supported yet');
        }

        if ($column === 'hostgroup.name' || $column === 'hostgroups.name') {
            return $this->compileGroupCondition($rule, 'host.groups');
        }

        if ($column === 'servicegroup.name' || $column === 'servicegroups.name') {
            return $this->compileGroupCondition($rule, 'service.groups');
        }

        if (preg_match('/^host\.vars\.(.+)$/', $column, $m)) {
            return $this->compileNormalCondition($rule, 'host.vars.' . $this->quoteVarPath($m[1]));
        }

        if (preg_match('/^service\.vars\.(.+)$/', $column, $m)) {
            return $this->compileNormalCondition($rule, 'service.vars.' . $this->quoteVarPath($m[1]));
        }

        if ($column === 'host.state.is_acknowledged') {
            return $this->compileNumericBoolean($rule, 'host.acknowledgement');
        }

        if ($column === 'service.state.is_acknowledged') {
            return $this->compileNumericBoolean($rule, 'service.acknowledgement');
        }

        if ($column === 'host.state.in_downtime') {
            return $this->compileNumericBoolean($rule, 'host.downtime_depth');
        }

        if ($column === 'service.state.in_downtime') {
            return $this->compileNumericBoolean($rule, 'service.downtime_depth');
        }

        if ($column === 'host.state.is_handled') {
            return $this->compileHandledCondition($rule, 'host');
        }

        if ($column === 'service.state.is_handled') {
            return $this->compileHandledCondition($rule, 'service');
        }

        if ($column === 'host.state.is_reachable') {
            return $this->compileNumericBoolean($rule, 'host.last_reachable');
        }

        // Icinga 2 API has no native service.state.is_reachable equivalent.
        // Skip it by compiling it to a neutral filter.
        if ($column === 'service.state.is_reachable') {
            return $this->compileNumericBoolean($rule, 'service.last_reachable');
        }

        if (!isset($this->fieldMap[$column])) {
            throw new InvalidArgumentException('No Icinga 2 mapping for field: ' . $column);
        }

        return $this->compileNormalCondition($rule, $this->fieldMap[$column]);
    }

    private function compileGroupCondition(Condition $rule, string $icingaField): string
    {
        $value = $rule->getValue();
        $var = $this->addVar($value);

        if ($rule instanceof Equal) {
            return sprintf('%s in %s', $var, $icingaField);
        }

        if ($rule instanceof Unequal) {
            return sprintf('!(%s in %s)', $var, $icingaField);
        }

        if ($rule instanceof Like) {
            if (is_string($value) && strpos($value, '*') !== false) {
                return sprintf(
                    'len(filter(g => match(%s, g), %s)) > 0',
                    $var,
                    $icingaField
                );
            }

            return sprintf('%s in %s', $var, $icingaField);
        }

        if ($rule instanceof Unlike) {
            if (is_string($value) && strpos($value, '*') !== false) {
                return sprintf(
                    'len(filter(g => match(%s, g), %s)) == 0',
                    $var,
                    $icingaField
                );
            }

            return sprintf('!(%s in %s)', $var, $icingaField);
        }

        throw new InvalidArgumentException('Unsupported operator for group condition: ' . get_class($rule));
    }

    private function compileNormalCondition(Condition $rule, string $icingaField): string
    {
        $value = $rule->getValue();
        $var = $this->addVar($this->normalizeValue($value));

        if ($rule instanceof Equal) {
            return sprintf('%s == %s', $icingaField, $var);
        }

        if ($rule instanceof Unequal) {
            return sprintf('%s != %s', $icingaField, $var);
        }

        if ($rule instanceof Like) {
            if (is_string($value) && strpos($value, '*') !== false) {
                return sprintf('match(%s, %s)', $var, $icingaField);
            }

            return sprintf('%s == %s', $icingaField, $var);
        }

        if ($rule instanceof Unlike) {
            if (is_string($value) && strpos($value, '*') !== false) {
                return sprintf('!match(%s, %s)', $var, $icingaField);
            }

            return sprintf('%s != %s', $icingaField, $var);
        }

        if ($rule instanceof GreaterThan) {
            return sprintf('%s > %s', $icingaField, $var);
        }

        if ($rule instanceof GreaterThanOrEqual) {
            return sprintf('%s >= %s', $icingaField, $var);
        }

        if ($rule instanceof LessThan) {
            return sprintf('%s < %s', $icingaField, $var);
        }

        if ($rule instanceof LessThanOrEqual) {
            return sprintf('%s <= %s', $icingaField, $var);
        }

        throw new InvalidArgumentException('Unsupported condition: ' . get_class($rule));
    }

    private function compileNumericBoolean(Condition $rule, string $field): string
    {
        $wantTrue = $this->toBool($rule->getValue());

        if ($rule instanceof Unequal || $rule instanceof Unlike) {
            $wantTrue = !$wantTrue;
        }

        return $wantTrue
            ? sprintf('%s != 0', $field)
            : sprintf('%s == 0', $field);
    }

    private function compileHandledCondition(Condition $rule, string $object): string
    {
        $wantTrue = $this->toBool($rule->getValue());

        if ($rule instanceof Unequal || $rule instanceof Unlike) {
            $wantTrue = !$wantTrue;
        }

        if ($wantTrue) {
            return sprintf(
                '(%s.acknowledgement != 0 || %s.downtime_depth > 0)',
                $object,
                $object
            );
        }

        return sprintf(
            '(%s.acknowledgement == 0 && %s.downtime_depth == 0)',
            $object,
            $object
        );
    }

    private function addVar($value): string
    {
        $name = 'v' . (++$this->varNum);
        $this->vars[$name] = $value;

        return $name;
    }

    private function normalizeValue($value)
    {
        if (is_string($value)) {
            $lower = strtolower($value);

            if (in_array($lower, ['y', 'yes', 'true'], true)) {
                return 1;
            }

            if (in_array($lower, ['n', 'no', 'false'], true)) {
                return 0;
            }

            if (is_numeric($value)) {
                return $value + 0;
            }
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1', 'y', 'yes', 'true'], true);
    }

    private function quoteVarPath(string $path): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $path)) {
            return $path;
        }

        return '[' . json_encode($path, JSON_UNESCAPED_SLASHES) . ']';
    }
}