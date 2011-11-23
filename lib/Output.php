<?php
/**
 * CSSTidy - CSS Parser and Optimiser
 *
 * CSS Output class
 * This class generated output CSS from parsed.
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
 * @author Cedric Morin (cedric at yterium dot com) 2010
 * @author Jakub Onderka (acci at acci dot cz) 2011
 */
namespace CSSTidy;
/**
 * CSS Printing class
 *
 * This class prints CSS data generated by csstidy.
 *
 * @package csstidy
 * @author Florian Schmitz (floele at gmail dot com) 2005-2006
 * @version 1.0.1
 */
class Output
{
    const AT_START = 1,
        AT_END = 2,
        SEL_START = 3,
        SEL_END = 4,
        PROPERTY = 5,
        VALUE = 6,
        COMMENT = 7,
        LINE_AT = 8;
    
    const INPUT = 'input',
        OUTPUT = 'output';

    /**
     * Saves the input CSS string
     * @var string
     */
    protected $inputCss;

    /**
     * Saves the formatted CSS string
     * @var string
     */
    protected $outputCss;

    /**
     * Saves the formatted CSS string (plain text)
     * @var string
     */
    protected $outputCssPlain;

    /** @var Configuration */
    protected $configuration;

    /** @var Logger */
    protected $logger;

    /** @var Parsed */
    protected $parsed;

    /** @var array */
    protected $tokens = array();

    /**
     * @param Configuration $configuration
     * @param Logger $logger
     * @param string $inputCss
     * @param Parsed $parsed
     */
    public function __construct(Configuration $configuration, Logger $logger, $inputCss, Parsed $parsed)
    {
        $this->configuration = $configuration;
        $this->logger = $logger;
        $this->inputCss = $inputCss;
        $this->parsed = $parsed;
    }

    /**
     * Returns the CSS code as plain text
     * @param string $defaultMedia default @media to add to selectors without any @media
     * @return string
     * @access public
     * @version 1.0
     */
    public function plain($defaultMedia = null)
    {
        $this->generate(true, $defaultMedia);
        return $this->outputCssPlain;
    }

    /**
     * Returns the formatted CSS code
     * @param string $defaultMedia default @media to add to selectors without any @media
     * @return string
     * @access public
     * @version 1.0
     */
    public function formatted($defaultMedia = null)
    {
        $this->generate(false, $defaultMedia);
        return $this->outputCss;
    }

