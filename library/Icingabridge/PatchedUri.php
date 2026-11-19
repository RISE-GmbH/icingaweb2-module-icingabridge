<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

declare(strict_types=1);

namespace Icinga\Module\Icingabridge;

/**
 * Preserves raw query strings containing Icinga filter operators
 * that would otherwise be percent-encoded by Guzzle's Uri class.
 *
 * @see https://github.com/guzzle/guzzle/issues/1696
 */
class PatchedUri extends \GuzzleHttp\Psr7\Uri
{
    private ?string $rawQuery = null;

    public function withQuery($query): \Psr\Http\Message\UriInterface
    {
        if (!is_string($query)) {
            throw new \InvalidArgumentException('Query must be a string');
        }

        $new = clone $this;
        $new->rawQuery = $query;

        return $new;
    }

    public function getQuery(): string
    {
        return $this->rawQuery ?? parent::getQuery();
    }

    public function __toString(): string
    {
        return self::composeComponents(
            $this->getScheme(),
            $this->getAuthority(),
            $this->getPath(),
            $this->getQuery(),
            $this->getFragment()
        );
    }
}