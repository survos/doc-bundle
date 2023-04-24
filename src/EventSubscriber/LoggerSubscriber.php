<?php

namespace Survos\DocBundle\EventSubscriber;

use Codeception\Event\StepEvent;
use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Codeception\Module\Symfony;
use Codeception\Step;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class LoggerSubscriber implements EventSubscriberInterface //  extends Extension
{
    public function __construct(
        protected array $config = [],
        protected array $options = [],
        protected ?Environment $twig = null,
    ) {
//        parent::__construct($config, $this->options);
    }

    public function setTwig(Environment $twig)
    {
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        return [];
        return self::$events;
    }

    public static $events = [
//        Events::SUITE_BEFORE => 'beforeSuite',
//        Events::TEST_BEFORE => 'beforeTest',
//        Events::TEST_END => 'endTest',
//        //        Events::STEP_BEFORE     => 'beforeStep',
//        Events::STEP_AFTER => 'afterStep',
//        Events::SUITE_AFTER => 'afterSuite',
    ];

    /**
     * @var  Step[]
     */
    private $steps;

    private $tests = [];

    private $fileMap = [];

    // we could either create the rst file from twig as we go through each step, or accumulate each step then
    // pass everything to a single file, which probably makes more sense
    public function beforeSuite(SuiteEvent $e)
    {
        //        dd(false, $e::class);
    }

    private function getTwig(): Environment
    {
        return $this->twig;
        //        assert(false);
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        $twig = $symfony->grabService('twig');
        return $twig;
        //        dd($twig, $twig::class);
    }

    public function beforeTest(TestEvent $e)
    {
        $this->steps = [];
        $meta = $e->getTest()->getMetadata();
        $source = $meta->getFilename();
        $target = preg_replace('{/platform/tests/functional/(.*?)Cest.php}', '/documentation/_tutorials/$1.rst', $source);
        // die($meta->getFilename() . ' ' . $target);
    }

    public function endTest(TestEvent $e)
    {
        $meta = $e->getTest()->getMetadata();
        $source = $meta->getFilename();
        $target = preg_replace('{/platform/tests/functional/(.*?)Cest.php}', '/documentation/_tutorials/$1.rst', $source);
        $rstCode = basename($target, '.rst');
        $this->fileMap[$source] = $rstCode;
        assert(count($this->steps), "No steps defined.");
        //        if (count($this->steps)) {
        //            dd($source, $rstCode, $this->steps);
        //        }
        // possible to use $meta->getService() to get twig??
        $twig = $this->getTwig();
        $rst = $twig->render('SurvosDocBundle::TutorialTestSteps.rst.twig', [
            'test' => $e->getTest(),
            'rstCode' => $rstCode,
            'steps' => $this->steps,
        ]);
        // render rst file
        $targetDir = dirname($target);
        if (! file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        file_put_contents($target, $rst);
        //        echo sprintf("%s  written (%s).\n", $target, $rstCode);
        // dump($meta);
        array_push($this->tests, $e->getTest());
        $this->steps = [];
    }

    private function getTranslator(): TranslatorInterface
    {
        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');
        return $symfony->grabService('translator');
    }

    public function afterStep(StepEvent $e)
    {
        $step = $e->getStep();
        $trans = $this->getTranslator();
        $formName = $formData = $newFormData = null;
        // let's convert this to something we can use
        switch ($code = $step->getAction()) {
            case 'submitForm':
                $formName = $step->getArguments()[0];
                if (preg_match('/name="(.*?)"/', $formName, $matches)) {
                    $formName = $matches[1];
                }
                $formData = $step->getArguments()[1];
                foreach ($formData as $key => $value) {
                    if (preg_match_all('/(.+?)\[(.+?)\]/', $key, $matches) > 0) {
                        $form = $matches[1][0];
                        $field = $matches[2][0];
                        $key = $trans->trans("{$form}.{$field}.label");
                    }
                    $newFormData[] = [
                        'key' => $key,
                        'value' => $value,
                    ];
                }
        }
        $instruction = [
            'action' => $code,
            // 'arguments' => $step->getArguments(),
            'formName' => $formName,
            'formData' => $newFormData,
            'humanizedArguments' => $step->getHumanizedArguments(),
            'display' => $step->__toString(),
        ];
        array_push($this->steps, $instruction);
        // print $e->getStep() . "\n";
    }

    public function afterSuite(SuiteEvent $e)
    {
        // dump the list of tutorials

        $twig = $this->getTwig();

        // @todo: get templates from /home/tac/survos/projects/monorepo/platform/src/AdHocBundle/Resources/views
        $rst = $twig->render('SurvosDocBundle::cestTutorialIndex.rst.twig', [
            'fileMap' => $this->fileMap,
            'tests' => $this->tests,
            'suite' => $e->getSuite(),
        ]);
        $meta = $e->getSettings();
        $source = $meta['path'];
        // render rst file
        $target = preg_replace('{/platform/tests/functional/}', '/documentation/_tutorials/cest_index.rst', $source);
        file_put_contents($target, $rst);

        // dump($e); die();
    }
}
