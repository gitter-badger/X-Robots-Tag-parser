<?php
/**
 * X-Robots-Tag HTTP header parser class
 *
 * @author VIP nytt (vipnytt@gmail.com)
 * @author Jan-Petter Gundersen (europe.jpg@gmail.com)
 *
 * Project:
 * @link https://github.com/VIPnytt/X-Robots-Tag-parser
 * @license https://opensource.org/licenses/MIT MIT license
 *
 * Specification:
 * @link https://developers.google.com/webmasters/control-crawl-index/docs/robots_meta_tag#using-the-x-robots-tag-http-header
 */

namespace vipnytt;

use vipnytt\XRobotsTagParser\directive;
use vipnytt\XRobotsTagParser\URLParser;
use vipnytt\XRobotsTagParser\UserAgentParser;

class XRobotsTagParser
{
    const HEADER_RULE_IDENTIFIER = 'x-robots-tag';
    const USERAGENT_DEFAULT = '';

    const DIRECTIVE_ALL = 'all';
    const DIRECTIVE_NONE = 'none';
    const DIRECTIVE_NO_ARCHIVE = 'noarchive';
    const DIRECTIVE_NO_FOLLOW = 'nofollow';
    const DIRECTIVE_NO_IMAGE_INDEX = 'noimageindex';
    const DIRECTIVE_NO_INDEX = 'noindex';
    const DIRECTIVE_NO_ODP = 'noodp';
    const DIRECTIVE_NO_SNIPPET = 'nosnippet';
    const DIRECTIVE_NO_TRANSLATE = 'notranslate';
    const DIRECTIVE_UNAVAILABLE_AFTER = 'unavailable_after';

    private $url = '';
    private $userAgent = self::USERAGENT_DEFAULT;

    private $headers = [];
    private $currentRule = '';
    private $currentUserAgent = self::USERAGENT_DEFAULT;
    private $currentDirective = '';
    private $currentValue = '';

    private $options = [];
    private $rules = [];

    /**
     * Constructor
     *
     * @param string $url
     * @param string $userAgent
     * @param array $options
     */
    public function __construct($url, $userAgent = self::USERAGENT_DEFAULT, $options = [])
    {
        // Parse URL
        $urlParser = new URLParser(trim($url));
        if (!$urlParser->isValid()) {
            trigger_error('Invalid URL', E_USER_WARNING);
        }
        // Encode URL
        $this->url = $urlParser->encode();
        // Set any optional options
        $this->options = $options;
        // Get headers
        $this->getHeaders();
        // Parse rules
        $this->parse();
        // Set User-Agent
        $parser = new UserAgentParser($userAgent);
        $this->userAgent = $parser->match(array_keys($this->rules), self::USERAGENT_DEFAULT);
    }

    /**
     * Request HTTP headers
     *
     * @return bool
     */
    private function getHeaders()
    {
        if (isset($this->options['headers'])) {
            $this->headers = $this->options['headers'];
            return true;
        }
        $this->headers = get_headers($this->url);
        if ($this->headers === false) {
            trigger_error('Unable to fetch HTTP headers', E_USER_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Parse HTTP headers
     *
     * @return void
     */
    private function parse()
    {
        foreach ($this->headers as $header) {
            $parts = explode(':', mb_strtolower($header), 2);
            if (count($parts) < 2 || $parts[0] != self::HEADER_RULE_IDENTIFIER) {
                // Header is not a rule
                continue;
            }
            $this->currentRule = trim($parts[1]);
            $this->detectDirectives();
        }

    }

    /**
     * Detect directives in rule
     *
     * @return void
     */
    private function detectDirectives()
    {
        $rules = explode(',', $this->currentRule);
        foreach ($rules as $rule) {
            $pair = array_map('trim', explode(':', $rule, 2));
            if ($rules[0] === $rule && count($pair) == 2 && !in_array($pair[0], $this->directiveArray())) {
                $this->currentUserAgent = $pair[0];
                $pair = array_map('trim', explode(':', $pair[1], 2));
            }
            if (in_array($pair[0], $this->directiveArray())) {
                $this->currentDirective = $pair[0];
                $this->currentValue = isset($pair[1]) ? $pair[1] : '';
                $this->addRule();
            }
        }
        $this->cleanup();
    }

    /**
     * Directives supported
     *
     * @return array
     */
    protected function directiveArray()
    {
        return [
            self::DIRECTIVE_ALL,
            self::DIRECTIVE_NONE,
            self::DIRECTIVE_NO_ARCHIVE,
            self::DIRECTIVE_NO_FOLLOW,
            self::DIRECTIVE_NO_IMAGE_INDEX,
            self::DIRECTIVE_NO_INDEX,
            self::DIRECTIVE_NO_ODP,
            self::DIRECTIVE_NO_SNIPPET,
            self::DIRECTIVE_NO_TRANSLATE,
            self::DIRECTIVE_UNAVAILABLE_AFTER
        ];
    }

    /**
     * Add rule
     *
     * @return void
     */
    private function addRule()
    {
        if (!isset($this->rules[$this->currentUserAgent])) {
            $this->rules[$this->currentUserAgent] = [];
        }
        $directive = new directive($this->currentDirective, $this->currentValue, $this->options);
        $this->rules[$this->currentUserAgent] = array_merge($this->rules[$this->currentUserAgent], $directive->getArray());
    }

    /**
     * Cleanup before next rule is read
     *
     * @return void
     */
    private function cleanup()
    {
        $this->currentRule = '';
        $this->currentUserAgent = self::USERAGENT_DEFAULT;
        $this->currentDirective = '';
        $this->currentValue = '';
    }

    /**
     * Return all applicable rules
     *
     * @return array
     */
    public function getRules()
    {
        $rules = [];
        // Default UserAgent
        if (isset($this->rules[self::USERAGENT_DEFAULT])) {
            $rules = array_merge($rules, $this->rules[self::USERAGENT_DEFAULT]);
        }
        // Matching UserAgent
        if (isset($this->rules[$this->userAgent])) {
            $rules = array_merge($rules, $this->rules[$this->userAgent]);
        }
        // Result
        return $rules;
    }

    /**
     * Export all rules for all UserAgents
     *
     * @return array
     */
    public function export()
    {
        return $this->rules;
    }
}