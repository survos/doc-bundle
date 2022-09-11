<?php

/*
 *
 */

namespace Survos\DocBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function Symfony\Component\String\u;

/**
 * Multiple Twig extensions: filters and functions
 */
class TwigExtension extends AbstractExtension
{
    public function __construct(
        private array $config)
    {

    }
    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {

        return [
        ];
    }


        /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {

        return [
            new TwigFunction('rst_h', [$this, 'rstHeader']),
        ];
    }

    public function rstHeader($level, $text): string
    {
        /*
         * # with overline, for parts
* with overline, for chapters
=, for sections
-, for subsections
^, for subsubsections
“, for paragraphs
         *
         */
        $levels = [null, '-', '^', '=','*','"'];
        return sprintf("%s\n%s\n\n", $text, str_repeat($levels[$level], mb_strlen($text)));
    }

    /**
     * add icon and sets target
     *
     */
    public function formatExternalLink(string $url, $class=''): string
    {
        return sprintf("<a target='_blank' href='%s'>%s <i class='fas fa-external-link'></i> </a>", $url, $url);
    }



}
