<?php

namespace Survos\DocBundle\Command;

use DavidBadura\MarkdownBuilder\MarkdownBuilder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Survos\StateBundle\Service\SurvosGraphVizDumper;
use Symfony\Component\Process\Process;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * One markdown file per registered workflow: an inline Mermaid diagram (renders on
 * GitHub, diffs cleanly, reads as text), a co-located high-resolution .svg, the
 * places/transitions tables, and — when app listeners are subscribed to the
 * workflow's events — their PHP source inline.
 *
 * Deliberately type-hints no Symfony Workflow class in its constructor, so the
 * command stays registrable even when symfony/workflow isn't installed: with no
 * workflows tagged, it simply reports that there's nothing to document.
 */
#[AsCommand('doc:workflows', 'Generate agent-friendly markdown docs (Mermaid + source) for each registered workflow')]
final class WorkflowDocsCommand
{
    /**
     * @param iterable<WorkflowInterface> $workflows tagged-iterator of every registered workflow/state machine
     */
    public function __construct(
        #[AutowireIterator('workflow')]
        private readonly iterable $workflows,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Filesystem $fs,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Only document this workflow (by name); omit for all')]
        ?string $name = null,
        #[Option('Output directory, relative to project dir unless absolute')]
        string $outputDir = 'docs/workflow',
        #[Option('Also render an .svg beside each .md (needs graphviz "dot")')]
        bool $svg = true,
    ): int {
        /** @var array<string, WorkflowInterface> $workflows */
        $workflows = [];
        foreach ($this->workflows as $workflow) {
            if (!$workflow instanceof WorkflowInterface) {
                continue;
            }
            $wfName = $workflow->getName();
            if ($name !== null && $name !== $wfName) {
                continue;
            }
            $workflows[$wfName] = $workflow;
        }
        ksort($workflows);

        if ($workflows === []) {
            $io->warning($name !== null
                ? sprintf('No workflow named "%s" is registered.', $name)
                : 'No workflows are registered. Install symfony/workflow (and survos/state-bundle for listener docs) to use this command.');

            return Command::SUCCESS;
        }

        $dir = Path::makeAbsolute($outputDir, $this->projectDir);
        // A full run replaces every generated file (drops orphans) and rewrites the
        // index; a name filter just (re)writes that one workflow, leaving the rest intact.
        $fullRun = $name === null;
        if ($fullRun && $this->fs->exists($dir)) {
            $this->fs->remove((new Finder())->files()->in($dir)->depth(0)->name(['*.md', '*.svg']));
        }

        $generatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);
        $dotMissing = false;

        foreach ($workflows as $wfName => $workflow) {
            $svgWritten = $svg && $this->writeSvg($workflow, $dir, $dotMissing);
            $this->fs->dumpFile($dir . '/' . $wfName . '.md', $this->renderWorkflow($workflow, $generatedAt, $svgWritten));
        }

        if ($fullRun) {
            $this->fs->dumpFile($dir . '/README.md', $this->renderIndex($workflows, $generatedAt));
        }

        if ($dotMissing) {
            $io->note('graphviz "dot" not found — wrote Mermaid diagrams only (no .svg).');
        }
        $io->success(sprintf('%d workflow file(s) written to %s', count($workflows), $dir));