    /**
     * Returns the formatted CSS code to make a complete webpage
     * @param bool $externalCss indicates whether styles to be attached internally or as an external stylesheet
     * @param string $title title to be added in the head of the document
     * @return string
     * @access public
     * @version 1.4
     */
    public function formattedPage($externalCss = true, $title = '')
    {
        if ($externalCss) {
            $css = "\n\n<style type=\"text/css\">\n";
            $cssParsed = file_get_contents('cssparsed.css');
            $css .= $cssParsed; // Adds an invisible BOM or something, but not in css_optimised.php
            $css .= "\n\n</style>";
        } else {
            $css = "\n\n" . '<link rel="stylesheet" type="text/css" href="cssparsed.css">';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <title>$title</title>
        $css
    </head>
    <body>
        <code id="copytext">{$this->formatted()}</code>
    </body>
</html>
HTML;
    }

    /**
     * Get compression ratio
     * @access public
     * @return float
     * @version 1.2
     */
    public function getRatio()
    {
        $input = $this->size(self::INPUT);
        $output = $this->size(self::OUTPUT);

        return round(($input - $output) / $input, 3) * 100;
    }

    /**
     * Get difference between the old and new code in bytes and prints the code if necessary.
     * @access public
     * @return string
     * @version 1.1
     */
    public function getDiff()
    {
        if (!$this->outputCssPlain) {
            $this->formatted();
        }

        $diff = strlen($this->outputCssPlain) - strlen($this->inputCss);

        if ($diff > 0) {
            return '+' . $diff;
        } else if ($diff == 0) {
            return '+-' . $diff;
        }

        return $diff;
    }

    /**
     * Get the size of either input or output CSS in KB
     * @param string $loc default is "output"
     * @return float
     * @version 1.0
     */
    public function size($loc = self::OUTPUT)
    {
        if ($loc === self::OUTPUT && !$this->outputCss) {
            $this->formatted();
        }

        if ($loc === self::INPUT) {
            return (strlen($this->inputCss) / 1000);
        } else {
            return (strlen($this->outputCssPlain) / 1000);
        }
    }

    /**
     * @param string $loc
     * @param int $level
     * @return float
     */
    public function gzippedSize($loc = self::OUTPUT, $level = -1)
    {
        if ($loc === self::OUTPUT && !$this->outputCss) {
            $this->formatted();
        }

        if ($loc === self::INPUT) {
            return (strlen(gzencode($this->inputCss, $level)) / 1000);
        } else {
            return (strlen(gzencode($this->outputCssPlain, $level)) / 1000);
        }
    }

    /**
     * Returns the formatted CSS Code and saves it into $this->output_css and $this->output_css_plain
     * @param bool $plain plain text or not
     * @param string $defaultMedia default @media to add to selectors without any @media
     * @version 2.0
     */
    protected function generate($plain = false, $defaultMedia = null)
    {
        if ($this->outputCss && $this->outputCssPlain) {
            return;
        }

        $this->convertRawCss($defaultMedia);

        $template = $this->configuration->getTemplate();

        if ($this->configuration->getAddTimestamp()) {
            array_unshift(
                $this->tokens,
                array(self::COMMENT, ' CSSTidy ' . CSSTidy::getVersion() . ': ' . date('r') . ' ')
            );
        }

        if (!$plain) {
            $this->outputCss = $this->tokensToCss($template, false);
        }

        $template = $template->getWithoutHtml();
        $this->outputCssPlain = $this->tokensToCss($template, true);

        // If using spaces in the template, don't want these to appear in the plain output
        $this->outputCssPlain = str_replace('&#160;', '', $this->outputCssPlain);
    }

    /**
     * @param Template $template
     * @param bool $plain
     * @return string
     */
    protected function tokensToCss(Template $template, $plain)
    {
        $output = '';

        if (!empty($this->parsed->charset)) {
            // After '@charset' must be single space!
            $output .= "{$template->beforeAtRule}@charset {$template->beforeValue}{$this->parsed->charset}{$template->afterValueWithSemicolon}";
        }

        foreach ($this->parsed->import as $import) {
            $importValue = $import->getValue();
            $replaced = $this->removeUrl($importValue);
            if ($replaced !== $importValue) {
                $importValue = $replaced;
                $this->logger->log('Optimised @import: Removed "url("', Logger::INFORMATION);
            }

            $output .= "{$template->beforeAtRule}@import{$template->beforeValue}{$importValue}{$template->afterValueWithSemicolon}";
        }

        foreach ($this->parsed->namespace as $namespace) {
            $replaced = $this->removeUrl($namespace);
            if ($replaced !== $namespace) {
                $namespace = $replaced;
                $this->logger->log('Optimised @namespace: Removed "url("', Logger::INFORMATION);
            }
            $output .= "{$template->beforeAtRule}@namespace{$template->beforeValue}{$namespace}{$template->afterValueWithSemicolon}";
        }

        $output .= $template->lastLineInAtRule;
        $inAtOut = '';
        $out = &$output;

        foreach ($this->tokens as $key => $token) {
            switch ($token[0]) {
                case self::PROPERTY:
                    if ($this->configuration->getCaseProperties() === Configuration::LOWERCASE) {
                        $token[1] = strtolower($token[1]);
                    } else if ($this->configuration->getCaseProperties() === Configuration::UPPERCASE) {
                        $token[1] = strtoupper($token[1]);
                    }
                    $out .= $template->beforeProperty . $this->htmlsp($token[1], $plain) . ':' . $template->beforeValue;
                    break;

                case self::VALUE:
                    $out .= $this->htmlsp($token[1], $plain);
                    $nextToken = $this->seekNoComment($key);
                    if (($nextToken === self::SEL_END || $nextToken === self::AT_END) && $this->configuration->getRemoveLastSemicolon()) {
                        $out .= str_replace(';', '', $template->afterValueWithSemicolon);
                    } else {
                        $out .= $template->afterValueWithSemicolon;
                    }
                    break;

                case self::SEL_START:
                    if ($this->configuration->getLowerCaseSelectors()) {
                        $token[1] = strtolower($token[1]);
                    }
                    $out .= $template->beforeSelector . $this->htmlsp($token[1], $plain) . $template->selectorOpeningBracket;
                    break;

                case self::SEL_END:
                    $out .= $template->selectorClosingBracket;
                    if ($this->seekNoComment($key) !== self::AT_END) {
                        $out .= $template->spaceBetweenBlocks;
                    }
                    break;

                case self::AT_START:
                    $out .= $template->beforeAtRule . $this->htmlsp($token[1], $plain) . $template->bracketAfterAtRule;
                    $out = & $inAtOut;
                    break;

                case self::AT_END:
                    $out = & $output;
                    $out .= $template->indentInAtRule . str_replace("\n", "\n" . $template->indentInAtRule, $inAtOut);
                    $inAtOut = '';
                    $out .= $template->atRuleClosingBracket;
                    break;

                case self::COMMENT:
                    $out .= "$template->beforeComment/*{$this->htmlsp($token[1], $plain)}*/$template->afterComment";
                    break;

                case self::LINE_AT:
                    $out .= $token[1];
                    break;
            }
        }

        return trim($output);
    }

    /**
     * Gets the next token type, excluding comments
     * @param integer $key current position
     * @return int a token type
     */
    protected function seekNoComment($key)
    {
        while (isset($this->tokens[++$key])) {
            if ($this->tokens[$key][0] === self::COMMENT) {
                continue;
            }

            return $this->tokens[$key][0];
        }

        return 0;
    }

    /**
     * Converts $this->css array to a raw array ($this->tokens)
     * @param string $defaultMedia default @media to add to selectors without any @media
     */
    protected function convertRawCss($defaultMediaIsCurrentlyNotSupported = '')
    {
        $this->tokens = array();

        $sortSelectors = $this->configuration->getSortSelectors();
        $sortProperties = $this->configuration->getSortProperties();

        $this->blockToTokens($this->parsed, $sortSelectors, $sortProperties);
    }

    /**
     * @param Block $block
     * @param bool $sortSelectors
     * @param bool $sortProperties
     */
    protected function blockToTokens(Block $block, $sortSelectors = false, $sortProperties = false)
    {
        if ($sortSelectors && $block instanceof AtBlock) {
            $this->sortSelectors($block);
        }

        if ($sortProperties) {
            $this->sortProperties($block);
        }

        if ($block instanceof Selector) {
            $this->addToken(self::SEL_START, $block->getName());
        } else if ($block instanceof AtBlock && !$block instanceof Parsed) {
            $this->addToken(self::AT_START, $block->getName());
        }

        foreach ($block->elements as $element) {
            if ($element instanceof Property) {
                /** @var Property $element */
                $this->addToken(self::PROPERTY, $element->getName());
                $this->addToken(self::VALUE, $element->getValue());
            } else if ($element instanceof Block) {
                /** @var Element $element */
                $this->blockToTokens($element, $sortSelectors, $sortProperties);
            } else if ($element instanceof LineAt) {
                /** @var LineAt $element */
                $this->addToken(self::LINE_AT, $element->__toString());
            } else if ($element instanceof Comment) {
                if ($this->configuration->getPreserveComments()) {
                    $this->addToken(self::COMMENT, $element->__toString());
                }
            } else {
                var_dump($this->inputCss);
                throw new \Exception("Not supported element " . is_object($element) ? get_class($element) : 'n');
            }
        }

        if ($block instanceof Selector) {
            $this->addToken(self::SEL_END);
        } else if ($block instanceof AtBlock && !$block instanceof Parsed) {
            $this->addToken(self::AT_END);
        }
    }

    /**
     * Sort selectors inside at block
     * @param AtBlock $block
     */
    protected function sortSelectors(AtBlock $block)
    {
        uasort($block->elements, function($a, $b) {
            if (!$a instanceof Selector || !$b instanceof Selector) {
                return 0;
            }

            return strcasecmp($a->getName(), $b->getName());
        });
    }

    /**
     * Sort properties inside block with right order IE hacks
     * @param Block $block
     */
    protected function sortProperties(Block $block)
    {
        uksort($block->elements, function($a, $b) {
            static $ieHacks = array(
                '*' => 1, // IE7 hacks first
                '_' => 2, // IE6 hacks
                '/' => 2, // IE6 hacks
                '-' => 2  // IE6 hacks
            );

            if ($a{0} === '!' || $b{0} === '!') { // Compared keys are for selector, not for properties
                return 0;
            } else if (!isset($ieHacks[$a{0}]) && !isset($ieHacks[$b{0}])) {
                return strcasecmp($a, $b);
            } else if (isset($ieHacks[$a{0}]) && !isset($ieHacks[$b{0}])) {
                return 1;
            } else if (!isset($ieHacks[$a{0}]) && isset($ieHacks[$b{0}])) {
                return -1;
            } else if ($ieHacks[$a{0}] === $ieHacks[$b{0}]) {
                return strcasecmp(substr($a, 1), substr($b, 1));
            } else {
                return $ieHacks[$a{0}] > $ieHacks[$b{0}] ? 1 : -1;
            }
        });
    }

    /**
     * Same as htmlspecialchars, only that chars are not replaced if $plain !== true.
     * @param string $string
     * @param bool $plain
     * @return string
     */
    protected function htmlsp($string, $plain)
    {
        if (!$plain) {
            return htmlspecialchars($string, ENT_QUOTES, 'utf-8');
        }
        return $string;
    }

    /**
     * Replace url('abc.css') with "abc.css"
     * @param string $string
     * @return string
     */
    protected function removeUrl($string)
    {
        return preg_replace('~url\(["\']?([^\)\'" ]*)["\']?[ ]?\)~', '"$1"', $string);
    }

    /**
     * Adds a token to $this->tokens
     * @param int $type
     * @param string $data
     * @return void
     */
    public function addToken($type, $data = null)
    {
        $this->tokens[] = array($type, $data);
    }
}