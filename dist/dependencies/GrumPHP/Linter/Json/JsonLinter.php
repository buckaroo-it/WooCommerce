<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\GrumPHP\Linter\Json;

use WC_Buckaroo\Dependencies\GrumPHP\Collection\LintErrorsCollection;
use WC_Buckaroo\Dependencies\GrumPHP\Linter\LinterInterface;
use WC_Buckaroo\Dependencies\GrumPHP\Util\Filesystem;
use WC_Buckaroo\Dependencies\Seld\JsonLint\JsonParser;
use WC_Buckaroo\Dependencies\Seld\JsonLint\ParsingException;
use SplFileInfo;

class JsonLinter implements LinterInterface
{
    /**
     * @var bool
     */
    private $detectKeyConflicts = false;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var JsonParser
     */
    private $jsonParser;

    public function __construct(Filesystem $filesystem, JsonParser $jsonParser)
    {
        $this->filesystem = $filesystem;
        $this->jsonParser = $jsonParser;
    }

    /**
     * @throws ParsingException
     */
    public function lint(SplFileInfo $file): LintErrorsCollection
    {
        $errors = new LintErrorsCollection();
        $flags = $this->calculateFlags();

        try {
            $json = $this->filesystem->readFromFileInfo($file);
            $this->jsonParser->parse($json, $flags);
        } catch (ParsingException $exception) {
            $errors->add(JsonLintError::fromParsingException($file, $exception));
        }

        return $errors;
    }

    public function isInstalled(): bool
    {
        return class_exists(JsonParser::class);
    }

    public function setDetectKeyConflicts(bool $detectKeyConflicts): void
    {
        $this->detectKeyConflicts = $detectKeyConflicts;
    }

    private function calculateFlags(): int
    {
        $flags = 0;
        $flags += $this->detectKeyConflicts ? JsonParser::DETECT_KEY_CONFLICTS : 0;

        return $flags;
    }
}