        return Command::SUCCESS;
    }

    private function renderWorkflow(WorkflowInterface $workflow, string $generatedAt, bool $svgWritten): string
    {
        $definition = $workflow->getDefinition();
        $store = $definition->getMetadataStore();
        $name = $workflow->getName();
        $isStateMachine = $workflow instanceof StateMachine;
        $md = new MarkdownBuilder();

        $md->h1('Workflow: ' . $name);
        $md->p($md->inlineItalic(sprintf(
            'Generated by `doc:workflows` on `%s`. Type: %s.',
            $generatedAt,
            $isStateMachine ? 'state machine' : 'workflow'
        )));

        $md->h2('Diagram');
        $md->code(trim($this->dumpMermaid($workflow)), 'mermaid');
        if ($svgWritten) {
            $md->p($md->inlineLink($name . '.svg', 'High-resolution SVG'));
        }

        $md->h2('Places');
        $initial = $definition->getInitialPlaces();
        $places = [];
        foreach ($definition->getPlaces() as $place) {
            $item = $md->inlineCode($place);
            if (in_array($place, $initial, true)) {
                $item .= ' ' . $md->inlineItalic('(initial)');
            }
            $meta = $store->getPlaceMetadata($place);
            $label = $meta['description'] ?? $meta['label'] ?? null;
            if (is_string($label) && $label !== '') {
                $item .= ' — ' . $label;
            }
            $places[] = $item;
        }
        $md->bulletedList($places);

        $md->h2('Transitions');
        $rows = [];
        foreach ($definition->getTransitions() as $transition) {
            $rows[] = [
                $md->inlineCode($transition->getName()),
                $this->codeList($transition->getFroms()),
                $this->codeList($transition->getTos()),
            ];
        }
        $md->table(['Transition', 'From', 'To'], $rows)->br();

        $this->appendListeners($md, $name);

        return $md->getMarkdown();
    }

    private function dumpMermaid(WorkflowInterface $workflow): string
    {
        $type = $workflow instanceof StateMachine
            ? MermaidDumper::TRANSITION_TYPE_STATEMACHINE
            : MermaidDumper::TRANSITION_TYPE_WORKFLOW;

        return (new MermaidDumper($type))->dump($workflow->getDefinition());
    }

    private function writeSvg(WorkflowInterface $workflow, string $dir, bool &$dotMissing): bool
    {
        if (!class_exists(Process::class)) {
            return false;
        }

        try {
            $process = new Process(['dot', '-Tsvg']);
            $process->setInput($this->dumpDot($workflow));
            $process->mustRun();
        } catch (\Throwable) {
            $dotMissing = true;

            return false;
        }

        $this->fs->dumpFile($dir . '/' . $workflow->getName() . '.svg', $process->getOutput());

        return true;
    }

    /**
     * Prefer state-bundle's dumper (it honors Survos place/transition metadata) when
     * installed; otherwise fall back to Symfony's built-in graphviz dumpers.
     */
    private function dumpDot(WorkflowInterface $workflow): string
    {
        $definition = $workflow->getDefinition();

        if (class_exists(SurvosGraphVizDumper::class)) {
            return (new SurvosGraphVizDumper())->dump($definition, new Marking(), [
                'name' => $workflow->getName(),
                'with-metadata' => true,
                'nofooter' => true,
                'label' => $workflow->getName(),
            ]);
        }

        $dumper = $workflow instanceof StateMachine ? new StateMachineGraphvizDumper() : new GraphvizDumper();

        return $dumper->dump($definition);
    }

    /**
     * Append a "Listeners" section: App\… listeners bound to this workflow's events,
     * each shown once with the events it handles and its inline PHP source.
     */
    private function appendListeners(MarkdownBuilder $md, string $wfName): void
    {
        $prefix = 'workflow.' . $wfName . '.';

        /** @var array<string, array{ref: \ReflectionMethod, events: list<string>}> $byMethod */
        $byMethod = [];
        foreach ($this->dispatcher->getListeners() as $eventName => $callables) {
            if (!str_starts_with((string) $eventName, $prefix)) {
                continue;
            }
            foreach ($callables as $callable) {
                if (!is_array($callable) || count($callable) !== 2) {
                    continue;
                }
                [$target, $method] = $callable;
                $class = is_object($target) ? $target::class : (string) $target;
                if (!str_starts_with($class, 'App\\')) {
                    continue;
                }
                $key = $class . '::' . $method;
                if (!isset($byMethod[$key])) {
                    try {
                        $byMethod[$key] = ['ref' => new \ReflectionMethod($class, $method), 'events' => []];
                    } catch (\ReflectionException) {
                        continue;
                    }
                }
                if (!in_array($eventName, $byMethod[$key]['events'], true)) {
                    $byMethod[$key]['events'][] = $eventName;
                }
            }
        }

        if ($byMethod === []) {
            return;
        }
        ksort($byMethod);

        $md->h2('Listeners');
        $md->p($md->inlineItalic("App listeners subscribed to this workflow's events, with source."));
        foreach ($byMethod as $key => $info) {
            $md->h3($md->inlineCode($key . '()'));
            $md->p('Events: ' . $this->codeList($info['events']));
            $source = $this->methodSource($info['ref']);
            if ($source !== null) {
                $md->p(sprintf('%s (L%d–L%d)', $md->inlineCode($source['path']), $source['start'], $source['end']));
                $md->code($source['code'], 'php');
            }
        }
    }

    /**
     * @return array{path: string, start: int, end: int, code: string}|null
     */
    private function methodSource(\ReflectionMethod $ref): ?array
    {
        $file = $ref->getFileName();
        $start = $ref->getStartLine();
        $end = $ref->getEndLine();
        if ($file === false || $start === false || $end === false) {
            return null;
        }

        $all = file($file);
        if ($all === false) {
            return null;
        }

        $snippet = array_slice($all, $start - 1, $end - $start + 1);

        return [
            'path' => $this->relativePath($file),
            'start' => $start,
            'end' => $end,
            'code' => $this->dedent($snippet),
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function dedent(array $lines): string
    {
        $min = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line, " \t"));
            if ($min === null || $indent < $min) {
                $min = $indent;
            }
        }
        $min ??= 0;

        return rtrim(implode('', array_map(
            static fn (string $line): string => substr($line, $min),
            $lines
        )), "\n");
    }

    private function relativePath(string $file): string
    {
        return Path::isBasePath($this->projectDir, $file)
            ? Path::makeRelative($file, $this->projectDir)
            : $file;
    }

    /**
     * @param list<string> $items
     */
    private function codeList(array $items): string
    {
        return implode(', ', array_map(static fn (string $item): string => '`' . $item . '`', $items));
    }

    /**
     * @param array<string, WorkflowInterface> $workflows
     */
    private function renderIndex(array $workflows, string $generatedAt): string
    {
        $md = new MarkdownBuilder();
        $md->h1('Workflows');
        $md->p($md->inlineItalic(sprintf('Generated by `doc:workflows` on `%s`. One file per workflow.', $generatedAt)));

        $items = [];
        foreach ($workflows as $wfName => $workflow) {
            $type = $workflow instanceof StateMachine ? 'state machine' : 'workflow';
            $items[] = $md->inlineLink($wfName . '.md', $md->inlineCode($wfName)) . ' — ' . $type;
        }
        $md->bulletedList($items);

        return $md->getMarkdown();
    }
}
