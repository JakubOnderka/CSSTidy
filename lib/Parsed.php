<?php
/**
 * CSSTidy - CSS Parser and Optimiser
 *
 * CSS Optimising Class
 * This class optimises CSS data generated by csstidy.
 *
 * Copyright 2005, 2006, 2007 Florian Schmitz
 *
 * This file is part of CSSTidy.
 *
 *   CSSTidy is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Lesser General Public License as published by
 *   the Free Software Foundation; either version 2.1 of the License, or
 *   (at your option) any later version.
 *
 *   CSSTidy is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Lesser General Public License for more details.
 *
 *   You should have received a copy of the GNU Lesser General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @package csstidy
 * @author Florian Schmitz (floele at gmail dot com) 2005-2007
 * @author Brett Zamir (brettz9 at yahoo dot com) 2007
 * @author Nikolay Matsievsky (speed at webo dot name) 2009-2010
 * @author Jakub Onderka (acci at acci dot cz) 2011
 */
namespace CSSTidy;

class Parsed
{
    /** @var array */
    public $css = array();

    /** @var array */
    public $tokens = array();

    /** @var string */
    public $charset = '';

    /** @var array */
    public $import = array();

    /** @var string */
    public $namespace = '';

    /** @var int */
    protected $mergeSelectors;

    /**
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->mergeSelectors = $configuration->getMergeSelectors();
    }

    /**
     * Adds a token to $this->tokens
     * @param int $type
     * @param string $data
     * @return void
     */
    public function addToken($type, $data)
    {
        $this->tokens[] = array($type, ($type === CSSTidy::COMMENT) ? $data : trim($data));
    }

    /**
     * Adds a property with value to the existing CSS code
     * @param string $media
     * @param string $selector
     * @param string $property
     * @param string $newValue
     * @access private
     * @version 1.2
     */
    public function addProperty($media, $selector, $property, $newValue)
    {
        if (trim($newValue) == '') {
            return;
        }

        if (isset($this->css[$media][$selector][$property])) {
            if ((CSSTidy::isImportant($this->css[$media][$selector][$property]) && CSSTidy::isImportant($newValue)) || !CSSTidy::isImportant($this->css[$media][$selector][$property])) {
                $this->css[$media][$selector][$property] = trim($newValue);
            }
        } else {
            $this->css[$media][$selector][$property] = trim($newValue);
        }
    }

    /**
     * Adds CSS to an existing media/selector
     * @param string $media
     * @param string $selector
     * @param array $cssToAdd
     * @version 1.1
     */
    public function mergeCssBlocks($media, $selector, array $cssToAdd)
    {
        foreach ($cssToAdd as $property => $value) {
            $this->addProperty($media, $selector, $property, $value);
        }
    }

    /**
     * Start a new media section.
     * Check if the media is not already known,
     * else rename it with extra spaces
     * to avoid merging
     *
     * @param string $media
     * @return string
     */
    public function newMediaSection($media)
    {
        // if the last @media is the same as this
        // keep it
        if (!$this->css || !is_array($this->css) || empty($this->css)) {
            return $media;
        }

        end($this->css);
        list($at,) = each($this->css);

        if ($at == $media) {
            return $media;
        }

        while (isset($this->css[$media])) {
            if (is_numeric($media)) {
                $media++;
            } else {
                $media .= " ";
            }
        }
        return $media;
    }

    /**
     * Start a new selector.
     * If already referenced in this media section,
     * rename it with extra space to avoid merging
     * except if merging is required,
     * or last selector is the same (merge siblings)
     *
     * never merge @font-face
     *
     * @param string $media
     * @param string $selector
     * @return string
     */
    public function newSelector($media, $selector)
    {
        $selector = trim($selector);
        if (strncmp($selector, "@font-face", 10) != 0) {
            if ($this->mergeSelectors != Configuration::DO_NOT_CHANGE) {
                return $selector;
            }

            if (!$this->css || !isset($this->css[$media]) || !$this->css[$media]) {
                return $selector;
            }

            // if last is the same, keep it
            end($this->css[$media]);
            list($sel,) = each($this->css[$media]);

            if ($sel == $selector) {
                return $selector;
            }
        }

        while (isset($this->css[$media][$selector])) {
            $selector .= " ";
        }

        return $selector;
    }

    /**
     * Start a new property
     * If already references in this selector,
     * rename it with extra space to avoid override
     *
     * @param string $media
     * @param string $selector
     * @param string $property
     * @return string
     */
    public function newProperty($media, $selector, $property)
    {
        if (!$this->css || !isset($this->css[$media][$selector]) || !$this->css[$media][$selector]) {
            return $property;
        }

        while (isset($this->css[$media][$selector][$property])) {
            $property .= ' ';
        }

        return $property;
    }
}